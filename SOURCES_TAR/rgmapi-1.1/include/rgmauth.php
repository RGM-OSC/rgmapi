<?php
/*
RGMAPI - authentication calls
Copyright (c) 2019 SCC France - RGM Team
Eric Belhomme <ebelhomme@fr.scc.com>

LICENCE :
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

function checkAuthTokenValidity($request, $acl){
    global $database_rgmweb;
    global $rgmauth_ttl;

    $tokenInfo = array(
        "session_id" => "None",
        "user_id" => 0,
        "creation_epoch" => 0,
        "status" => "unauthorized"
    );
    $now = time();
    
    //Parameters in request
    $token = $request->get('token');

    // clean expired tokens
    sqlrequest( $database_rgmweb, "DELETE FROM sessions WHERE creation_epoch < '" . ($now - $rgmauth_ttl) . "';", false);

    // try to find an existing token
    $stmt = sqlrequest( $database_rgmweb, "SELECT session_id, creation_epoch, user_id, session_type FROM sessions "
        . "WHERE session_type >= 2 AND  session_type <= 3 AND session_token = '" . $token . "';",  false);
    $sql_raw = mysqli_fetch_row($stmt);

    if (count($sql_raw) == 3) {
        $tokenInfo['session_id'] = $sql_raw[0];
        $tokenInfo['user_id'] = $sql_raw[2];
        $tokenInfo['creation_epoch'] = $sql_raw[1];
        $tokenInfo['status'] = "authorized";
    }
    return $tokenInfo;
}

function getAuthToken() {
    global $database_rgmweb;
    $request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();

    $user_id = $user_right = $user_type = 0;
    $userpasswd = '';

    $username = $request->get('username');
    $password = md5($request->get('password'));
    
    if ( ($userintable = getUserByUsername($username)) ) {
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
    if( $user_type != "1" && $user_right == "1") {
        
        //IF match the hashed password
        if($userpasswd == $password) {
            // share session mgmt with rgmweb
            $sessid = genSessionId();
            // gen token randomly
            $salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
            $salt = base64_encode($salt);
            $salt = str_replace('+', '.', $salt);
            $token = hash('sha256', crypt($username, '$2y$10$'.$salt.'$') . $_SERVER['SERVER_ADDR']);
    
            $newsession = sqlrequest( $database_rgmweb, "INSERT INTO `sessions` (session_id, user_id, session_type, session_token, creation_epoch) VALUES ('" .
                $sessid . "','" . $user_id . "', '2', '" . $token . "', '". time() . "');", true);
    
            $array = array("RGMAPI_TOKEN" => $token);
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

function checkAuthToken($acl){
    $request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();

    $httpcode = '401';
    $tokenInfo = checkAuthTokenValidity($request, $acl);
    if ($tokenInfo['status'] == 'authorized')
        $httpcode = '200';
    
    $result = getJsonResponse($response, $httpcode, $tokenInfo);
    echo $result;
}

function clearOneTimeToken($token) {
    global $database_rgmweb;
    sqlrequest( $database_rgmweb, "DELETE FROM sessions WHERE session_type = 3 AND session_token = '" . $token . "';", false);
}
?>
