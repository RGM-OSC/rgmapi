<?php
/*
#
#
# RGMAPI 
# Route calls
#
# Copyleft 2018 RGM
# Author: BU DCA Team based on Adrien van den Haak initial work (https://github.com/EyesOfNetworkCommunity/rgmapi)
# 
#
#
*/

// => Modify this key with your own secret at initialization
//define("RGMAPI_KEY", "C0n5t3ll@t10n");


/* API key encryption */
/*
function genApiKey( $user_id )
{
//    $key = md5(RGMAPI_KEY.$user_id);

    $salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);
    $salt = base64_encode($salt);
    $salt = str_replace('+', '.', $salt);

    return hash('sha256', crypt(RGMAPI_KEY.$user_id, '$2y$10$'.$salt.'$') . $_SERVER['SERVER_ADDR']);
}
*/

const ACL_NOAUTH     = 0b00001; // authentication not required (token *not* needed)
const ACL_READONLY   = 0b00010; // action restricted to read-only
const ACL_1T_TOKEN   = 0b00100; // one-time token authorized for this action
const ACL_1T_NOCLEAR = 0b00100; // don't clear one-time token after call
const ACL_ADMIN      = 0b10000; // action restricted to admin users



/*---General functions--*/
//function getParametersNameFunction( $className, $functionName ){
//    $reflector = new ReflectionMethod($className, $functionName);
//    $params = array(array(),array());
// 
//    foreach ($reflector->getParameters() as $param) {
//        $params[0][] = $param->name;
//        if( $param->isDefaultValueAvailable() ){
//		$params[1][$param->name] = $param->getDefaultValue(); 
//	}
//    }
//    
//    return $params;
//}
//
//function getParametersNameCompulsoryFunction( $className, $functionName ){
//    $reflector = new ReflectionMethod($className, $functionName);
//    $params = array();
//    
//    foreach ($reflector->getParameters() as $param) {
//        if( $param->isDefaultValueAvailable() == false ){
//            $params[] = $param->name;
//        }
//    }
//    
//    return $params;
//}
//
//function has_empty($array) {
//    foreach ($array as $value) {
//        if ($value == null)
//            return true;
//    }
//    return false;
//}

function getUserByUsername( $username ){
    global $database_rgmweb;
    
    $usersql = sqlrequest($database_rgmweb,
		"SELECT U.user_id as user_id,U.user_name as user_name,U.user_passwd as user_passwd,U.user_type as user_type,
		U.user_limitation as user_limitation,R.tab_1 as readonly,R.tab_2 as operator,R.tab_6 as admin
		FROM users as U left join groups as G on U.group_id = G.group_id left join groupright as R on R.group_id=G.group_id
		WHERE U.user_name = '".$username."'",
		false,
		array((string)$username)
	);
    
    return $usersql;
}

/*---HTTP Response---*/
function getJsonResponse( $response, $code, $array = null ){
	
	global $app;
    
    // RGM API version is the concatenation on Slim framework version *and* RGM API level revision
    $codeMessage = $response->getMessageForCode($code);
    $arrayHeader = array(
        "slim_version" => \Slim\Slim::VERSION,
        "rgmapi_version" => '1.0',
        "http_code" => $codeMessage
    );
    $arrayMerge = array_merge( $arrayHeader, $array );

    $jsonResponse = json_encode($arrayMerge, JSON_PRETTY_PRINT);
    $jsonResponseWithHeader = $jsonResponse;

	$app->response->headers->set('Content-Type', 'application/json');
	$app->response->setStatus($codeMessage);
	
    return $jsonResponseWithHeader;
}

function constructResponse( $response, $logs, $authenticationValid = false ){
    //Only if API keys match
    if($authenticationValid == true){
        try {
            $array = array("result" => $logs);
            $result = getJsonResponse($response, "200", $array);
            echo $result;
        }
        catch(PDOException $e) {
            //error_log($e->getMessage(), 3, '/var/tmp/php.log');
            $array = array("error" => $e->getMessage());
            $result = getJsonResponse($response, "400", $array);
            echo $result;
        }
    }
    else{
        $array = array("status" => "unauthorized");
        $result = getJsonResponse($response, "401", $array);
        echo $result;
    }
}

/*---Authorization checks--*/
/*
function verifyAuthenticationByApiKey( $request, $right ){
    $authenticationValid = false;
    
    //Parameters in request
    $paramUsername = $request->get('username');
    $paramApiKey = $request->get('apiKey');
    
    //Do not set $serverApiKey to NULL (bypass risk)
    $serverApiKey = RGMAPI_KEY;
    
    $usersql = getUserByUsername( $paramUsername );
    $user_right = mysqli_result($usersql, 0, $right);
    $user_type = mysqli_result($usersql, 0, "user_type");
    
    //IF LOCAL USER AND ADMIN USER (No limitation)
    if( $user_type != "1" && $user_right == "1") {
        //ID of the authenticated user
        $user_id = mysqli_result($usersql, 0, "user_id");

        global $database_rgmweb;
    
        $sessions = sqlrequest( $database_rgmweb, "SELECT session_id, session_token, creation_epoch FROM sessions WHERE session_type = '2' AND user_id = '"
            . $user_id . "' ORDER BY creation_epoch;",  false);
        $now = time();
        while ($sql_raw = mysqli_fetch_array($sessions)) {
            if ($sql_raw[2] + 86400 > $now) {
                sqlrequest( $database_rgmweb, "DELETE FROM sessions WHERE session_id = '" . $sql_raw[0] ."';", false);
            } else {
                $serverApiKey = $sql_raw[1];
            }
        }
    }
    
    //Only if API keys match
    if($paramApiKey == $serverApiKey){
        $authenticationValid = true;
    }

    
    return $authenticationValid;
}
*/

/*
function verifyAuthenticationByPassword( $request ){
    $authenticationValid = false;
    
    //Parameters in request
    $paramUsername = $request->get('username');
    $paramPassword = $request->get('password');
    
    $usersql = getUserByUsername( $paramUsername );
    $user_right = mysqli_result($usersql, 0, "readonly");
    $user_type = mysqli_result($usersql, 0, "user_type");
    
    //IF LOCAL USER AND ADMIN USER (No limitation)
    if( $user_type != "1" && $user_right == "1"){
        $userpasswd = mysqli_result($usersql, 0, "user_passwd");
        $password = md5($paramPassword);
        
        //IF match the hashed password
        if($userpasswd == $password)
            $authenticationValid = true;
    }
    
    return $authenticationValid;
}
*/

/*---Custom calls---*/
/*
function getApiKey(){
    global $database_rgmweb;
    $request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();
    
    $authenticationValid = verifyAuthenticationByPassword( $request );
    if( $authenticationValid == TRUE ){
        //ID of the authenticated user
        $paramUsername = $request->get('username');
        $usersql = getUserByUsername( $paramUsername );
        $user_id = mysqli_result($usersql, 0, "user_id");

        error_log("getApiKey() for userid ".$user_id."(".$paramUsername.")\n");
        
        $serverApiKey = genApiKey( $user_id );
        error_log("getApiKey() for userid ".$user_id." key token: ".$serverApiKey."\n");

        // prefix with 2 random digits to avoid collision in case of 2 concurrent cnx
        $sessid = sprintf('%02d%d', mt_rand(1,99), time());
        
        $newsession = sqlrequest( $database_rgmweb, "INSERT INTO `sessions` (session_id, user_id, session_type, session_token) VALUES ('" .
            $sessid . "','" . $user_id . "', '2', '" . $serverApiKey . "');", true);

        $array = array("RGMAPI_KEY" => $serverApiKey);
        $result = getJsonResponse($response, "200", $array);
        echo $result;
    }
    else{
        $array = array("message" => "The username-password credentials of your authentication can not be accepted or the user is not in a group");
        $result = getJsonResponse($response, "401", $array);
        echo $result;
    }  
}
*/
/*
function getAuthenticationStatus(){
	
	$request = \Slim\Slim::getInstance()->request();
    $response = \Slim\Slim::getInstance()->response();

    checkAuthToken
    
    $authenticationValid = verifyAuthenticationByApiKey( $request,"readonly" );    
    if( $authenticationValid == TRUE ){
        $array = array("status" => "authorized");
        $result = getJsonResponse($response, "200", $array);
        echo $result;
    }
    else{
        $array = array("status" => "unauthorized");
        $result = getJsonResponse($response, "401", $array);
        echo $result;
    }
}
*/

?>
