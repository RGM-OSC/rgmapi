<?php
/**
 * RGMAPI - authentication calls
 * Copyright (c) 2019 SCC France - RGM Team
 * Eric Belhomme <ebelhomme@fr.scc.com>
 * 
 * LICENCE :
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */


/**
 * @brief   Generates a unique token and session id
 * @details Generates a unique token and session ID when invoked. It does @b not
 *          handle any database related operation.
 *          This function is not exported throught Slim routing handlers
 * @param   $username The username used to create the token
 * @return  an array composed of @b session and @b token keys 
 */
function _genToken($username) {
    // gen token randomly
    $ret = array();
    global $rgm_session;
    // share session mgmt with rgmweb
    $ret['session'] = $rgm_session->generate_session_id();
    $salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
    $salt = base64_encode($salt);
    $salt = str_replace('+', '.', $salt);
    $ret['token'] = hash('sha256', crypt($username, '$2y$10$'.$salt.'$') . $_SERVER['SERVER_ADDR']); // NOSONAR
    return $ret;
}

/**
 * @brief   Generates a one-time token
 * @details When invoked, this function generates a new token and insert it into
 *          the @b sessions table
 * @param   none
 * @return  nothing
 */
function _genOneTimeToken() {
    global $database_rgmweb;
    $tokeninfo = _genToken('one-time-token');
    sqlrequest(
        $database_rgmweb,
        "INSERT INTO sessions (session_id, session_token, session_type, creation_epoch, update_epoch) VALUES (?, ?, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
        true,
        array($tokeninfo['session'], $tokeninfo['token'])
    );
}

/**
 * @brief   check token validity
 * @details check the validity of the token passed as argument. It return an array with token details
 * @param   $request    a pointer to Slimm::request class with current context
 * @param   $token      the token to check
 * @return  $tokenInfo  an array with token details
 */
function checkAuthTokenValidity($request, $token) {
    global $database_rgmweb;
    global $rgmauth_ttl;

    $tokenInfo = array(
        'session_id' => 'None',
        'creation_epoch' => 0,
        'status' => 'unauthorized',
        'username' => ''
    );
    $now = time();
    
    // clean expired tokens
    // FIXME: Alex: j'émets une réserve sur cette suppression, j'ai un doute que le calcul soit identique
    // à ce qu'on fait ailleurs. UNIX_TIMESTAMP() ==> à vérifier
    // D'autre part si on ne veut supprimer que les sessions liées à des tokens, autant ajouter la clause qui
    // va bien. D'une manière générale, je suis pas pour DU TOUT pour supprimer des éléments d'une table de session.
    // Ca sert quand même de trace, on peut purger tout ce qui a plus d'un an par exemple mais pas des trucs échus
    // depuis une journée.
    sqlrequest(
        $database_rgmweb,
        "DELETE FROM sessions WHERE creation_epoch < ? AND session_type IN (2, 3)",
        false,
        array($now - $rgmauth_ttl)
    );

    // try to find an existing token
    $stmt = sqlrequest(
        $database_rgmweb,
        "SELECT session_id, creation_epoch, user_id, session_type FROM sessions WHERE session_type IN (2, 3) AND session_token = ?",
        false,
        array($token)
    );
    $sql_raw = mysqli_fetch_row($stmt);

    if (count($sql_raw) == 4) {
        $tokenInfo['session_id'] = $sql_raw[0];
        $tokenInfo['creation_epoch'] = $sql_raw[1];
        $tokenInfo['status'] = "authorized";
        $session_type = $sql_raw[3];
        switch ($session_type) {
            case 2:
                $stmt = sqlrequest($database_rgmweb, "SELECT user_name FROM users WHERE user_id = ?", false, array($sql_raw[2]));
                $tokenInfo['username'] = mysqli_result($stmt, 0, "user_name");
                break;
            case 3:
                $tokenInfo['username'] = 'one-time-token';
                break;
            default:
                error_log("checkAuthTokenValidity() : unknown session_type=" . $session_type);
                break;
        }
    }

    return $tokenInfo;
}

/**
 * @brief   registers a user (provided username/password) and returns an auth token
 */
function getAuthToken() {
    global $database_rgmweb;
    $request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();

    $user_id = $user_right = $user_type = 0;
    $userpasswd = '';

    $username = $request->get('username');
    $password = RgmSession::getHashedPassword($request->get('password')); // NOSONAR
    
    if ($userintable = getUserByUsername($username)) {
        $user_id = mysqli_result($userintable, 0, "user_id");
        $user_right = mysqli_result($userintable, 0, "readonly");
        $user_type = mysqli_result($userintable, 0, "user_type");
        $userpasswd = mysqli_result($userintable, 0, "user_passwd");
    } else {
        $array = array("message" => "Wrong credentials (invalid username or password)");
        $result = getJsonResponse($response, "401", $array);
        echo $result;
        return;
    }
    
    // access to API require user with admin privs
    if ($user_type != "1" && $user_right == "1") {
        //IF match the hashed password
        if($userpasswd == $password) {
            $tokeninfo = _genToken($username);
            sqlrequest(
                $database_rgmweb,
                "INSERT INTO `sessions` (session_id, user_id, session_type, session_token, creation_epoch) VALUES (?, ?, 2, ?, ?)",
                false,
                array($tokeninfo['session'], $user_id, $tokeninfo['token'], time())
            );
            $array = array("RGMAPI_TOKEN" => $tokeninfo['token']);
            $result = getJsonResponse($response, "200", $array);
            echo $result;
        } else {
            $array = array("message" => "Wrong credentials (invalid username or password)");
            $result = getJsonResponse($response, "401", $array);
            echo $result;
        }  
    } else {
        $array = array("message" => "User not allowed to get RGMAPI token");
        $result = getJsonResponse($response, "401", $array);
        echo $result;
    }
}

/**
 * @brief   find token in Slim::request parameters (either in URI params and/or in HTTP headers)
 */
function getTokenParameter($request, $body = null) { 
    // Search for token parameter passed as variable or in http headers
    $token = '';
    if ($header = $request->headers->get('token')) {
        $token = $header;
    }
    if ( $var = $request->get('token')) {
        $token = $var;
    }
    if (isset($body['token'])) {
        $token = $body['token'];
    }
    return $token;
}

function checkAuthToken($token) {
    $request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();
    $token = getTokenParameter($request);
    $httpcode = '401';
    $tokenInfo = checkAuthTokenValidity($request, $token);
    if ($tokenInfo['status'] == 'authorized')
        $httpcode = '200';
    
    $result = getJsonResponse($response, $httpcode, $tokenInfo);
    echo $result;
}

function clearOneTimeToken($token) {
    global $database_rgmweb;
    sqlrequest( $database_rgmweb, "DELETE FROM sessions WHERE session_type = 3 AND session_token = ?", false, array($token));
}
?>
