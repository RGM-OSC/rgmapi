<?php

global $app;

/**
 *  API routes are defined here (http method, association route, function, privileges)
 */
/*
 * Not rewrited yet : new routes from Vincent
 */
RgmApiCommon::addRoute($app, 'POST', '/createMultipleObjects', 'createMultipleObjects', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCustomArgumentsToHost', 'addCustomArgumentsToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCustomArgumentsToService', 'addCustomArgumentsToService', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCustomArgumentsToHostTemplate', 'addCustomArgumentsToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCustomArgumentsToServiceTemplate', 'addCustomArgumentsToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addContactNotificationCommandToContact', 'addContactNotificationCommandToContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCheckCommandParameterToHostTemplate', 'addCheckCommandParameterToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCheckCommandParameterToServiceInHost', 'addCheckCommandParameterToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCheckCommandParameterToServiceTemplate', 'addCheckCommandParameterToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/addCheckCommandParameterToServiceInHostTemplate', 'addCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/modifyCheckCommandToHostTemplate', 'modifyCheckCommandToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/modifyCheckCommandToServiceTemplate', 'modifyCheckCommandToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteHostTemplateToHosts', 'deleteHostTemplateToHosts', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteServiceByHostTemplate', 'deleteServiceByHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCustomArgumentsToHost', 'deleteCustomArgumentsToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCustomArgumentsToService', 'deleteCustomArgumentsToService', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCustomArgumentsToHostTemplate', 'deleteCustomArgumentsToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCustomArgumentsToServiceTemplate', 'deleteCustomArgumentsToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteContactNotificationCommandToContact', 'deleteContactNotificationCommandToContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCheckCommandParameterToHostTemplate', 'deleteCheckCommandParameterToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCheckCommandParameterToServiceInHost', 'deleteCheckCommandParameterToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCheckCommandParameterToServiceTemplate', 'deleteCheckCommandParameterToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/deleteCheckCommandParameterToServiceInHostTemplate', 'deleteCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);

// RestFul routes
// Nagios routes
RgmApiCommon::addRoute($app, 'GET', '/nagios/resources', 'getResources', ACL_READONLY);
RgmApiCommon::addRoute($app, 'PUT', '/nagios/resources', 'modifyNagiosResources', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/nagios/export', 'exportConfiguration', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/nagios/eventbroker', 'addEventBroker', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/nagios/eventbroker', 'delEventBroker', ACL_ADMIN);

// Oneliner routes
RgmApiCommon::addRoute($app, 'GET', '/oneliner/tags', 'listOneLinersTags', ACL_READONLY);
RgmApiCommon::addRoute($app, 'GET', '/oneliner/items', 'listOneLinersItems', ACL_READONLY);

// LiveStatus routes
RgmApiCommon::addRoute($app, 'GET', '/livestatus/nagiosstates', 'listNagiosStates', ACL_READONLY);
RgmApiCommon::addRoute($app, 'GET', '/livestatus/nagiosobjects', 'listNagiosObjects', ACL_READONLY);
RgmApiCommon::addRoute($app, 'GET', '/livestatus/nagiosbackends', 'listNagiosBackends', ACL_READONLY);

// Host routes
RgmApiCommon::addRoute($app, 'GET', '/host', 'getHost', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/host', 'createHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'PUT', '/host', 'modifyHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/host', 'deleteHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/host/group', 'addHostGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/host/group', 'deleteHostGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/host/contact', 'addContactToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/host/contact', 'deleteContactToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/host/contact/group', 'addContactGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/host/contact/group', 'deleteContactGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/host/template', 'addHostTemplateToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/hosts/down', 'getHostsDown', ACL_READONLY);

// Hostgroup routes
RgmApiCommon::addRoute($app, 'GET', '/hostgroup', 'getHostGroup', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/hostgroup', 'createHostGroup', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/hostgroup', 'deleteHostGroup', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/hostgroup/hosts', 'getHostsByHostGroup', ACL_READONLY);

// Service routes
RgmApiCommon::addRoute($app, 'GET', '/service', 'getServicesByHost', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/service', 'createServiceToHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'PUT', '/service', 'modifyServicefromHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/service', 'deleteService', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/services/down', 'getServicesDown', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/service/duplicate', 'duplicateService', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/service/contact', 'addContactToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/service/contact', 'deleteContactToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/service/contact/group', 'addContactGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/service/contact/group', 'deleteContactGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/service/group', 'getServiceGroup', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/service/group', 'addServiceGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/service/group', 'deleteServiceGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/service/template', 'addServiceTemplateToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/service/template', 'deleteServiceTemplateToServiceInHost', ACL_ADMIN);

// Command routes
RgmApiCommon::addRoute($app, 'GET', '/command', 'getCommand', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/command', 'createCommand', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'PUT', '/command', 'modifyCommand', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/command', 'deleteCommand', ACL_ADMIN);

// Contact routes
RgmApiCommon::addRoute($app, 'GET', '/contact', 'getContact', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/contact', 'createContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'PUT', '/contact', 'modifyContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/contact', 'deleteContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/contacts/group', 'getContactGroups', ACL_READONLY);
RgmApiCommon::addRoute($app, 'DELETE', '/contacts/group', 'deleteContactGroup', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/contact/group', 'addContactGroupToContact', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/contact/group', 'deleteContactGroupToContact', ACL_ADMIN);

// Templates routes
RgmApiCommon::addRoute($app, 'GET', '/template/host', 'getHostTemplate', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/template/host', 'createHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host', 'deleteHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/contact', 'addContactToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/contact', 'deleteContactToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/contact/group', 'addContactGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/contact/group', 'deleteContactGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/group', 'addHostGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/group', 'deleteHostGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/service', 'createServiceToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'PUT', '/template/host/service', 'modifyServicefromHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/service/contact', 'addContactToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/service/contact', 'deleteContactToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/service/contact/group', 'addContactGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/service/contact/group', 'deleteContactGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/service/group', 'addServiceGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/service/group', 'deleteServiceGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/service/template', 'addServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/service/template', 'deleteServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/host/template', 'addInheritanceTemplateToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/host/template', 'deleteInheritanceTemplateToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/template/service', 'getServiceTemplate', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/template/service', 'createServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/service', 'deleteServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/service/contact', 'addContactToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/service/contact', 'deleteContactToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/service/contact/group', 'addContactGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/service/contact/group', 'deleteContactGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/service/group', 'addServiceGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/service/group', 'deleteServiceGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/template/service/template', 'addInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/template/service/template', 'deleteInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'GET', '/template/hosts', 'getHostsBytemplate', ACL_READONLY);
RgmApiCommon::addRoute($app, 'GET', '/template/services', 'getServicesByHostTemplate', ACL_READONLY);

// User restful
RgmApiCommon::addRoute($app, 'POST', '/user', 'createUser', ACL_ADMIN);

// Downtimes routes
RgmApiCommon::addRoute($app, 'GET', '/downtimes', 'getDowntimes', ACL_READONLY);
RgmApiCommon::addRoute($app, 'POST', '/downtime/host', 'createHostDowntime', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/downtime/host', 'deleteHostDowntime', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'POST', '/downtime/service', 'createServiceDowntime', ACL_ADMIN);
RgmApiCommon::addRoute($app, 'DELETE', '/downtime/service', 'deleteServiceDowntime', ACL_ADMIN);
