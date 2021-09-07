<?php

/**
 *  API routes are defined here (http method, association route, function, privileges)
 */
/*
 * Not rewrited yet : new routes from Vincent
 */
RgmApiCommon::addRoute('POST', '/createMultipleObjects', 'createMultipleObjects', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCustomArgumentsToHost', 'addCustomArgumentsToHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCustomArgumentsToService', 'addCustomArgumentsToService', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCustomArgumentsToHostTemplate', 'addCustomArgumentsToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCustomArgumentsToServiceTemplate', 'addCustomArgumentsToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addContactNotificationCommandToContact', 'addContactNotificationCommandToContact', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCheckCommandParameterToHostTemplate', 'addCheckCommandParameterToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCheckCommandParameterToServiceInHost', 'addCheckCommandParameterToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCheckCommandParameterToServiceTemplate', 'addCheckCommandParameterToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/addCheckCommandParameterToServiceInHostTemplate', 'addCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/modifyCheckCommandToHostTemplate', 'modifyCheckCommandToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/modifyCheckCommandToServiceTemplate', 'modifyCheckCommandToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteServiceByHostTemplate', 'deleteServiceByHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCustomArgumentsToHost', 'deleteCustomArgumentsToHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCustomArgumentsToService', 'deleteCustomArgumentsToService', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCustomArgumentsToHostTemplate', 'deleteCustomArgumentsToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCustomArgumentsToServiceTemplate', 'deleteCustomArgumentsToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteContactNotificationCommandToContact', 'deleteContactNotificationCommandToContact', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCheckCommandParameterToHostTemplate', 'deleteCheckCommandParameterToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCheckCommandParameterToServiceInHost', 'deleteCheckCommandParameterToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCheckCommandParameterToServiceTemplate', 'deleteCheckCommandParameterToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/deleteCheckCommandParameterToServiceInHostTemplate', 'deleteCheckCommandParameterToServiceInHostTemplate', ACL_ADMIN);

// RestFul routes
// Nagios routes
RgmApiCommon::addRoute('GET', '/nagios/resources', 'getResources', ACL_READONLY);
RgmApiCommon::addRoute('PUT', '/nagios/resources', 'modifyNagiosResources', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/nagios/export', 'exportConfiguration', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/nagios/eventbroker', 'addEventBroker', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/nagios/eventbroker', 'delEventBroker', ACL_ADMIN);

// Oneliner routes
RgmApiCommon::addRoute('GET', '/oneliner/tags', 'listOneLinersTags', ACL_READONLY);
RgmApiCommon::addRoute('GET', '/oneliner/items', 'listOneLinersItems', ACL_READONLY);

// LiveStatus routes
RgmApiCommon::addRoute('GET', '/livestatus/nagiosstates', 'listNagiosStates', ACL_READONLY);
RgmApiCommon::addRoute('GET', '/livestatus/nagiosobjects', 'listNagiosObjects', ACL_READONLY);
RgmApiCommon::addRoute('GET', '/livestatus/nagiosbackends', 'listNagiosBackends', ACL_READONLY);

// Host routes
RgmApiCommon::addRoute('GET', '/host', 'getHost', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/host', 'createHost', ACL_ADMIN);
RgmApiCommon::addRoute('PUT', '/host', 'modifyHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/host', 'deleteHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/host/group', 'addHostGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/host/group', 'deleteHostGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/host/contact', 'addContactToHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/host/contact', 'deleteContactToHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/host/contact/group', 'addContactGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/host/contact/group', 'deleteContactGroupToHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/host/template', 'addHostTemplateToHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/host/templates', 'deleteHostTemplateToHost', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/hosts/down', 'getHostsDown', ACL_READONLY);

// Hostgroup routes
RgmApiCommon::addRoute('GET', '/hostgroup', 'getHostGroup', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/hostgroup', 'createHostGroup', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/hostgroup', 'deleteHostGroup', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/hostgroup/hosts', 'getHostsByHostGroup', ACL_READONLY);

// Service routes
RgmApiCommon::addRoute('GET', '/service', 'getServicesByHost', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/service', 'createServiceToHost', ACL_ADMIN);
RgmApiCommon::addRoute('PUT', '/service', 'modifyServicefromHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/service', 'deleteService', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/services/down', 'getServicesDown', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/service/duplicate', 'duplicateService', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/service/contact', 'addContactToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/service/contact', 'deleteContactToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/service/contact/group', 'addContactGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/service/contact/group', 'deleteContactGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/service/group', 'getServiceGroup', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/service/group', 'addServiceGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/service/group', 'deleteServiceGroupToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/service/template', 'addServiceTemplateToServiceInHost', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/service/template', 'deleteServiceTemplateToServiceInHost', ACL_ADMIN);

// Command routes
RgmApiCommon::addRoute('GET', '/command', 'getCommand', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/command', 'createCommand', ACL_ADMIN);
RgmApiCommon::addRoute('PUT', '/command', 'modifyCommand', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/command', 'deleteCommand', ACL_ADMIN);

// Contact routes
RgmApiCommon::addRoute('GET', '/contact', 'getContact', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/contact', 'createContact', ACL_ADMIN);
RgmApiCommon::addRoute('PUT', '/contact', 'modifyContact', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/contact', 'deleteContact', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/contacts/group', 'getContactGroups', ACL_READONLY);
RgmApiCommon::addRoute('DELETE', '/contacts/group', 'deleteContactGroup', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/contact/group', 'addContactGroupToContact', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/contact/group', 'deleteContactGroupToContact', ACL_ADMIN);

// Templates routes
RgmApiCommon::addRoute('GET', '/template/host', 'getHostTemplate', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/template/host', 'createHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host', 'deleteHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/contact', 'addContactToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/contact', 'deleteContactToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/contact/group', 'addContactGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/contact/group', 'deleteContactGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/group', 'addHostGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/group', 'deleteHostGroupToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/service', 'createServiceToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('PUT', '/template/host/service', 'modifyServicefromHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/service/contact', 'addContactToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/service/contact', 'deleteContactToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/service/contact/group', 'addContactGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/service/contact/group', 'deleteContactGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/service/group', 'addServiceGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/service/group', 'deleteServiceGroupToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/service/template', 'addServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/service/template', 'deleteServiceTemplateToServiceInHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/host/template', 'addInheritanceTemplateToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/host/template', 'deleteInheritanceTemplateToHostTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/template/service', 'getServiceTemplate', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/template/service', 'createServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/service', 'deleteServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/service/contact', 'addContactToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/service/contact', 'deleteContactToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/service/contact/group', 'addContactGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/service/contact/group', 'deleteContactGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/service/group', 'addServiceGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/service/group', 'deleteServiceGroupToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/template/service/template', 'addInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/template/service/template', 'deleteInheritServiceTemplateToServiceTemplate', ACL_ADMIN);
RgmApiCommon::addRoute('GET', '/template/hosts', 'getHostsBytemplate', ACL_READONLY);
RgmApiCommon::addRoute('GET', '/template/services', 'getServicesByHostTemplate', ACL_READONLY);

// User restful
RgmApiCommon::addRoute('POST', '/user', 'createUser', ACL_ADMIN);

// Downtimes routes
RgmApiCommon::addRoute('GET', '/downtimes', 'getDowntimes', ACL_READONLY);
RgmApiCommon::addRoute('POST', '/downtime/host', 'createHostDowntime', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/downtime/host', 'deleteHostDowntime', ACL_ADMIN);
RgmApiCommon::addRoute('POST', '/downtime/service', 'createServiceDowntime', ACL_ADMIN);
RgmApiCommon::addRoute('DELETE', '/downtime/service', 'deleteServiceDowntime', ACL_ADMIN);
