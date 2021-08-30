<?php

use Slim\Slim;

const ACL_NOAUTH = 0b00001; // authentication not required (token *not* needed)
const ACL_READONLY = 0b00010; // action restricted to read-only
const ACL_ADMIN = 0b10000; // action restricted to admin users

// Mappings des roles
$ACL_MATRIX = array(
    "1" => ACL_ADMIN,
    "*" => ACL_READONLY
);

class RgmApiCommon
{
    const VERSION = '2.0';

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
    public static function genToken($username)
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
     * Precedence rules : http header, then GET, then POST
     * @brief   find token in Slim::request parameters (either in URI params and/or in HTTP headers)
     */
    public static function getTokenParameter($request)
    {
        $token = '';
        $body = $request->getBody();
        if ($header = $request->headers->get('token')) {
            $token = $header;
        } elseif ($var = $request->get('token')) {
            $token = $var;
        } elseif ($body && isset($body['token'])) {
            $token = $body['token'];
        }

        return $token;
    }

    public static function getJsonResponse($response, $code, $array = null)
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

    public static function constructResponse($response, $logs, $authenticationValid = false)
    {
        //Only if API keys match
        if ($authenticationValid) {
            try {
                $array = array('status' => 'authorized', 'result' => $logs);
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
        $username = $request->get('username');

        $user = static::getUserByName($username);
        if ($user && !empty($user)) {
            $user_id = $user['user_id'];
            $user_group = $user['group_id'];
            $user_type = $user['user_type'];
            $userpasswd = $user['user_passwd'];
            $hash_method = $user['hash_method'];
            $request_password = RgmSession::getHashedPassword($request->get('password'), $hash_method); // NOSONAR
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
    public static function checkAuthTokenValidity($token, $acl)
    {
        global $database_rgmweb;

        // By default : invalid token
        $tokenInfo = array(
            'status' => 'unauthorized'
        );

        // Is token valid
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
}
