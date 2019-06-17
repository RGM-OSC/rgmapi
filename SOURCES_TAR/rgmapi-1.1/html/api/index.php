<?php
/**
 * RGMAPI
 * Route calls
 *
 * Copyleft 2018 RGM
 * Author: BU DCA Team based on Adrien van den Haak initial work (https://github.com/EyesOfNetworkCommunity/eonapi)
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


require "/srv/rgm/rgmapi/include/Slim/Slim.php";
require "/srv/rgm/rgmapi/include/rgmauth.php";
require "/srv/rgm/rgmapi/include/api_functions.php";
require "/srv/rgm/rgmapi/include/ObjectManager.php";

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

/**
 *  API routes are defined here (http method, association route, function, privileges)
 */
addRoute('get', '/getDowntimes', 'getDowntimes', ACL_READONLY);
addRoute('get', '/getHostsDown', 'getHostsDown', ACL_READONLY);
addRoute('get', '/getResources', 'getResources', ACL_READONLY);
addRoute('get', '/getServicesDown', 'getServicesDown', ACL_READONLY);

addRoute('post', '/getHost', 'getHost', ACL_READONLY);
addRoute('post', '/getContact', 'getContact', ACL_READONLY);
addRoute('post', '/getCommand', 'getCommand', ACL_READONLY);
addRoute('post', '/getHostGroup', 'getHostGroup', ACL_READONLY);
addRoute('post', '/getServiceGroup', 'getServiceGroup', ACL_READONLY);
addRoute('post', '/getHostTemplate', 'getHostTemplate', ACL_READONLY);
addRoute('post', '/getContactGroups', 'getContactGroups', ACL_READONLY);
addRoute('post', '/getServicesByHost', 'getServicesByHost', ACL_READONLY);
addRoute('post', '/getServiceTemplate', 'getServiceTemplate', ACL_READONLY);
addRoute('post', '/getHostsBytemplate', 'getHostsBytemplate', ACL_READONLY);
addRoute('post', '/getHostsByHostGroup', 'getHostsByHostGroup', ACL_READONLY);
addRoute('post', '/getServicesByHostTemplate', 'getServicesByHostTemplate', ACL_READONLY);

addRoute('post', '/createUser', 'createUser', ACL_ADMIN);
addRoute('post', '/createHost', 'createHost', ACL_ADMIN | ACL_1T_TOKEN);
addRoute('post', '/createCommand', 'createCommand', ACL_ADMIN);
addRoute('post', '/createContact', 'createContact', ACL_ADMIN);
addRoute('post', '/createHostGroup', 'createHostGroup', ACL_ADMIN);
addRoute('post', '/createHostTemplate', 'createHostTemplate', ACL_ADMIN);
addRoute('post', '/createHostDowntime', 'createHostDowntime', ACL_ADMIN);
addRoute('post', '/createServiceToHost', 'createServiceToHost', ACL_ADMIN);
addRoute('post', '/createMultipleObjects', 'createMultipleObjects', ACL_ADMIN);
addRoute('post', '/createServiceTemplate', 'createServiceTemplate', ACL_ADMIN);
addRoute('post', '/createServiceDowntime', 'createServiceDowntime', ACL_ADMIN);
addRoute('post', '/createServiceToHostTemplate', 'createServiceToHostTemplate', ACL_ADMIN);

addRoute('post', '/addEventBroker', 'addEventBroker', ACL_ADMIN);
addRoute('post', '/addContactToHost', 'addContactToHost', ACL_ADMIN);
addRoute('post', '/addHostGroupToHost', 'addHostGroupToHost', ACL_ADMIN);
addRoute('post', '/addContactGroupToHost', 'addContactGroupToHost', ACL_ADMIN);
addRoute('post', '/addHostTemplateToHost', 'addHostTemplateToHost', ACL_ADMIN);
addRoute('post', '/addContactGroupToContact', 'addContactGroupToContact', ACL_ADMIN);
addRoute('post', '/addContactToHostTemplate', 'addContactToHostTemplate', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToHost', 'addCustomArgumentsToHost', ACL_ADMIN);
addRoute('post', '/addContactToServiceInHost', 'addContactToServiceInHost', ACL_ADMIN);
addRoute('post', '/addHostGroupToHostTemplate', 'addHostGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToService', 'addCustomArgumentsToService', ACL_ADMIN);
addRoute('post', '/addContactToServiceTemplate', 'addContactToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addContactGroupToHostTemplate', 'addContactGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/addContactGroupToServiceInHost', 'addContactGroupToServiceInHost', ACL_ADMIN);
addRoute('post', '/addServiceGroupToServiceInHost', 'addServiceGroupToServiceInHost', ACL_ADMIN);
addRoute('post', '/addContactGroupToServiceTemplate', 'addContactGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToHostTemplate', 'addCustomArgumentsToHostTemplate', ACL_ADMIN);
addRoute('post', '/addServiceGroupToServiceTemplate', 'addServiceGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addContactToServiceInHostTemplate', 'addContactToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/addServiceTemplateToServiceInHost', 'addServiceTemplateToServiceInHost', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToServiceTemplate', 'addCustomArgumentsToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addInheritanceTemplateToHostTemplate', 'addInheritanceTemplateToHostTemplate', ACL_ADMIN);
addRoute('post', '/addContactNotificationCommandToContact', 'addContactNotificationCommandToContact', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToHostTemplate', 'addCheckCommandParameterToHostTemplate', ACL_ADMIN);
addRoute('post', '/addContactGroupToServiceInHostTemplate', 'addContactGroupToServiceInHostTempalte', ACL_ADMIN);
addRoute('post', '/addServiceGroupToServiceInHostTemplate', 'addServiceGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceInHost', 'addCheckCommandParameterToServiceInHost', ACL_ADMIN);
addRoute('post', '/addServiceTemplateToServiceInHostTemplate', 'addServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceTemplate', 'addCheckCommandParameterToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addInheritServiceTemplateToServiceTemplate', 'addInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceInHostTemplate', 'addCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);

addRoute('post', '/modifyHost', 'modifyHost', ACL_ADMIN);
addRoute('post', '/modifyContact', 'modifyContact', ACL_ADMIN);
addRoute('post', '/modifyCommand', 'modifyCommand', ACL_ADMIN);
addRoute('post', '/modifyServicefromHost', 'modifyServicefromHost', ACL_ADMIN);
addRoute('post', '/modifyNagiosResources', 'modifyNagiosResources', ACL_ADMIN);
addRoute('post', '/modifyServicefromHostTemplate', 'modifyServicefromHostTemplate', ACL_ADMIN);
addRoute('post', '/modifyCheckCommandToHostTemplate', 'modifyCheckCommandToHostTemplate', ACL_ADMIN);
addRoute('post', '/modifyCheckCommandToServiceTemplate', 'modifyCheckCommandToServiceTemplate', ACL_ADMIN);

addRoute('post', '/deleteHost', 'deleteHost', ACL_ADMIN);
addRoute('post', '/deleteContact', 'deleteContact', ACL_ADMIN);
addRoute('post', '/deleteService', 'deleteService', ACL_ADMIN);
addRoute('post', '/deleteCommand', 'deleteCommand', ACL_ADMIN);
addRoute('post', '/delEventBroker', 'delEventBroker', ACL_ADMIN);
addRoute('post', '/deleteHostGroup', 'deleteHostGroup', ACL_ADMIN);
addRoute('post', '/deleteContactGroup', 'deleteContactGroup', ACL_ADMIN);
addRoute('post', '/deleteHostTemplate', 'deleteHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteHostDowntime', 'deleteHostDowntime', ACL_ADMIN);
addRoute('post', '/deleteContactToHost', 'deleteContactToHost', ACL_ADMIN);
addRoute('post', '/deleteServiceTemplate', 'deleteServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteHostGroupToHost', 'deleteHostGroupToHost', ACL_ADMIN);
addRoute('post', '/deleteServiceDowntime', 'deleteServiceDowntime', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToHost', 'deleteContactGroupToHost', ACL_ADMIN);
addRoute('post', '/deleteHostTemplateToHosts', 'deleteHostTemplateToHosts', ACL_ADMIN);
addRoute('post', '/deleteServiceByHostTemplate', 'deleteServiceByHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToContact', 'deleteContactGroupToContact', ACL_ADMIN);
addRoute('post', '/deleteContactToHostTemplate', 'deleteContactToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToHost', 'deleteCustomArgumentsToHost', ACL_ADMIN);
addRoute('post', '/deleteContactToServiceInHost', 'deleteContactToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteHostGroupToHostTemplate', 'deleteHostGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactToServiceTemplate', 'deleteContactToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToService', 'deleteCustomArgumentsToService', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToHostTemplate', 'deleteContactGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToServiceInHost', 'deleteContactGroupToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteServiceGroupToServiceInHost', 'deleteServiceGroupToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToServiceTemplate', 'deleteContactGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteServiceGroupToServiceTemplate', 'deleteServiceGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToHostTemplate', 'deleteCustomArgumentsToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteServiceTemplateToServiceInHost', 'deleteServiceTemplateToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteContactToServiceInHostTemplate', 'deleteContactToServiceInHostTempalte', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToServiceTemplate', 'deleteCustomArgumentsToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteInheritanceTemplateToHostTemplate', 'deleteInheritanceTemplateToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactGroupToServiceInHostTemplate', 'deleteContactGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteServiceGroupToServiceInHostTemplate', 'deleteServiceGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactNotificationCommandToContact', 'deleteContactNotificationCommandToContact', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToHostTemplate', 'deleteCheckCommandParameterToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceInHost', 'deleteCheckCommandParameterToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceTemplate', 'deleteCheckCommandParameterToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteServiceTemplateToServiceInHostTemplate', 'deleteServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteInheritServiceTemplateToServiceTemplate', 'deleteInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceInHostTemplate', 'deleteCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);

addRoute('post', '/duplicateService', 'duplicateService', ACL_ADMIN);
addRoute('post', '/exportConfiguration', 'exportConfiguration', ACL_ADMIN);



addRoute('post', '/listNagiosStates', 'listNagiosStates', ACL_READONLY);
addRoute('post', '/listNagiosObjects', 'listNagiosObjects', ACL_READONLY);
addRoute('post', '/listNagiosBackends', 'listNagiosBackends', ACL_READONLY);

addRoute('get', '/listOneLinersTags', 'listOneLinersTags', ACL_READONLY);
addRoute('get', '/listOneLinersItems', 'listOneLinersItems', ACL_READONLY);

/**
 * getAuthToken and checkAuthToken are *not* wrapped through addRoute()
 * as they handle token stuff an addRoute() assume the token is *already*
 * generated.
 */
$app->get('/getAuthToken', 'getAuthToken');
$app->get('/checkAuthToken', 'checkAuthToken');


/**
 * @brief   Kind of framework to add routes very easily
 * @details This function registers Slim routes to ObjectManager methods
 * @param   $httpMethod HTTP method (get, post, put, etc.) to use for route registration
 * @param   $routeName the API route name
 * @param   $methodName the callback function to register with this route, implemented on ObjectManager class
 * @param   $acl the ACL the caller must comply with
 */
function addRoute($httpMethod, $routeName, $methodName, $acl) {
	
    global $app;
    
    $app->$httpMethod($routeName, function() use ($methodName, $acl) {
		
        $request = \Slim\Slim::getInstance()->request();
        $response = \Slim\Slim::getInstance()->response();
        $body = json_decode($request->getBody(), true);
        if (($err = json_last_error()) != 0) {
            $array = array("error" => 'JSON Error');
            switch ($err) {
                case JSON_ERROR_DEPTH: $array['error'] = 'JSON Error: JSON_ERROR_DEPTH'; break;
                case JSON_ERROR_STATE_MISMATCH: $array['error'] = 'JSON Error: JSON_ERROR_STATE_MISMATCH'; break;
                case JSON_ERROR_CTRL_CHAR: $array['error'] = 'JSON Error: JSON_ERROR_CTRL_CHAR'; break;
                case JSON_ERROR_SYNTAX: $array['error'] = 'JSON Error: JSON_ERROR_SYNTAX'; break;
                case JSON_ERROR_UTF8: $array['error'] = 'JSON Error: JSON_ERROR_UTF8'; break;
                case JSON_ERROR_RECURSION: $array['error'] = 'JSON Error: JSON_ERROR_RECURSION'; break;
                case JSON_ERROR_INF_OR_NAN: $array['error'] = 'JSON Error: JSON_ERROR_INF_OR_NAN'; break;
                case JSON_ERROR_UNSUPPORTED_TYPE: $array['error'] = 'JSON Error: JSON_ERROR_UNSUPPORTED_TYPE'; break;
                case JSON_ERROR_INVALID_PROPERTY_NAME: $array['error'] = 'JSON Error: JSON_ERROR_INVALID_PROPERTY_NAME'; break;
                case JSON_ERROR_UTF16: $array['error'] = 'JSON Error: JSON_ERROR_UTF16'; break;
            }
            $result = getJsonResponse($response, "406", $array);
            echo $result;
            return;
        }
        $authOk = false;
        $logs = "";
        $className = 'ObjectManager';
        $params = array(array(), array());
        $msg = '';
        $token = getTokenParameter($request, $body);

        // retrieve routed function parameters, ensure all parameters are provided
        // (or have an acceptable default value in function)
        $reflector = new ReflectionMethod($className, $methodName);
        foreach ($reflector->getParameters() as $param) {
            $params[0][] = $param->name;
            $params[1][$param->name] = null;
            // first, set default value, if exists
            if ($param->isDefaultValueAvailable()) {
                $params[1][$param->name] = $param->getDefaultValue();
            }
            //second, header value takes precedence over default value
            if ($header = $request->headers->get($param->name)) {
                $params[1][$param->name] = $header;
            }
            //third, parameter value takes precedence over header value
            if ( $var = $request->get($param->name)) {
                $params[1][$param->name] = $var;
            }
            // finally, data value takes precedence over all other
            if (isset($body["$param->name"])) {
                $params[1][$param->name] = $body["$param->name"];
            }
        }
        if (isset($params['token'])) {
            unset($params['token']);
        }
        // ensure passed parameters are required by routed function
        foreach ($request->get() as $key => $value) {
            if (in_array( $key, $params[0]) == false) {
                if ($msg != '') $msg .= ', ';
                $msg .= 'unknown parameter: ' . $key;
            }
        }
        // ensure function have all requested parameters filled
        $keys = array_keys($params[1]);
        foreach ($params[0] as $fnparam) {
            if (in_array( $fnparam, $keys) == false) {
                if ($msg != '') $msg .= ', ';
                $msg .= 'missing parameter: ' . $key;
            }
        }
        // if unknown or missing parameters found, exit with a http 417 error code
        if ($msg != '') {
            $array = array("message" => $msg);
            $result = getJsonResponse($response, "417", $array);
            echo $result;
            return;
        }

        if ($acl & ACL_NOAUTH) {
            $authOk = true;
        } else {
            $tokenInfo = checkAuthTokenValidity($request, $token);
            if ($tokenInfo['status'] == 'authorized') {
                $authOk = true;
            }
        }
        if ($authOk) {
            $co = new $className($tokenInfo['username']);
            $logs = call_user_func_array(array($co, $methodName), $params[1]);
            if ( ! $acl && ACL_1T_NOCLEAR) {
                clearOneTimeToken($token);
            }
        }

        constructResponse( $response, $logs, $authOk );
    });
}

$app->run();

?>
