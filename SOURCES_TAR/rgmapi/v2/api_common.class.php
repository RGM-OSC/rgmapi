<?php

use Slim\Slim;

const ACL_NOAUTH = 0b00001; // authentication not required (token *not* needed)
const ACL_READONLY = 0b00010; // action restricted to read-only
const ACL_ADMIN = 0b10000; // action restricted to admin users

// Mappings des roles : unused at this time
$ACL_MATRIX = array(
    "1" => ACL_ADMIN,
    "*" => ACL_READONLY
);

class RgmApiCommon
{
    const VERSION = '2.0';

    const OBJ_METHODS = 'RgmApiMethods';

    const INSERT_TOKEN = 'INSERT INTO tokens (token, user_id, creation_epoch, validity_epoch, usage_count, usage_max_count, enabled) VALUES (?, ?, UNIX_TIMESTAMP(), ?, 0, ?, 1)';

    const INC_TOKEN_COUNT = 'UPDATE tokens SET usage_count = usage_count + 1 WHERE TOKEN = ?';

    /**
     * validity_epoch = -1 : no limit in time
     */
    const SELECT_VALID_TOKEN = 'SELECT * FROM tokens WHERE enabled = 1 AND token = ? AND (validity_epoch >= ? OR validity_epoch = -1) AND (usage_count < usage_max_count OR usage_max_count = -1)';

    const SELECT_USER_BY_ID = 'SELECT * FROM users WHERE user_id = ?';

    const SELECT_USER_BY_NAME = 'SELECT * FROM users WHERE user_name = ?';

    private static function insertToken($token, $user_id, $validity_epoch, $usage_max_count = -1)
    {
        global $database_rgmweb;
        RgmConnexion::sqlrequest($database_rgmweb, static::INSERT_TOKEN, false, array($token, $user_id, $validity_epoch, $usage_max_count));
    }

    private static function incUsageCount($token)
    {
        global $database_rgmweb;
        RgmConnexion::sqlrequest($database_rgmweb, static::INC_TOKEN_COUNT, false, array($token));
    }

    /**
     * @brief   Generates a unique token and session id
     * @details Generates a unique token and session ID when invoked. It does @b not
     *          handle any database related operation.
     *          This function is not exported throught Slim routing handlers
     * @param string $username The username used to create the token
     * @return array An array composed of @b session and @b token keys
     */
    private static function genToken($username)
    {
        // gen token randomly
        $ret = array();

        $salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
        $salt = base64_encode($salt);
        $salt = str_replace('+', '.', $salt);

        // share session mgmt with rgmweb
        $ret['token'] = hash('sha512', crypt($username, '$2y$10$' . $salt . '$') . $_SERVER['SERVER_ADDR']); // NOSONAR

        $ttl = RgmConfig::get('token_ttl');
        if ($ttl > 0) {
            $validity = time() + $ttl;
        } else {
            $validity = -1;
        }
        // Publish validity on 2 standards formats
        $ret['valid_until_1970'] = $validity;
        if ($ttl > 0) {
            $ret['valid_until_rfc'] = date(DATE_RFC3339, $validity);
        } else {
            $ret['valid_until_rfc'] = '';
        }

        return $ret;
    }

    private static function getUserById($id)
    {
        global $database_rgmweb;
        $res = RgmConnexion::sqlrequest_array($database_rgmweb, self::SELECT_USER_BY_ID, array($id));

        if (!empty($res)) {
            return $res[0];
        }

        return '';
    }

    private static function getUserByName($username)
    {
        global $database_rgmweb;
        $res = RgmConnexion::sqlrequest_array($database_rgmweb, self::SELECT_USER_BY_NAME, array($username));

        if (!empty($res)) {
            return $res[0];
        }

        return '';
    }

    /**
     * Precedence rules : http header (X-RGM-Token), then GET, then POST (token)
     * @brief   find token in Slim::request parameters (either in URI params and/or in HTTP headers)
     */
    private static function getTokenParameter($request)
    {
        $token = $request->headers->get('X-RGM-Token');
        if (!$token) {
            $token = $request->params('token');
        }

        return $token;
    }

    private static function getJsonResponse($response, $code, $array = null)
    {
        global $app;

        // RGM API version is the concatenation on Slim framework version *and* RGM API level revision
        $codeMessage = $response->getMessageForCode($code);
        $arrayHeader = array(
            'version' => static::VERSION,
            'code' => $code,
            'message' => $codeMessage
        );
        $arrayMerge = array_merge($arrayHeader, $array);

        $jsonResponse = json_encode($arrayMerge, JSON_PRETTY_PRINT);
        $jsonResponseWithHeader = $jsonResponse;

        $app->response->headers->set('Content-Type', 'application/json');
        $app->response->setStatus($codeMessage);

        return $jsonResponseWithHeader;
    }

    private static function constructResponse($response, $logs, $authenticationValid = false)
    {
        //Only if API keys match
        if ($authenticationValid) {
            try {
                $array = array(
                    'status' => 'authorized',
                    'result' => $logs
                );
                $result = static::getJsonResponse($response, 200, $array);
                echo $result;
            } catch (PDOException $e) {
                $array = array('error' => $e->getMessage());
                $result = static::getJsonResponse($response, 400, $array);
                echo $result;
            }
        } else {
            $array = array('status' => 'unauthorized');
            $result = static::getJsonResponse($response, 401, $array);
            echo $result;
        }
    }

    /**
     * @brief   registers a user (provided username/password) and returns an auth token
     */
    public static function getAuthToken()
    {
        $request = Slim::getInstance()->request();
        $response = Slim::getInstance()->response();
        $username = $request->params('username');

        $user = static::getUserByName($username);
        if ($user && !empty($user)) {
            $user_id = $user['user_id'];
            $user_type = $user['user_type'];
            $userpasswd = $user['user_passwd'];
            $hash_method = $user['hash_method'];
            $request_password = RgmSession::getHashedPassword($request->params('password'), $hash_method); // NOSONAR
        } else {
            $array = array("message" => "Wrong credentials (invalid username or password)");
            $result = static::getJsonResponse($response, 401, $array);
            echo $result;
            return;
        }

        // access to API require user with admin privs and should be not an LDAP user
        if ($user_type !== 1) {
            if ($userpasswd === $request_password) {
                $tokeninfo = static::genToken($username);
                static::insertToken($tokeninfo['token'], $user_id, $tokeninfo['valid_until_1970']);
                $array = array(
                    "token" => $tokeninfo['token'],
                    "valid_until_1970" => $tokeninfo['valid_until_1970'],
                    "valid_until_rfc" => $tokeninfo['valid_until_rfc']
                );
                $result = static::getJsonResponse($response, 200, $array);
                echo $result;
            } else {
                $array = array("message" => "Wrong credentials (invalid username or password)");
                $result = static::getJsonResponse($response, 401, $array);
                echo $result;
            }
        } else {
            $array = array("message" => "User not allowed to get RGMAPI token");
            $result = static::getJsonResponse($response, 401, $array);
            echo $result;
        }
    }

    /**
     * @brief check token validity
     * @details check the validity of the token passed as argument. It return an array with token details
     * @param string $token the token to check
     * @param integer $acl
     * @return array $tokenInfo  an array with token details
     */
    private static function checkAuthTokenValidity($token, $acl)
    {
        // By default : invalid token
        $tokenInfo = array(
            'status' => 'unauthorized'
        );

        // Is token valid
        if ($token) {
            global $database_rgmweb;
            $query = RgmConnexion::sqlrequest_array($database_rgmweb, static::SELECT_VALID_TOKEN, array($token, time()));
            if (!empty($query)) {
                $tokenInfo['status'] = "authorized";

                $user_id = $query[0]['user_id'];
                $user = static::getUserById($user_id);
                $tokenInfo['username'] = $user['user_name'];

                if ($user['group_id'] === 1) {
                    $tokenInfo['status'] = "authorized";
                } elseif ($acl === ACL_ADMIN) {
                    $tokenInfo['status'] = "unauthorized";
                } elseif ($acl === ACL_READONLY) {
                    $tokenInfo['status'] = "authorized";
                }

                // Increment token usage_count
                static::incUsageCount($token);
            } else {
                error_log('checkAuthTokenValidity() : unknown token=' . $token);
            }
        } else {
            error_log('checkAuthTokenValidity() : empty token');
        }

        return $tokenInfo;
    }

    public static function checkAuthToken()
    {
        $request = Slim::getInstance()->request();
        $response = Slim::getInstance()->response();
        $token = static::getTokenParameter($request);
        $httpcode = 401;
        $tokenInfo = static::checkAuthTokenValidity($token, ACL_READONLY);
        if ($tokenInfo['status'] === 'authorized') {
            $httpcode = 200;
        }

        echo static::getJsonResponse($response, $httpcode, $tokenInfo);
    }

    /**
     * @param $app
     * @brief   Kind of framework to add routes very easily
     * @details This function registers Slim routes to ObjectManager methods
     * @param Slim $app
     * @param string $httpMethod HTTP method (get, post, put, etc.) to use for route registration
     * @param string $routeName the API route name
     * @param string $methodName the callback function to register with this route, implemented on ObjectManager class
     * @param integer $acl the ACL the caller must comply with
     * @throws ReflectionException
     */
    public static function addRoute($app, $httpMethod, $routeName, $methodName, $acl)
    {
        $app->map($routeName, function () use ($methodName, $acl) {
            $request = Slim::getInstance()->request();
            $response = Slim::getInstance()->response();
            // ROAROA : ici il faudrait tester le content-type demandé
            // afin de faire soit du JSON (comme c'est le cas ici présent),
            // soit du form-encoded
            $body = json_decode($request->getBody(), true);
            if (($err = json_last_error()) != 0) {
                $array = array('error' => 'JSON Error');
                switch ($err) {
                    case JSON_ERROR_DEPTH:
                        $array['error'] = 'JSON Error: JSON_ERROR_DEPTH';
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        $array['error'] = 'JSON Error: JSON_ERROR_STATE_MISMATCH';
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        $array['error'] = 'JSON Error: JSON_ERROR_CTRL_CHAR';
                        break;
                    case JSON_ERROR_SYNTAX:
                        $array['error'] = 'JSON Error: JSON_ERROR_SYNTAX';
                        break;
                    case JSON_ERROR_UTF8:
                        $array['error'] = 'JSON Error: JSON_ERROR_UTF8';
                        break;
                    case JSON_ERROR_RECURSION:
                        $array['error'] = 'JSON Error: JSON_ERROR_RECURSION';
                        break;
                    case JSON_ERROR_INF_OR_NAN:
                        $array['error'] = 'JSON Error: JSON_ERROR_INF_OR_NAN';
                        break;
                    case JSON_ERROR_UNSUPPORTED_TYPE:
                        $array['error'] = 'JSON Error: JSON_ERROR_UNSUPPORTED_TYPE';
                        break;
                    // Errors known from PHP 7
                    // case JSON_ERROR_INVALID_PROPERTY_NAME:
                    //     $array['error'] = 'JSON Error: JSON_ERROR_INVALID_PROPERTY_NAME';
                    //     break;
                    // case JSON_ERROR_UTF16:
                    //     $array['error'] = 'JSON Error: JSON_ERROR_UTF16';
                    //     break;
                    default:
                        $array['error'] = 'JSON Error: UNKNOWN ERROR';
                        break;
                }
                $result = RgmApiCommon::getJsonResponse($response, 406, $array);
                echo $result;
                return;
            }
            $authOk = false;
            $methodResponse = '';
            $params = array(array(), array());
            $msg = '';
            $token = RgmApiCommon::getTokenParameter($request);

            // retrieve routed function parameters, ensure all parameters are provided
            // (or have an acceptable default value in function)
            $className = static::OBJ_METHODS;
            $reflector = new ReflectionMethod($className, $methodName);
            foreach ($reflector->getParameters() as $param) {
                $paramName = $param->name;
                $params[0][] = $paramName;
                $params[1][$paramName] = null;
                // first, set default value, if exists
                if ($param->isDefaultValueAvailable()) {
                    $params[1][$paramName] = $param->getDefaultValue();
                }
                //second, header value takes precedence over default value
                if ($header = $request->headers->get($paramName)) {
                    $params[1][$paramName] = $header;
                }
                //third, parameter value takes precedence over header value
                if ($var = $request->get($paramName)) {
                    $params[1][$paramName] = $var;
                }
                // finally, data value takes precedence over all other
                if (isset($body[$paramName])) {
                    $params[1][$paramName] = $body[$paramName];
                }
            }
            if (isset($params['token'])) {
                unset($params['token']);
            }
            // ensure passed parameters are required by routed function
            foreach ($request->get() as $key => $value) {
                if ($key == 'token') {
                    continue;
                }
                if (!in_array($key, $params[0])) {
                    if ($msg != '') {
                        $msg .= ', ';
                    }
                    $msg .= 'unknown parameter: ' . $key;
                }
            }
            // ensure function have all requested parameters filled
            $keys = array_keys($params[1]);
            foreach ($params[0] as $fnparam) {
                if (!in_array($fnparam, $keys)) {
                    if ($msg != '') {
                        $msg .= ', ';
                    }
                    $msg .= 'missing parameter: ' . $keys;
                }
            }
            // if unknown or missing parameters found, exit with a http 417 error code
            if ($msg != '') {
                $array = array('message' => $msg);
                $result = static::getJsonResponse($response, 417, $array);
                echo $result;
                return;
            }

            $tokenInfo = static::checkAuthTokenValidity($token, $acl);
            if ($tokenInfo['status'] == 'authorized') {
                $authOk = true;
            }
            if ($authOk) {
                $co = new $className($tokenInfo['username']);
                $methodResponse = call_user_func_array(array($co, $methodName), $params[1]);
            }

            static::constructResponse($response, $methodResponse, $authOk);
        })->via($httpMethod);
    }
}
