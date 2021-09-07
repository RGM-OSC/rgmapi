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

    /**
     * Classe that define methods used by endpoints (routes)
     */
    const OBJ_METHODS = 'RgmApiMethods';

    /**
     * Constants defining HTTP headers for authentication
     */
    const TOKEN_PARAMETER_NAME = 'token';
    const TOKEN_PARAMETER_HEADER = 'X-RGM-Token';
    const TOKEN_PARAMETER_DATE = 'valid_until_1970';
    const TOKEN_PARAMETER_NAME_RFC = 'valid_until_rfc';

    /**
     * Constants defining JSON result status authorizations
     */
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_UNAUTHORIZED = 'unauthorized';

    /**
     * Commons HTTP codes
     */
    const HTTP_200 = 200;
    const HTTP_201 = 201;
    const HTTP_400 = 400;
    const HTTP_401 = 401;
    const HTTP_404 = 404;

    const CT_FORM = 'application/x-www-form-urlencoded';
    const CT_JSON = 'application/json';

    const INSERT_TOKEN = 'INSERT INTO tokens (token, user_id, creation_epoch, validity_epoch, usage_count, usage_max_count, enabled) VALUES (?, ?, UNIX_TIMESTAMP(), ?, 0, ?, 1)';

    const INC_TOKEN_COUNT = 'UPDATE tokens SET usage_count = usage_count + 1 WHERE TOKEN = ?';

    /**
     * validity_epoch = -1 : no limit in time
     * usage_max_count = -1 : no limit in usage count
     */
    const SELECT_VALID_TOKEN = 'SELECT * FROM tokens WHERE enabled = 1 AND token = ? AND (validity_epoch >= ? OR validity_epoch = -1) AND (usage_count < usage_max_count OR usage_max_count = -1)';

    const SELECT_USER_BY_ID = 'SELECT * FROM users WHERE user_id = ?';

    const SELECT_USER_BY_NAME = 'SELECT * FROM users WHERE user_name = ?';

    private static function insertToken($token, $user_id, $validity_epoch, $usage_max_count = -1)
    {
        global $database_rgmweb;
        RgmConnexion::sqlrequest($database_rgmweb, static::INSERT_TOKEN, false, array($token, $user_id, $validity_epoch, $usage_max_count));
    }

    private static function incTokenUsageCount($token)
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

        $salt = mcrypt_create_iv(22);
        $salt = base64_encode($salt);
        $salt = str_replace('+', '.', $salt);

        // share session mgmt with rgmweb
        $ret[static::TOKEN_PARAMETER_NAME] = hash('sha512', crypt($username, '$2y$10$' . $salt . '$') . $_SERVER['SERVER_ADDR']); // NOSONAR

        $ttl = RgmConfig::get('token_ttl');
        if ($ttl > 0) {
            $validity = time() + $ttl;
        } else {
            $validity = -1;
        }
        // Publish validity on 2 standards formats
        $ret[static::TOKEN_PARAMETER_DATE] = $validity;
        if ($ttl > 0) {
            $ret[static::TOKEN_PARAMETER_NAME_RFC] = date(DATE_RFC3339, $validity);
        } else {
            $ret[static::TOKEN_PARAMETER_NAME_RFC] = '';
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
     * Precedence rules : Authorization bearer token, then http header (X-RGM-Token), then GET, then POST (token)
     * @brief   find token in Slim::request parameters (either in URI params and/or in HTTP headers)
     */
    private static function getTokenParameter($request)
    {
        $token = null;
        // Eg.: Authorization: Bearer AbCdEf123456
        $bearer = $request->headers->get('Authorization');
        if ($bearer) {
            $bearer = explode(' ', $bearer);
            if (is_array($bearer) && count($bearer) === 2 && strcasecmp('bearer', $bearer[0]) === 0) {
                $token = $bearer[1];
            }
        }
        if (!$token) {
            $token = $request->headers->get(static::TOKEN_PARAMETER_HEADER);
        }
        if (!$token) {
            $token = $request->params(static::TOKEN_PARAMETER_NAME);
        }

        return $token;
    }

    private static function getJsonResponse($request, $response, $httpCode, $array = null)
    {
        // Set the compliant http code
        $method = $request->getMethod();
        $resultCode = true;
        if ($httpCode === static::HTTP_200) {
            if (is_array($array) && is_array($array['result'])) {
                if (isset($array['result']['code']) && $array['result']['code'] != 0) {
                    $resultCode = false;
                }
            }
            // Resource created
            if ($method === 'POST' && $resultCode) {
                $httpCode = static::HTTP_201;
            } elseif (!$resultCode) {
                // Resource not found
                $httpCode = static::HTTP_404;
            }
        }

        $codeMessage = $response->getMessageForCode($httpCode);
        $arrayHeader = array(
            'version' => static::VERSION,
            'code' => $httpCode,
            'message' => $codeMessage
        );
        $arrayMerge = array_merge($arrayHeader, $array);

        $jsonResponse = json_encode($arrayMerge, JSON_PRETTY_PRINT);

        $response->headers->set('Content-Type', 'application/json');
        $response->setStatus($httpCode);

        return $jsonResponse;
    }

    private static function constructResponse($request, $response, $logs, $authenticationValid = false)
    {
        if ($authenticationValid) {
            try {
                $array = array(
                    'status' => static::STATUS_AUTHORIZED,
                    'result' => $logs
                );
                $httpCode = static::HTTP_200;
            } catch (Exception $e) {
                $array = array('error' => $e->getMessage());
                $httpCode = static::HTTP_400;
            }
        } else {
            $array = array('status' => static::STATUS_UNAUTHORIZED);
            $httpCode = static::HTTP_401;
        }

        $result = static::getJsonResponse($request, $response, $httpCode, $array);
        echo $result;
    }

    /**
     * @brief   registers a user (provided username/password) and returns an auth token
     */
    public static function getAuthToken()
    {
        $app = Slim::getInstance();
        $request = $app->request();
        $response = $app->response();
        $username = $request->params('username');

        $user = static::getUserByName($username);
        if ($user && !empty($user)) {
            $user_id = $user['user_id'];
            $user_type = $user['user_type'];
            $userpasswd = $user['user_passwd'];
            $hash_method = $user['hash_method'];
            $request_password = RgmSession::getHashedPassword($request->params('password'), $hash_method); // NOSONAR
        } else {
            $array = array('message' => 'Wrong credentials (invalid username or password)');
            $result = static::getJsonResponse($request, $response, static::HTTP_401, $array);
            echo $result;
            return;
        }

        // access to API require user with admin privs and should be not an LDAP user
        $httpCode = static::HTTP_401;
        if ($user_type !== 1) {
            if ($userpasswd === $request_password) {
                $tokeninfo = static::genToken($username);
                static::insertToken($tokeninfo[static::TOKEN_PARAMETER_NAME], $user_id, $tokeninfo[static::TOKEN_PARAMETER_DATE]);
                $message = array(
                    static::TOKEN_PARAMETER_NAME => $tokeninfo[static::TOKEN_PARAMETER_NAME],
                    static::TOKEN_PARAMETER_DATE => $tokeninfo[static::TOKEN_PARAMETER_DATE],
                    static::TOKEN_PARAMETER_NAME_RFC => $tokeninfo[static::TOKEN_PARAMETER_NAME_RFC]
                );
                $httpCode = static::HTTP_201;
            } else {
                $message = array('message' => 'Wrong credentials (invalid username or password)');
            }
        } else {
            $message = array('message' => 'LDAP user not allowed to get RGMAPI token');
        }

        $result = static::getJsonResponse($request, $response, $httpCode, $message);
        echo $result;
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
            'status' => static::STATUS_UNAUTHORIZED
        );

        // Is token valid
        if ($token) {
            global $database_rgmweb;
            $query = RgmConnexion::sqlrequest_array($database_rgmweb, static::SELECT_VALID_TOKEN, array($token, time()));
            if (!empty($query)) {
                $tokenInfo['status'] = static::STATUS_AUTHORIZED;

                $user_id = $query[0]['user_id'];
                $user = static::getUserById($user_id);
                $tokenInfo['username'] = $user['user_name'];

                if ($user['group_id'] === 1) {
                    $tokenInfo['status'] = static::STATUS_AUTHORIZED;
                } elseif ($acl === ACL_ADMIN) {
                    $tokenInfo['status'] = static::STATUS_UNAUTHORIZED;
                } elseif ($acl === ACL_READONLY) {
                    $tokenInfo['status'] = static::STATUS_AUTHORIZED;
                }

                // Increment token usage_count
                static::incTokenUsageCount($token);
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
        $app = Slim::getInstance();
        $request = $app->request();
        $response = $app->response();
        $token = static::getTokenParameter($request);
        $httpcode = static::HTTP_401;
        $tokenInfo = static::checkAuthTokenValidity($token, ACL_READONLY);
        if ($tokenInfo['status'] === static::STATUS_AUTHORIZED) {
            $httpcode = static::HTTP_200;
        }

        echo static::getJsonResponse($request, $response, $httpcode, $tokenInfo);
    }

    /**
     * @brief   Kind of framework to add routes very easily
     * @details This function registers Slim routes to ObjectManager methods
     * @param string $httpMethod HTTP method (get, post, put, etc.) to use for route registration
     * @param string $routeName the API route name
     * @param string $methodName the callback function to register with this route, implemented on ObjectManager class
     * @param integer $acl the ACL the caller must comply with
     */
    public static function addRoute($httpMethod, $routeName, $methodName, $acl)
    {
        $app = Slim::getInstance();
        $app->map($routeName, function () use ($methodName, $acl, $app, $httpMethod) {
            $request = $app->request();
            $response = $app->response();
            $contentType = $request->getMediaType();
            if ($contentType === static::CT_JSON) {
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
                    $result = RgmApiCommon::getJsonResponse($request, $response, 406, $array);
                    echo $result;
                    return;
                }
            } else {
                $body = null;
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
                // second, parameter value takes precedence over header value
                if ($var = $request->get($paramName)) {
                    $params[1][$paramName] = $var;
                }
                // Third, if form encoded, post params
                if ($contentType === static::CT_FORM && $var = $request->post($paramName)) {
                    $params[1][$paramName] = $var;
                }
                // finally, data value takes precedence over all other for JSON message
                if ($body != null && isset($body[$paramName])) {
                    $params[1][$paramName] = $body[$paramName];
                }
            }
            if (isset($params[static::TOKEN_PARAMETER_NAME])) {
                unset($params[static::TOKEN_PARAMETER_NAME]);
            }
            // ensure passed parameters are required by routed function
            foreach ($request->get() as $key => $value) {
                if ($key === static::TOKEN_PARAMETER_NAME) {
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
                $result = static::getJsonResponse($request, $response, 417, $array);
                echo $result;
                return;
            }

            $tokenInfo = static::checkAuthTokenValidity($token, $acl);
            if ($tokenInfo['status'] === static::STATUS_AUTHORIZED) {
                $authOk = true;
            }
            if ($authOk) {
                $co = new $className($tokenInfo['username']);
                $methodResponse = call_user_func_array(array($co, $methodName), $params[1]);
            }

            static::constructResponse($request, $response, $methodResponse, $authOk);
        })->via($httpMethod);
    }
}
