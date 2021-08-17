<?php
  /*
    RGMAPI
    Route calls

    Copyleft 2018 RGM
    Author: BU DCA Team based on Adrien van den Haak initial work (https://github.com/EyesOfNetworkCommunity/rgmapi)
  */

  const ACL_NOAUTH = 0b00001; // authentication not required (token *not* needed)
  const ACL_READONLY = 0b00010; // action restricted to read-only
  const ACL_1T_TOKEN = 0b00100; // one-time token authorized for this action
  const ACL_1T_NOCLEAR = 0b00100; // don't clear one-time token after call
  const ACL_ADMIN = 0b10000; // action restricted to admin users

  function getUserByUsername($username)
  {
    global $database_rgmweb;

    return sqlrequest(
      $database_rgmweb,
      "SELECT U.user_id as user_id, U.user_name as user_name, U.user_passwd as user_passwd, U.user_type as user_type,
		U.user_limitation as user_limitation, R.tab_1 as readonly, R.tab_2 as operator, R.tab_6 as admin, U.hash_method as hash_method
		FROM users as U left join groups as G on U.group_id = G.group_id left join groupright as R on R.group_id = G.group_id
		WHERE U.user_name = ?",
      false,
      array($username)
    );
  }

  /*---HTTP Response---*/
  function getJsonResponse($response, $code, $array = null)
  {
    global $app;

    // RGM API version is the concatenation on Slim framework version *and* RGM API level revision
    $codeMessage = $response->getMessageForCode($code);
    $arrayHeader = array(
      "slim_version" => \Slim\Slim::VERSION,
      "rgmapi_version" => '1.0',
      "http_code" => $codeMessage
    );
    $arrayMerge = array_merge($arrayHeader, $array);

    $jsonResponse = json_encode($arrayMerge, JSON_PRETTY_PRINT);
    $jsonResponseWithHeader = $jsonResponse;

    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setStatus($codeMessage);

    return $jsonResponseWithHeader;
  }

  function constructResponse($response, $logs, $authenticationValid = false)
  {
    //Only if API keys match
    if ($authenticationValid) {
      try {
        $array = array("result" => $logs);
        $result = getJsonResponse($response, "200", $array);
        echo $result;
      } catch (PDOException $e) {
        $array = array("error" => $e->getMessage());
        $result = getJsonResponse($response, "400", $array);
        echo $result;
      }
    } else {
      $array = array("status" => "unauthorized");
      $result = getJsonResponse($response, "401", $array);
      echo $result;
    }
  }

?>
