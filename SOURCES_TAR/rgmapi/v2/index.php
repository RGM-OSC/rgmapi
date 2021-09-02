<?php

/**
 * RGMAPI
 * Route calls
 *
 * Copyleft 2021 RGM
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

use Slim\Slim;

// FIXME : temporaire ... peut-être pas d'ailleurs
error_reporting(E_ALL);

include_once('../include/Slim/Slim.php');
include_once('/srv/rgm/rgmweb/include/database.php');
include_once('api_common.class.php');
include_once('ObjectManager.php');

Slim::registerAutoloader();
$app = new Slim();

/**
 *  API routes are defined here (http method, association route, function, privileges)
 */
/*
 * Not rewrited yet : new routes from Vincent
 */
addRoute('post', '/createMultipleObjects', 'createMultipleObjects', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToHost', 'addCustomArgumentsToHost', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToService', 'addCustomArgumentsToService', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToHostTemplate', 'addCustomArgumentsToHostTemplate', ACL_ADMIN);
addRoute('post', '/addCustomArgumentsToServiceTemplate', 'addCustomArgumentsToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addContactNotificationCommandToContact', 'addContactNotificationCommandToContact', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToHostTemplate', 'addCheckCommandParameterToHostTemplate', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceInHost', 'addCheckCommandParameterToServiceInHost', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceTemplate', 'addCheckCommandParameterToServiceTemplate', ACL_ADMIN);
addRoute('post', '/addCheckCommandParameterToServiceInHostTemplate', 'addCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/modifyCheckCommandToHostTemplate', 'modifyCheckCommandToHostTemplate', ACL_ADMIN);
addRoute('post', '/modifyCheckCommandToServiceTemplate', 'modifyCheckCommandToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteHostTemplateToHosts', 'deleteHostTemplateToHosts', ACL_ADMIN);
addRoute('post', '/deleteServiceByHostTemplate', 'deleteServiceByHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToHost', 'deleteCustomArgumentsToHost', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToService', 'deleteCustomArgumentsToService', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToHostTemplate', 'deleteCustomArgumentsToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteCustomArgumentsToServiceTemplate', 'deleteCustomArgumentsToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteContactNotificationCommandToContact', 'deleteContactNotificationCommandToContact', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToHostTemplate', 'deleteCheckCommandParameterToHostTemplate', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceInHost', 'deleteCheckCommandParameterToServiceInHost', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceTemplate', 'deleteCheckCommandParameterToServiceTemplate', ACL_ADMIN);
addRoute('post', '/deleteCheckCommandParameterToServiceInHostTemplate', 'deleteCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);

// RestFul routes
// Nagios routes
addRoute('get', '/nagios/resources', 'getResources', ACL_READONLY);
addRoute('put', '/nagios/resources', 'modifyNagiosResources', ACL_ADMIN);
addRoute('post', '/nagios/export', 'exportConfiguration', ACL_ADMIN);
addRoute('post', '/nagios/eventbroker', 'addEventBroker', ACL_ADMIN);
addRoute('delete', '/nagios/eventbroker', 'delEventBroker', ACL_ADMIN);

// Oneliner routes
addRoute('get', '/oneliner/tags', 'listOneLinersTags', ACL_READONLY);
addRoute('get', '/oneliner/items', 'listOneLinersItems', ACL_READONLY);

// LiveStatus routes
addRoute('get', '/livestatus/nagiosstates', 'listNagiosStates', ACL_READONLY);
addRoute('get', '/livestatus/nagiosobjects', 'listNagiosObjects', ACL_READONLY);
addRoute('get', '/livestatus/nagiosbackends', 'listNagiosBackends', ACL_READONLY);

// Host routes
addRoute('get', '/host', 'getHost', ACL_READONLY);
addRoute('post', '/host', 'createHost', ACL_ADMIN);
addRoute('put', '/host', 'modifyHost', ACL_ADMIN);
addRoute('delete', '/host', 'deleteHost', ACL_ADMIN);
addRoute('post', '/host/group', 'addHostGroupToHost', ACL_ADMIN);
addRoute('delete', '/host/group', 'deleteHostGroupToHost', ACL_ADMIN);
addRoute('post', '/host/contact', 'addContactToHost', ACL_ADMIN);
addRoute('delete', '/host/contact', 'deleteContactToHost', ACL_ADMIN);
addRoute('post', '/host/contact/group', 'addContactGroupToHost', ACL_ADMIN);
addRoute('delete', '/host/contact/group', 'deleteContactGroupToHost', ACL_ADMIN);
addRoute('post', '/host/template', 'addHostTemplateToHost', ACL_ADMIN);
addRoute('get', '/hosts/down', 'getHostsDown', ACL_READONLY);

// Hostgroup routes
addRoute('get', '/hostgroup', 'getHostGroup', ACL_READONLY);
addRoute('post', '/hostgroup', 'createHostGroup', ACL_ADMIN);
addRoute('delete', '/hostgroup', 'deleteHostGroup', ACL_ADMIN);
addRoute('get', '/hostgroup/hosts', 'getHostsByHostGroup', ACL_READONLY);

// Service routes
addRoute('get', '/service', 'getServicesByHost', ACL_READONLY);
addRoute('post', '/service', 'createServiceToHost', ACL_ADMIN);
addRoute('put', '/service', 'modifyServicefromHost', ACL_ADMIN);
addRoute('delete', '/service', 'deleteService', ACL_ADMIN);
addRoute('get', '/services/down', 'getServicesDown', ACL_READONLY);
addRoute('post', '/service/duplicate', 'duplicateService', ACL_ADMIN);
addRoute('post', '/service/contact', 'addContactToServiceInHost', ACL_ADMIN);
addRoute('delete', '/service/contact', 'deleteContactToServiceInHost', ACL_ADMIN);
addRoute('post', '/service/contact/group', 'addContactGroupToServiceInHost', ACL_ADMIN);
addRoute('delete', '/service/contact/group', 'deleteContactGroupToServiceInHost', ACL_ADMIN);
addRoute('get', '/service/group', 'getServiceGroup', ACL_READONLY);
addRoute('post', '/service/group', 'addServiceGroupToServiceInHost', ACL_ADMIN);
addRoute('delete', '/service/group', 'deleteServiceGroupToServiceInHost', ACL_ADMIN);
addRoute('post', '/service/template', 'addServiceTemplateToServiceInHost', ACL_ADMIN);
addRoute('delete', '/service/template', 'deleteServiceTemplateToServiceInHost', ACL_ADMIN);

// Command routes
addRoute('get', '/command', 'getCommand', ACL_READONLY);
addRoute('post', '/command', 'createCommand', ACL_ADMIN);
addRoute('put', '/command', 'modifyCommand', ACL_ADMIN);
addRoute('delete', '/command', 'deleteCommand', ACL_ADMIN);

// Contact routes
addRoute('get', '/contact', 'getContact', ACL_READONLY);
addRoute('post', '/contact', 'createContact', ACL_ADMIN);
addRoute('put', '/contact', 'modifyContact', ACL_ADMIN);
addRoute('delete', '/contact', 'deleteContact', ACL_ADMIN);
addRoute('get', '/contacts/group', 'getContactGroups', ACL_READONLY);
addRoute('delete', '/contacts/group', 'deleteContactGroup', ACL_ADMIN);
addRoute('post', '/contact/group', 'addContactGroupToContact', ACL_ADMIN);
addRoute('delete', '/contact/group', 'deleteContactGroupToContact', ACL_ADMIN);

// Templates routes
addRoute('get', '/template/host', 'getHostTemplate', ACL_READONLY);
addRoute('post', '/template/host', 'createHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host', 'deleteHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/contact', 'addContactToHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/contact', 'deleteContactToHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/contact/group', 'addContactGroupToHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/contact/group', 'deleteContactGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/group', 'addHostGroupToHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/group', 'deleteHostGroupToHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/service', 'createServiceToHostTemplate', ACL_ADMIN);
addRoute('put', '/template/host/service', 'modifyServicefromHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/service/contact', 'addContactToServiceInHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/service/contact', 'deleteContactToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/service/contact/group', 'addContactGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/service/contact/group', 'deleteContactGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/service/group', 'addServiceGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/service/group', 'deleteServiceGroupToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/service/template', 'addServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/service/template', 'deleteServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
addRoute('post', '/template/host/template', 'addInheritanceTemplateToHostTemplate', ACL_ADMIN);
addRoute('delete', '/template/host/template', 'deleteInheritanceTemplateToHostTemplate', ACL_ADMIN);
addRoute('get', '/template/service', 'getServiceTemplate', ACL_READONLY);
addRoute('post', '/template/service', 'createServiceTemplate', ACL_ADMIN);
addRoute('delete', '/template/service', 'deleteServiceTemplate', ACL_ADMIN);
addRoute('post', '/template/service/contact', 'addContactToServiceTemplate', ACL_ADMIN);
addRoute('delete', '/template/service/contact', 'deleteContactToServiceTemplate', ACL_ADMIN);
addRoute('post', '/template/service/contact/group', 'addContactGroupToServiceTemplate', ACL_ADMIN);
addRoute('delete', '/template/service/contact/group', 'deleteContactGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/template/service/group', 'addServiceGroupToServiceTemplate', ACL_ADMIN);
addRoute('delete', '/template/service/group', 'deleteServiceGroupToServiceTemplate', ACL_ADMIN);
addRoute('post', '/template/service/template', 'addInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
addRoute('delete', '/template/service/template', 'deleteInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
addRoute('get', '/template/hosts', 'getHostsBytemplate', ACL_READONLY);
addRoute('get', '/template/services', 'getServicesByHostTemplate', ACL_READONLY);

// User restful
addRoute('post', '/user', 'createUser', ACL_ADMIN);

// Downtimes routes
addRoute('get', '/downtimes', 'getDowntimes', ACL_READONLY);
addRoute('post', '/downtime/host', 'createHostDowntime', ACL_ADMIN);
addRoute('delete', '/downtime/host', 'deleteHostDowntime', ACL_ADMIN);
addRoute('post', '/downtime/service', 'createServiceDowntime', ACL_ADMIN);
addRoute('delete', '/downtime/service', 'deleteServiceDowntime', ACL_ADMIN);

/**
 * getAuthToken and checkAuthToken are *not* wrapped through addRoute()
 * as they handle token stuff an addRoute() assume the token is *already*
 * generated.
 */
$app->get('/getAuthToken', function () {
    RgmApiCommon::getAuthToken();
});
$app->get('/checkAuthToken', function () {
    RgmApiCommon::checkAuthToken();
});

/**
 * @brief   Kind of framework to add routes very easily
 * @details This function registers Slim routes to ObjectManager methods
 * @param string $httpMethod HTTP method (get, post, put, etc.) to use for route registration
 * @param string $routeName the API route name
 * @param string $methodName the callback function to register with this route, implemented on ObjectManager class
 * @param string $acl the ACL the caller must comply with
 * @throws ReflectionException
 */
function addRoute($httpMethod, $routeName, $methodName, $acl)
{
    global $app;

    $app->$httpMethod($routeName, function () use ($methodName, $acl) {
        $request = Slim::getInstance()->request();
        $response = Slim::getInstance()->response();
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
        $className = 'ObjectManager';
        $params = array(array(), array());
        $msg = '';
        $token = RgmApiCommon::getTokenParameter($request);

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
            if ($var = $request->get($param->name)) {
                $params[1][$param->name] = $var;
            }
            // finally, data value takes precedence over all other
            if (isset($body[$param->name])) {
                $params[1][$param->name] = $body[$param->name];
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
                $msg .= 'missing parameter: ' . $key;
            }
        }
        // if unknown or missing parameters found, exit with a http 417 error code
        if ($msg != '') {
            $array = array('message' => $msg);
            $result = RgmApiCommon::getJsonResponse($response, 417, $array);
            echo $result;
            return;
        }

        $tokenInfo = RgmApiCommon::checkAuthTokenValidity($token, $acl);
        if ($tokenInfo['status'] == 'authorized') {
            $authOk = true;
        }
        if ($authOk) {
            $co = new $className($tokenInfo['username']);
            $methodResponse = call_user_func_array(array($co, $methodName), $params[1]);
        }

        RgmApiCommon::constructResponse($response, $methodResponse, $authOk);
    });
}

$app->run();
