<?php

/**
 * RGMAPI - RGM Objects class
 *
 * Copyright (c) 2017 AXIANS C&S
 * Author: Adrien van den Haak <adrien.vandenhaak@axians.com>
 *
 * Copyright (c) 2017 AXIANS Cloud Builder
 * Author: Jean-Philippe Levy <jean-philippe.levy@axians.com>
 * Contributor: Hoarau Jeremy <jeremy.hoarau@axians.com>
 *
 * Copyright (c) 2019 RGM Team
 * Michael Aubertin <maubertin@fr.scc.com>
 * Eric Belhomme <ebelhomme@fr.scc.com>
 * Alex Rocher <arocher@fr.scc.com>
 */

include_once('/srv/rgm/rgmweb/include/livestatus/Client.php');
include_once('/srv/rgm/lilac/includes/config.inc');
include_once('/srv/rgm/rgmweb/module/monitoring_ged/ged_functions.php');

use Nagios\Livestatus\Client;

/**
 * @brief  A class that encapsulates all RGM API calls functions
 */
class RgmApiMethods
{
    private $authUser;

    /**
     * @param string $username
     */
    function __construct($username)
    {
        /**
         * The username is filled during object instanciation
         */
        $this->authUser = $username;
    }

    /**
     * @brief  log function
     */
    private function getLogs($error, $success)
    {
        $logs = $error . $success;
        $countLogs = substr_count($logs, "\n");

        if ($countLogs > 1) {
            $logs = str_replace("\n", " | ", $logs);
        } else {
            $logs = str_replace("\n", "", $logs);
        }

        return rtrim($logs, " | ");
    }

    /**
     * @brief  Exports Lilac configuration to Nagios config file
     * @param string &$error a string reference to notify on error
     * @param string &$success a string reference to notify on success
     * @param string $jobName the export job name as stored into Lilac export table
     * @throws PropelException
     */
    private function exportConfigurationToNagios(&$error = "", &$success = "", $jobName = "Incremental Nagios Export")
    {
        $c = new Criteria();
        $exportJobs = ExportJobPeer::doSelect($c);

        $nagiosJobId = NULL;
        foreach ($exportJobs as $job) {
            if ($job->getName() == $jobName) {
                $nagiosJobId = $job->getId();
                break;
            }
        }

        if ($nagiosJobId != NULL) {
            $exportJob = ExportJobPeer::retrieveByPK($nagiosJobId);
            $exportJob->setStatusCode(ExportJob::STATUS_STARTING);
            $exportJob->setStartTime(time());
            $exportJob->setStatus("Starting...");
            $exportJob->save();
            exec("php /srv/rgm/lilac/exporter/export.php " . $exportJob->getId() . " > /dev/null 2>&1", $tempOutput, $retVal);

            $success .= $jobName . " : Nagios configuration exported\n";
        } else {
            $error .= "ERROR during nagios configuration export\n";
        }
    }

    /**
     * @brief  check a LiveStatus socket status
     * @param string $type socket type (unix or TCP)
     * @param string $address target IP address
     * @param integer $port target TCP port
     * @param string $path unix socket path
     * @return  resource|boolean A file handle on the opened socket, or **false** if the connection failed
     */
    private function checkLiveStatusSocket($type, $address, $port, $path)
    {
        if ($type == "unix") {
            $socket_path_connexion = "unix://" . $path;
            $host = fsockopen($socket_path_connexion, $port, $errno, $errstr, 5);
        } else {
            $host = fsockopen($address, $port, $errno, $errstr, 5);
        }
        return $host;
    }

    /**
     * @brief  a generic function to retrieve various Lilac objects information
     * @param object $objectClass a Lilac class object
     * @param string $objectName a specific name of the object name we want to retrieve
     * @return array|string An array containing the object requested *or* a list of all objects of $objectClass
     */
    private function getNagiosObject($objectClass, $objectName)
    {
        if (!$objectName) {
            $crit = new Criteria();
            $crit->addAscendingOrderByColumn($objectClass::NAME);
            $result = array();
            $lst = $objectClass::doSelect($crit);
            foreach ($lst as $obj) {
                array_push($result, $obj->toArray());
            }
            return $result;
        } else {
            $obj = new $objectClass;
            // Find host contact
            $result = $obj->getByName($objectName);
            if (!$result) {
                return "Object $objectName doesn't exist\n";
            } else {
                return $result->toArray();
            }
        }
    }

    /**
     * @brief  returns a Lilac Host object for referenced hostname
     * @param string $hostName the Lilac HostName object to retrieve
     * @return array An array that describes the hostname
     */
    public function getHost($hostName)
    {
        return $this->getNagiosObject(new NagiosHostPeer(), $hostName);
    }

    /**
     * @brief  get HostTemplate details
     * @param string $templateHostName the HostTemplate name to look for
     * @return  array An array that describes the HostTemplate
     */
    public function getHostTemplate($templateHostName)
    {
        return $this->getNagiosObject(new NagiosHostTemplatePeer(), $templateHostName);
    }

    /**
     * @brief  get details for a specified Lilac HostGroup name
     * @param string $hostGroupName the HostGroup name to describe
     * @return  array An array of HostGroup details
     */
    public function getHostGroup($hostGroupName)
    {
        return $this->getNagiosObject(new NagiosHostgroupPeer(), $hostGroupName);
    }

    /**
     * @brief  get Lilac Contact Names
     * @details  returns a Lilac ContactName, or a **list** of ContactName
     * @param string|boolean $contactName the Lilac ContactName to search, or **false** to
     *      list all ContactName (defaults to false)
     * @return  array a ContactName detail *or* a list of ContactName
     */
    public function getContact($contactName = FALSE)
    {
        return $this->getNagiosObject(new NagiosContactPeer(), $contactName);
    }

    /**
     * @brief  get Lilac Contact Groups
     * @details  returns a Lilac ContactGroup, or a **list** of ContactGroup
     * @param string $contactGroupName the Lilac ContactGroup to search, or **false** to
     *      list all ContactName (defaults to false)
     * @return  array a ContactGroup detail *or* a list of ContactGroup
     */
    public function getContactGroups($contactGroupName = FALSE)
    {
        return $this->getNagiosObject(new NagiosContactGroupPeer(), $contactGroupName);
    }

    /**
     * @brief  get Lilac Commands
     * @details  returns a Lilac Command, or a **list** of Commands
     * @param string $commandName the Lilac Command to search, or **false** to
     *      list all Commands (defaults to false)
     * @return  array a Command detail *or* a list of Commands
     */
    public function getCommand($commandName)
    {
        return $this->getNagiosObject(new NagiosCommandPeer(), $commandName);
    }

    /**
     * @brief  get Lilac ServiceTemplate
     * @details  returns a Lilac ServiceTemplate, or a **list** of ServiceTemplate
     * @param string $templateName the Lilac ServiceTemplate to search, or **false** to
     *      list all ServiceTemplate (defaults to false)
     * @return  array a ServiceTemplate detail *or* a list of ServiceTemplate
     */
    public function getServiceTemplate($templateName)
    {
        return $this->getNagiosObject(new NagiosServiceTemplatePeer(), $templateName);
    }

    /**
     * @brief  get Lilac ServiceGroup
     * @details  returns a Lilac ServiceGroup, or a **list** of groups
     * @param string $serviceGroupName the Lilac ServiceGroup to search, or **false** to
     *      list all ServiceGroup (defaults to false)
     * @return  array a ServiceGroup detail *or* a list of ServiceGroup
     */
    public function getServiceGroup($serviceGroupName)
    {
        return $this->getNagiosObject(new NagiosServiceGroupPeer(), $serviceGroupName);
    }

    /**
     * @brief  get a host list that belongs to specified Lilac HostTemplate
     * @param string $templateHostName The HostTemplate name to look for
     * @return array|string a list of hostnames
     */
    public function getHostsBytemplate($templateHostName)
    {
        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            return "Host Template $templateHostName not found\n";
        } else {
            $hostList = $template_host->getAffectedHosts();
            if (!$hostList) {
                return "No Host found for the template : $templateHostName \n";
            } else {
                $result = array();
                foreach ($hostList as $host) {
                    array_push($result, $host->toArray());
                }
                return $result;
            }
        }
    }

    /**
     * @brief  get a host list that belongs to specified Lilac HostGroup
     * @param string $hostGroupName The HostGroup name to look for
     * @return array|string a list of hostnames
     */
    public function getHostsByHostGroup($hostGroupName)
    {
        $nhgp = new NagiosHostgroupPeer;
        //Find HostGroup
        $hostGroup = $nhgp::getByName($hostGroupName);
        if (!$hostGroup) {
            return "HostGroup named $hostGroupName not found\n";
        } else {
            $hostList = $hostGroup->getMembers();
            if (!$hostList) {
                return "No Host found for the HostGroup: $hostGroupName \n";
            } else {
                $result = array();
                foreach ($hostList as $host) {
                    array_push($result, $host->toArray());
                }
                return $result;
            }
        }
    }

    /**
     * @brief  get all services attached to a specified Lilac Host
     * @param string $hostName The Host name to query
     * @return  array|string An array containing a list of services attached to host
     * @throws PropelException
     */
    public function getServicesByHost($hostName)
    {
        $nhp = new NagiosHostPeer();
        $host = $nhp::getByName($hostName);
        if (!$host) {
            return "No host named $hostName.\n";
        } else {
            $c = new Criteria();
            $c->add(NagiosServicePeer::HOST, $host->getId());
            $c->addAscendingOrderByColumn(NagiosServicePeer::ID);
            $services = NagiosServicePeer::doSelect($c);
            $result = array();
            foreach ($services as $service) {
                $answer = $service->toArray();
                $answer["parameters"] = $service->getNagiosServiceCheckCommandParameters()->toArray();
                array_push($result, $answer);
            }
            return $result;
        }
    }

    /**
     * @brief  get all services attached to a specified Lilac HostTemplate
     * @param string $templateHostName The HostTemplate to query
     * @return  array|string An array containing a list of services attached to HostTemplate
     * @throws PropelException
     */
    public function getServicesByHostTemplate($templateHostName)
    {
        $nhtp = new NagiosHostTemplatePeer();
        $templateHost = $nhtp::getByName($templateHostName);
        if (!$templateHost) {
            return "No hostTemplate named $templateHostName.\n";
        } else {
            $c = new Criteria();
            $c->add(NagiosServicePeer::HOST_TEMPLATE, $templateHost->getId());
            $c->addAscendingOrderByColumn(NagiosServicePeer::DESCRIPTION);
            $services = NagiosServicePeer::doSelect($c);
            $result = array();
            foreach ($services as $service) {
                $answer = $service->toArray();
                $answer["parameters"] = $service->getNagiosServiceCheckCommandParameters()->toArray();
                array_push($result, $answer);
            }
            return $result;
        }
    }

    /**
     * @brief  Retrieve Nagios Downtimes from LiveStatus
     * @return  Array an array containing a list of active downtimes
     */
    public function getDowntimes()
    {
        $downtime = array();
        $tab = array("author", "comment", "duration", "end_time", "entry_time", "fixed", "id", "is_service", "triggered_by", "type", "start_time");
        $tabDate = array("end_time", "entry_time", "start_time");
        foreach ($this->listNagiosObjects("downtimes", NULL, $tab)["default"] as $downtimes) {
            foreach ($downtimes as $k => $down) {
                if (in_array($k, $tabDate)) {
                    $ta["human_" . $k] = gmdate("Y-m-d\TH:i:s\Z", $down);
                }
                $ta[$k] = $down;
            }
            array_push($downtime, $ta);
        }
        return $downtime;
    }

    /**
     * @brief  Retrieve Nagios hosts currently down from LiveStatus
     * @return  array An array containing a list of down hosts
     */
    public function getHostDown()
    {
        $HostsDown = array();
        $tabColumns = array("id", "name", "address", "services_with_state", "last_state_change",
            "acknowledged", "acknowledged_type", "comment", "comments_with_info", "notifications_enabled");
        $tabFilters = array("state = 1");
        $tabDate = array("last_state_change");
        $tabConcat = array("comments_with_info");

        $dateT = array();
        foreach ($this->listNagiosObjects("hosts", NULL, $tabColumns, $tabFilters)["default"] as $hd) {
            foreach ($hd as $k => $hdown) {
                if (in_array($k, $tabDate)) {
                    $ta["human_" . $k] = gmdate("Y-m-d\TH:i:s\Z", $hdown);
                    $dateT = ($k == "last_state_change" ? array("last_state_change" => $hdown) : NULL);
                } elseif (in_array($k, $tabConcat)) {
                    $concat = "";
                    if (sizeof($hd[$k]) > 0) {
                        for ($i = 0; $i <= sizeof($hd[$k]) - 1; $i++) {
                            if (sizeof($hd[$k][$i]) > 0) {
                                for ($j = 0; $j <= sizeof($hd[$k][$i]) - 1; $j++) {
                                    $concat .= $hd[$k][$i][$j] . " | ";
                                }
                            }
                            $concat .= (sizeof($hd[$k][$i]) > 1 ? NULL : "|");
                        }
                    }
                    $ta["human_" . $k] = $concat;
                    $ta["date"] = time();
                    $ta["human_date"] = gmdate("Y-m-d\TH:i:s\Z", $ta["date"]);
                    if (isset($dateT["last_state_change"])) {
                        $date1 = new DateTime();
                        $date2 = new DateTime();
                        $date2->setTimestamp($dateT["last_state_change"]);
                        $interval = $date2->diff($date1);
                        $ta["human_duration"] = $interval->format('%ad %hh %im %ss ');
                    }
                }
                $ta[$k] = $hdown;
            }
            array_push($HostsDown, $ta);
        }
        return ($HostsDown);
    }

    /**
     * @brief  Retrieve Nagios services currently down from LiveStatus
     * @return  array An array containing a list of down services
     */
    /* RGMWEB-LIVESTATUS - Get Services Down*/
    public function getServicesDown()
    {
        $ServiceDown = array();
        $tabColumns = array("id", "host_name", "host_address", "display_name", "acknowledged",
            "acknowledged_type", "comment", "comments_with_info", "last_state_change");
        $tabFilters = array("state > 0", "host_state = 0", "state_type = 1");
        $tabDate = array("last_state_change");
        $tabConcat = array("comments_with_info");

        $dateT = array();
        foreach ($this->listNagiosObjects("services", NULL, $tabColumns, $tabFilters)["default"] as $sd) {
            foreach ($sd as $k => $sdown) {
                if (in_array($k, $tabDate)) {
                    $ta["human_" . $k] = gmdate("Y-m-d\TH:i:s\Z", $sdown);
                    $dateT = ($k == "last_state_change" ? array("last_state_change" => $sdown) : NULL);
                } elseif (in_array($k, $tabConcat)) {
                    $concat = "";
                    if (sizeof($sd[$k]) > 0) {
                        for ($i = 0; $i <= sizeof($sd[$k]) - 1; $i++) {
                            if (sizeof($sd[$k][$i]) > 0) {
                                for ($j = 0; $j <= sizeof($sd[$k][$i]) - 1; $j++) {
                                    $concat .= $sd[$k][$i][$j] . " | ";
                                }
                            }
                            $concat .= (sizeof($sd[$k][$i]) > 1 ? NULL : "|");
                        }
                    }
                    $ta["human_" . $k] = $concat;
                    $ta["date"] = time();
                    $ta["human_date"] = gmdate("Y-m-d\TH:i:s\Z", $ta["date"]);
                    if (isset($dateT["last_state_change"])) {
                        $date1 = new DateTime();
                        $date2 = new DateTime();
                        $date2->setTimestamp($dateT["last_state_change"]);
                        $interval = $date2->diff($date1);
                        $ta["human_duration"] = $interval->format('%ad %hh %im %ss ');
                    }
                }
                $ta[$k] = $sdown;
            }
            array_push($ServiceDown, $ta);
        }
        return $ServiceDown;
    }

    /**
     * @brief  Retrieve Nagios "user macros" from Lilac
     * @return  array An array containing a the user macros
     */
    public function getResources()
    {
        $error = "";
        $success = "";
        try {
            $resourceCfg = NagiosResourcePeer::doSelectOne(new Criteria());
            if (!$resourceCfg) {
                $code = 1;
                $error .= "No resources initialize.";
            } else {
                return $resourceCfg->toArray();
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= "An exception occured : $e";
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac ContactName object
     * @param string $contactName a valid Lilac ContactName. Must be unique
     * @param string $contactAlias a string  that contains the contact desciption. Defaults to  @b "description"
     * @param string $contactMail a string  that contains the contact email address. Can't be **null**
     * @param string $contactPager a string  that contains the contact *pager* number. Defaults to @b "" (empty string)
     * @param string $contactGroup a valid Lilac ContactGroup object. Defaults to @b "" (empty string)
     * @param string $serviceNotificationCommand a valid Lilac Command objet used as command for Nagios service notifier. Default to  @b "rgm_service_notifier"
     * @param string $hostNotificationCommand a valid Lilac Command objet used as command for Nagios host notifier. Default to  @b "rgm_host_notifier"
     * @param string $options a list array of optional flags. currently supported flags are:
     *      | flag |
     *      |------|
     *      | host_notification_period  |
     *      | service_notification_period  |
     *      | host_notification_options_down  |
     *      | host_notification_options_flapping  |
     *      | host_notification_options_recovery  |
     *      | host_notification_options_scheduled_downtime  |
     *      | host_notification_options_unreachable  |
     *      | service_notification_options_critical  |
     *      | service_notification_options_flapping  |
     *      | service_notification_options_recovery  |
     *      | service_notification_options_unknown  |
     *      | service_notification_options_warning  |
     *      | can_submit_commands  |
     *      | retain_status_information  |
     *      | retain_nonstatus_information  |
     *      | host_notifications_enabled  |
     *      | service_notifications_enabled  |
     * @param  boolean s$exportConfiguration force the execution of Lilac exporter after Contact creation. Defaults to **FALSE**
     * @return  array An array with the return status of the call
     * @throws PropelException
     */
    public function createContact($contactName, $contactAlias, $contactMail,
                                  $contactPager = "", $contactGroup = "", $serviceNotificationCommand = "rgm_service_notifier",
                                  $hostNotificationCommand = "rgm_host_notifier", $options = NULL,
                                  $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $ncp = new NagiosContactPeer;
        // Find host
        $contact = $ncp->getByName($contactName);
        if ($contact) {
            $code = 1;
            $error .= "$contactName already exists\n";
        } else {
            $tempContact = new NagiosContact();
            try {
                $tempContact->setName($contactName);
                $tempContact->setAlias($contactAlias);
                $tempContact->setEmail($contactMail);
                $tempContact->setPager($contactPager);
                $tempContact->addServiceNotificationCommandByName($serviceNotificationCommand);
                $tempContact->addHostNotificationCommandByName($hostNotificationCommand);
                if ($options) {
                    if (array_key_exists('host_notification_period', $options)) {
                        $tempContact->setHostNotificationPeriodByName(strval($options->host_notification_period));
                    }
                    if (array_key_exists('service_notification_period', $options)) {
                        $tempContact->setServiceNotificationPeriodByName(strval($options->service_notification_period));
                    }
                    if (!array_key_exists('host_notification_options_down', $options)) {
                        $tempContact->setHostNotificationOnDown(0);
                    } else {
                        $tempContact->setHostNotificationOnDown(intval($options->host_notification_options_down));
                    }
                    if (!array_key_exists('host_notification_options_flapping', $options)) {
                        $tempContact->setHostNotificationOnFlapping(0);
                    } else {
                        $tempContact->setHostNotificationOnFlapping($options->host_notification_options_flapping);
                    }
                    if (!array_key_exists('host_notification_options_recovery', $options)) {
                        $tempContact->setHostNotificationOnRecovery(0);
                    } else {
                        $tempContact->setHostNotificationOnRecovery($options->host_notification_options_recovery);
                    }
                    if (!array_key_exists('host_notification_options_scheduled_downtime', $options)) {
                        $tempContact->setHostNotificationOnScheduledDowntime(0);
                    } else {
                        $tempContact->setHostNotificationOnScheduledDowntime($options->host_notification_options_scheduled_downtime);
                    }
                    if (!array_key_exists('host_notification_options_unreachable', $options)) {
                        $tempContact->setHostNotificationOnUnreachable(0);
                    } else {
                        $tempContact->setHostNotificationOnUnreachable($options->host_notification_options_unreachable);
                    }
                    if (!array_key_exists('service_notification_options_critical', $options)) {
                        $tempContact->setServiceNotificationOnCritical(0);
                    } else {
                        $tempContact->setServiceNotificationOnCritical($options->service_notification_options_critical);
                    }
                    if (!array_key_exists('service_notification_options_flapping', $options)) {
                        $tempContact->setServiceNotificationOnFlapping(0);
                    } else {
                        $tempContact->setServiceNotificationOnFlapping($options->service_notification_options_flapping);
                    }
                    if (!array_key_exists('service_notification_options_recovery', $options)) {
                        $tempContact->setServiceNotificationOnRecovery(0);
                    } else {
                        $tempContact->setServiceNotificationOnRecovery($options->service_notification_options_recovery);
                    }
                    if (!array_key_exists('service_notification_options_unknown', $options)) {
                        $tempContact->setServiceNotificationOnUnknown(0);
                    } else {
                        $tempContact->setServiceNotificationOnUnknown($options->service_notification_options_unknown);
                    }
                    if (!array_key_exists('service_notification_options_warning', $options)) {
                        $tempContact->setServiceNotificationOnWarning(0);
                    } else {
                        $tempContact->setServiceNotificationOnWarning($options->service_notification_options_warning);
                    }
                    if (!array_key_exists('can_submit_commands', $options)) {
                        $tempContact->setCanSubmitCommands(0);
                    } else {
                        $tempContact->setCanSubmitCommands($options->can_submit_commands);
                    }
                    if (!array_key_exists('retain_status_information', $options)) {
                        $tempContact->setRetainStatusInformation(0);
                    } else {
                        $tempContact->setRetainStatusInformation($options->retain_status_information);
                    }
                    if (!array_key_exists('retain_nonstatus_information', $options)) {
                        $tempContact->setRetainNonstatusInformation(0);
                    } else {
                        $tempContact->setRetainNonstatusInformation($options->retain_nonstatus_information);
                    }
                    if (!array_key_exists('host_notifications_enabled', $options)) {
                        $tempContact->setHostNotificationsEnabled(0);
                    } else {
                        $tempContact->setHostNotificationsEnabled($options->host_notifications_enabled);
                    }
                    if (!array_key_exists('service_notifications_enabled', $options)) {
                        $tempContact->setServiceNotificationsEnabled(0);
                    } else {
                        $tempContact->setServiceNotificationsEnabled($options->service_notifications_enabled);
                    }
                }
                $tempContact->save();
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage();
            }

            if (!empty($contactGroup)) {
                $ncg = (new NagiosContactGroupPeer)->getByName($contactGroup);
                if ($ncg) {
                    $contactGroupMember = new NagiosContactGroupMember();
                    $contactGroupMember->setContact($tempContact->getId());
                    $contactGroupMember->setContactgroup($ncg->getId());
                    $contactGroupMember->save();
                }
            }
            $success .= "contact had been created.";

            if ($exportConfiguration) {
                $this->exportConfigurationToNagios($error, $success);
            }
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Nagios Downtime object on selected Lilac host
     * @param string $hostName a string containing Lilac HostName. Can't be **null**
     * @param string $comment a string that describes a comment for putting the host down. Can't be **null**
     * @param string $startTime a string that represents the starting date for the downtime. It must
     *      follow a date representation supported by the PHP DateTime class.
     *      see. https://www.php.net/manual/fr/class.datetime.php
     *      Can't be **null**
     * @param string $endTime a string that represents the ending date for the downtime. It must
     *      follow a date representation supported by the PHP DateTime class.
     *      see. https://www.php.net/manual/fr/class.datetime.php
     *      Can't be **null**
     * @param string $user Lilac ContactName responsible for this downtime. Defaults to RGMAPI session user
     * @param integer $fixed defaults to **1**
     * @param integer $duration defaults to **1000**
     * @param boolean $childHostAction defaults to **FALSE**
     */
    public function createHostDowntime($hostName, $comment, $startTime, $endTime, $user = '',
                                       $fixed = 1, $duration = 1000, $childHostAction = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        if (empty($user)) {
            $user = $this->authUser;
        }
        try {
            $CommandFile = "/srv/rgm/nagios/var/log/rw/nagios.cmd";
            $date_start = new DateTime($startTime);
            $start = $date_start->getTimestamp();
            $date_end = new DateTime($endTime);
            $end = $date_end->getTimestamp();
            //$success .= $date_end->format('d-m-Y H:i:s');
            $date = new DateTime();
            $timestamp = $date->getTimestamp();
            if (NagiosHostPeer::getByName($hostName)) {
                if (!$childHostAction) {
                    $cmdline = '[' . $timestamp . '] SCHEDULE_HOST_DOWNTIME;' . $hostName . ';' . $start . ';' . $end . ';' . $fixed . ';0;' . $duration . ';' . $user . ';' . $comment . PHP_EOL;
                    print "no child - cmd: $cmdline";
                    file_put_contents($CommandFile, $cmdline, FILE_APPEND);
                } else {
                    $cmdline = '[' . $timestamp . '] SCHEDULE_AND_PROPAGATE_HOST_DOWNTIME;' . $hostName . ';' . $start . ';' . $end . ';' . $fixed . ';0;' . $duration . ';' . $user . ';' . $comment . PHP_EOL;
                    print "with child - cmd: $cmdline";
                    file_put_contents($CommandFile, $cmdline, FILE_APPEND);
                }
                $downtimesList = $this->getDowntimes();
                $x = 0;
                $verify = False;
                while ($x < count($downtimesList) && !$verify) {
                    if (strval($timestamp) == strval($downtimesList[$x]["entry_time"])) {
                        $verify = True;
                        $success .= "Schedule host downtimes succesfully save. ref: $timestamp";
                    }
                    $x++;
                }
                if (!$verify) {
                    $code = 1;
                    $error .= "An error occurred nothing happen.";
                }
            } else {
                $code = 1;
                $error .= "$hostName didn't exist.";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Nagios Downtime object on selected Lilac host
     * @param string $hostName a string containing Lilac HostName. Can't be **null**
     * @param string $serviceName a string containing Lilac ServiceName. Can't be **null**
     * @param string $comment a string that describes a comment for putting the host down. Can't be **null**
     * @param string $startTime a string that represents the starting date for the downtime. It must
     *      follow a date representation supported by the PHP DateTime class.
     *      see. https://www.php.net/manual/fr/class.datetime.php
     *      Can't be **null**
     * @param string $endTime a string that represents the ending date for the downtime. It must
     *      follow a date representation supported by the PHP DateTime class.
     *      see. https://www.php.net/manual/fr/class.datetime.php
     *      Can't be **null**
     * @param string $user Lilac ContactName responsible for this downtime. Defaults to RGMAPI session user
     * @param integer $fixed defaults to **1**
     * @param integer $duration defaults to **1000**
     */
    public function createServiceDowntime($hostName, $serviceName, $comment, $startTime,
                                          $endTime, $user = '', $fixed = 1, $duration = 1000)
    {
        $error = "";
        $success = "";
        $code = 0;
        if (empty($user)) {
            $user = $this->authUser;
        }
        try {
            $service = '';
            $CommandFile = "/srv/rgm/nagios/var/log/rw/nagios.cmd";
            $date_start = new DateTime($startTime);
            $start = $date_start->getTimestamp();
            $date_end = new DateTime($endTime);
            $end = $date_end->getTimestamp();
            $date = new DateTime();
            $timestamp = $date->getTimestamp();
            $arrSvcCol = array('description', 'host_name');
            $arrSvcFilter = array("host_name = $hostName", "description = $serviceName");
            $jsonServices = $this->listNagiosObjects('services', 0, $arrSvcCol, $arrSvcFilter);
            foreach ($jsonServices['default'] as $svcJsonData) {
                $service = $svcJsonData['description'];
            }
            if (!$service) {
                $code = 1;
                $error .= "$hostName and/or $serviceName didn't exist.";
            } else {
                $cmdline = '[' . $timestamp . '] SCHEDULE_SVC_DOWNTIME;' . $hostName . ';' . $serviceName . ';' . $start . ';' . $end . ';' . $fixed . ';0;' . $duration . ';' . $user . ';' . $comment . '' . PHP_EOL;
                file_put_contents($CommandFile, $cmdline, FILE_APPEND);
                $x = 0;
                $y = 0;
                $downtimesList = $this->getDowntimes();
                // Wait downtime creation return 2.5 seconds max
                while (count($downtimesList) < 1 && $y < 5) {
                    $downtimesList = $this->getDowntimes();
                    $y++;
                    usleep(500000); // Wait 0.5 seconds to loop
                }
                $verify = False;
                while ($x < count($downtimesList) && !$verify) {
                    if (strval($timestamp) >= strval($downtimesList[$x]["entry_time"]) && strval($timestamp) <= (strval($downtimesList[$x]["entry_time"]) + 3)) {
                        $verify = True;
                        $success .= "Schedule service downtimes succesfully save. ref: $timestamp";
                    }
                    $x++;
                }
                if (!$verify) {
                    $code = 1;
                    $error .= "An error occurred nothing happen.";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac Host object
     * @param string $templateHostName a string of a valid Lilac HostTemplate object. Default to @b "GENERIC_HOST"
     * @param string $hostName a string of a *unique* hostname. Can't be **null**.
     * @param string $hostIp a string of the IP address of the host. Can be a CIDR IP
     *      address (**x.x.x.x**) *or* a FQDN hostname (**host.domain**)
     * @param string $hostAlias a string containing a *alias* or a *description* of the host. Defaults to @b "" (empty string)
     * @param string $contactName an optional Lilac ContactName associated with this host. Defaults to **NULL**
     * @param string $contactGroupName = an optional Lilac ContactGroupName associated with this host. Defaults to **NULL**
     * @param boolean $exportConfiguration force the execution of Lilac exporter after Contact creation. Defaults to **FALSE**
     * @return  array An array with the return status of the call
     */
    public function createHost($templateHostName, $hostName, $hostIp,
                               $hostAlias = "", $contactName = NULL, $contactGroupName = NULL,
                               $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        $nhp = new NagiosHostPeer;
        // Find host
        $host = $nhp::getByName($hostName);
        if ($host) {
            $code = 1;
            $error .= "Host $hostName already exists\n";
        }
        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $code = 1;
            $error .= "Host Template $templateHostName not found\n";
        }
        // Lauch actions if no errors
        if (empty($error)) {
            try {
                // host
                $tempHost = new NagiosHost();
                $tempHost->setName($hostName);
                $tempHost->setAlias($hostAlias);
                $tempHost->setDisplayName($hostName);
                $tempHost->setAddress($hostIp);
                $tempHost->save();
                $success .= "Host $hostName added\n";

                // host-template
                $newInheritance = new NagiosHostTemplateInheritance();
                $newInheritance->setNagiosHost($tempHost);
                $newInheritance->setNagiosHostTemplateRelatedByTargetTemplate($template_host);
                $newInheritance->save();
                $success .= "Host Template " . $templateHostName . " added to host " . $hostName . "\n";

                if ($contactName != NULL) {
                    //Add a contact to a host
                    $code = $this->addContactToHost($tempHost->getName(), $contactName)["code"];
                }
                if ($contactGroupName != NULL && $code == 0) {
                    //Add a contact group to a host
                    $code = $this->addContactGroupToHost($tempHost->getName(), $contactGroupName)["code"];
                }
                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage() . "\n";
            }
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac HostTemplate object
     * @param string $templateHostName a string of a *unique* HostTemplate name. Can't be **null**.
     * @param string $templateHostDescription a string for a HostTemplate description. Defaults to @ "" (empty string)
     * @param boolean $createHostgroup defaults to TRUE
     * @param boolean $exportConfiguration force the execution of Lilac exporter after Contact creation. Defaults to **FALSE**
     * @todo why the hell create a hostgroup with the name of the host template ??? should be smarter to specify a hostgroup name (and createit if it doesn't exists...)
     * @throws PropelException
     * @throws PropelException
     */
    /* LILAC - Create Host Template */
    public function createHostTemplate($templateHostName, $templateHostDescription = "",
                                       $createHostgroup = TRUE, $exportConfiguration = FALSE)
    {
        global $lilac;
        $error = "";
        $success = "";
        $code = 0;

        // Check for pre-existing host template with same name
        $nhtp = new NagiosHostTemplatePeer;
        $template_host = $nhtp::getByName($templateHostName);
        if ($template_host) {
            $code = 1;
            $error .= "A host template with that name already exists!\n";
        }
        if ($templateHostName == NULL || $templateHostName == "") {
            $code = 1;
            $error .= "A host template name must be defined\n";
        }
        if (empty($error)) {
            // Create template
            $template = new NagiosHostTemplate();
            $template->setName($templateHostName);
            $template->setDescription($templateHostDescription);
            $template->save();
            $success .= "Host template " . $templateHostName . " created\n";
            // Add host template inheritance ("GENERIC_HOST")
            $targetTemplate = $nhtp::getByName("GENERIC_HOST");
            if (!$targetTemplate) {
                $code = 1;
                $error .= "The target template 'GENERIC_HOST' does not exit\n";
            } else {
                $newInheritance = new NagiosHostTemplateInheritance();
                $newInheritance->setNagiosHostTemplateRelatedBySourceTemplate($template);
                $newInheritance->setNagiosHostTemplateRelatedByTargetTemplate($targetTemplate);
                try {
                    $newInheritance->save();
                    $success .= "Template 'GENERIC_HOST' added to inheritance chain\n";
                } catch (Exception $e) {
                    $code = 1;
                    $error .= $e->getMessage();
                }
            }
            if ($createHostgroup && empty($error)) {
                // Create Host Group with Host Template name if not exists
                if ($lilac->hostgroup_exists($templateHostName)) {
                    $nhgp = new NagiosHostgroupPeer;
                    $hostGroup = $nhgp::getByName($templateHostName);
                } else {
                    $hostGroup = $this->createHostGroup($templateHostName, $error, $success);
                    // $hostGroup = $nhgp->getByName( $templateHostName );
                }
                // Add Group Membership to Host template
                if ($hostGroup != NULL) {
                    $template->addHostgroupByName($templateHostName);
                    $success .= "Host group membership added to " . $templateHostName . "\n";
                }
            }
        }
        // Export
        if ($exportConfiguration) {
            $this->exportConfigurationToNagios($error, $success);
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac HostGroup object
     * @param string $hostGroupName a string of a *unique* HostGroup name. Can't be **null**.
     * @param string $description a string description for HostGroup. Defaults to @b "host group"
     * @param boolean $exportConfiguration force the execution of Lilac exporter after Contact creation. Defaults to **FALSE**
     * @throws PropelException
     * @throws PropelException
     */
    public function createHostGroup($hostGroupName, $description = "host group", $exportConfiguration = FALSE)
    {
        global $lilac;
        $error = "";
        $success = "";
        $code = 0;

        // Check for pre-existing contact with same name
        if ($lilac->hostgroup_exists($hostGroupName)) {
            $code = 1;
            $error .= "A host group with that name already exists!\n";
        } else {
            // Field Error Checking
            if ($hostGroupName == "") {
                $error .= "Host group name is required\n";
            } else {
                // All is well for error checking, add the hostgroup into the db.
                $hostGroup = new NagiosHostgroup();
                $hostGroup->setAlias($description);
                $hostGroup->setName($hostGroupName);
                $hostGroup->save();

                $success .= "Host group " . $hostGroupName . " created\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            }
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac ServiceTemplate object
     * @param string $templateServiceName a string of a *unique* ServiceTemplate name. Can't be **null**.
     * @param string $templateDescription a string description for ServiceTemplate. Defaults to @b "" (empty string)
     * @param boolean $exportConfiguration force the execution of Lilac exporter after Contact creation. Defaults to **FALSE**
     * @throws PropelException
     * @throws PropelException
     */
    public function createServiceTemplate($templateServiceName, $templateDescription = "", $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        if (NagiosServiceTemplatePeer::getByName($templateServiceName)) {
            $code = 1;
            $error .= "The Service template '" . $templateServiceName . "' already exist.\n";
        } else {
            $nst = new NagiosServiceTemplate;
            $nst->setName($templateServiceName);
            $nst->setDescription($templateDescription);
            $nst->save();
            $success .= "The Service template $templateServiceName had been created.\n";
            // Export
            if ($exportConfiguration) {
                $this->exportConfigurationToNagios($error, $success);
            }
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac Command object
     * @param string $commandName a string of a *unique* ServiceTemplate name. Can't be **null**.
     * @param string $commandLine a string description for ServiceTemplate. Defaults to @b "" (empty string)
     * @param string $commandDescription
     */
    public function createCommand($commandName, $commandLine, $commandDescription = "")
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $ncp = new NagiosCommandPeer;
            $targetCommand = $ncp->getByName($commandName);

            //If command doesn't exist we create it
            if (!$targetCommand) {
                $command = new NagiosCommand;
                $command->setName($commandName);
                $command->setLine($commandLine);
                $command->setDescription($commandDescription);
                $result = $command->save();
                if (!$result) {
                    $code = 1;
                    $error .= "The command '" . $command->getName() . "' can't be created\n";
                } else {
                    $success .= "The command '" . $command->getName() . "' has been created.\n";
                }
            } //if command already exist we modify it
            else {
                $code = 1;
                $error .= "The command '" . $targetCommand->getName() . "' already exist, if you want to modify it see the function 'modifyCommand'.\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /**
     * @brief  Create a Lilac Contact (user) object
     * @param string $userName
     * @param string $userMail
     * @param bool $admin
     * @param string $filterName
     * @param string $filterValue
     * @param bool $exportConfiguration
     * @return string
     * @throws PropelException
     */
    public function createUser($userName, $userMail, $admin = false, $filterName = "", $filterValue = "", $exportConfiguration = FALSE)
    {
        //Lower case
        $userName = strtolower($userName);

        $success = "";
        $error = "";
        $userGroup = 0;
        //Local user
        $userType = 0;
        $userPassword1 = $userName;
        $userPassword2 = $userName;
        $message = false;

        if ($admin) {
            //admins group
            $userGroup = 1;
            $userDescr = "admin user";
            $createdUserLimitation = 0;
        } else {
            $userDescr = "limited user";
            $createdUserLimitation = 1;
        }
        // RGMWEB - User creation
        $user = insert_user($userName, $userDescr, $userGroup, $userPassword1, $userPassword2, $userType, "", $userMail, $createdUserLimitation, $message);

        if ($user) {
            $success .= "User $userName created\n";
        } else {
            $error .= "Unable to create user $userName\n";
        }

        // RGMWEB - XML Filter creation
        $xml_file = "/srv/rgm/rgmweb/cache/" . $userName . "-ged.xml";
        $dom = openXml();
        $root = $dom->createElement("ged");
        $root = $dom->appendChild($root);

        $default = $dom->createElement("default");
        $default = $root->appendChild($default);

        //GED filters for non admin users
        if (!$admin) {
            $default = $root->getElementsByTagName("default")->item(0);
            $default->appendChild($dom->createTextNode($userName));

            $filters = $dom->createElement("filters");
            $filters = $root->appendChild($filters);
            $filters->setAttribute("name", $userName);
            $filter = $dom->createElement("filter");
            $filter = $filters->appendChild($filter);
            $filter->setAttribute("name", $filterName);
            $filter->appendChild($dom->createTextNode($filterValue));
        }

        $dom->save($xml_file);
        $xml = $dom->saveXML();

        $fp = @fopen($xml_file, "w+");

        if (fwrite($fp, $xml)) {
            $success .= "Events filters file $xml_file is created\n";
        } else {
            $error .= "Unable to create xml file\n";
        }

        fclose($fp);

        chown($xml_file, "apache");
        chgrp($xml_file, "apache");

        // Export
        if ($exportConfiguration) {
            $this->exportConfigurationToNagios($error, $success);
        }

        return $this->getLogs($error, $success);
    }

    public function createMutipleHosts($hosts, $exportConfiguration = false)
    {
        foreach ($hosts as $host) {
            $hostAlias = (!isset($host->hostAlias) ? NULL : $host->hostAlias);
            $contactName = (!isset($host->contactName) ? $contactName = NULL : $host->contactName);
            $contactGroupName = (!isset($host->contactGroupName) ? $contactGroupName = NULL : $host->contactGroupName);
            $this->createHost($host->$templateHostName, $host->hostName, $host->hostIp, $hostAlias, $contactName, $contactGroupName, $exportConfiguration);
        }
    }

    /* LILAC - Global add function */
    public function createMultipleObjects($hosts = array(), $hostTemplates = array(), $hostGroups = array(), $commands = array(), $serviceTemplates = array(), $exportConfiguration = FALSE)
    {
        $error = "";
        $succes = "";

        foreach ($hosts as $obj) {
            $hostAlias = (!isset($obj->hostAlias) ? NULL : $obj->hostAlias);
            $contactName = (!isset($obj->contactName) ? $contactName = NULL : $obj->contactName);
            $contactGroupName = (!isset($obj->contactGroupName) ? $contactGroupName = NULL : $obj->contactGroupName);
            $result = $this->createHost($obj->$templateHostName, $obj->hostName, $obj->hostIp, $hostAlias, $contactName, $contactGroupName, $exportConfiguration);
            if ($result["code"] > 0) {
                $error .= " | " . $result["description"];
            } else {
                $succes .= " | " . $result["description"];
            }
        }

        foreach ($hostTemplates as $obj) {
            $templateHostDescription = (!isset($obj->templateHostDescription) ? NULL : $obj->templateHostDescription);
            $createHostgroup = (!isset($obj->createHostgroup) ? NULL : $obj->createHostgroup);
            $result = $this->createHostTemplate($obj->templateHostName, $obj->templateHostDescription, $obj->createHostgroup, $exportConfiguration);
            if ($result["code"] > 0) {
                $error .= " | " . $result["description"];
            } else {
                $succes .= " | " . $result["description"];
            }
        }

        foreach ($hostGroups as $obj) {
            $result = $this->createHostGroup($obj->hostGroupName, $exportConfiguration);
            if ($result["code"] > 0) {
                $error .= " | " . $result["description"];
            } else {
                $succes .= " | " . $result["description"];
            }
        }

        foreach ($commands as $obj) {
            $commandDescription = (!isset($obj->commandDescription) ? NULL : $obj->commandDescription);
            $result = $this->createCommand($obj->commandName, $obj->commandLine, $commandDescription);
            if ($result["code"] > 0) {
                $error .= " | " . $result["description"];
            } else {
                $succes .= " | " . $result["description"];
            }
        }
        foreach ($serviceTemplates as $obj) {
            $templatesToInherit = (!isset($obj->templatesToInherit) ? NULL : $obj->templatesToInherit);
            $templateDescription = (!isset($obj->templateDescription) ? NULL : $obj->templateDescription);
            $servicesGroup = (!isset($obj->servicesGroup) ? NULL : $obj->servicesGroup);
            $contacts = (!isset($obj->contacts) ? NULL : $obj->contacts);
            $contactsGroup = (!isset($obj->contactsGroup) ? NULL : $obj->contactsGroup);
            $checkCommandParameters = (!isset($obj->checkCommandParameters) ? NULL : $obj->checkCommandParameters);
            $result = $this->createServiceTemplate($obj->templateName, $templateDescription);
            if ($result["code"] > 0) {
                $error .= " | " . $result["description"];
            } else {
                $succes .= " | " . $result["description"];
            }
        }

        return $this->getLogs($error, $succes);
    }

    /* LILAC - Add Custom Argument to a host */
    public function addCustomArgumentsToHost($hostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhp = new NagiosHostPeer;
        $host = $nhp::getByName($hostName);
        // Find host
        if (!$host) {
            $error .= "Host :  $hostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosHostCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosHostCustomObjectVarPeer::HOST, $host->getId());
                $nhcov = NagiosHostCustomObjectVarPeer::doSelectOne($c);

                if (!$nhcov) {
                    $param = new NagiosHostCustomObjectVar();
                    $param->setNagiosHost($host);
                    $param->setVarName($key);
                    $param->setVarValue($value);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$hostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$hostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Custom Argument to a host Template*/
    public function addCustomArgumentsToHostTemplate($templateHostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhtp = new NagiosHostTemplatePeer;
        $templateHost = $nhtp::getByName($templateHostName);
        // Find host
        if (!$templateHost) {
            $error .= "Template Host :  $templateHostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosHostCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosHostCustomObjectVarPeer::HOST_TEMPLATE, $templateHost->getId());
                $nhcov = NagiosHostCustomObjectVarPeer::doSelectOne($c);

                if (!$nhcov) {
                    $param = new NagiosHostCustomObjectVar();
                    $param->setNagiosHostTemplate($templateHost);
                    $param->setVarName($key);
                    $param->setVarValue($value);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateHostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateHostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Custom Argument to a Service Template*/
    public function addCustomArgumentsToServiceTemplate($templateServiceName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nstp = new NagiosServiceTemplatePeer;
        $templateService = $nstp::getByName($templateServiceName);
        // Find host
        if (!$templateService) {
            $error .= "Template Service:  $templateServiceName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosServiceCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosServiceCustomObjectVarPeer::SERVICE_TEMPLATE, $templateService->getId());
                $nscov = NagiosServiceCustomObjectVarPeer::doSelectOne($c);

                if (!$nscov) {
                    $param = new NagiosServiceCustomObjectVar();
                    $param->setNagiosServiceTemplate($templateService);
                    $param->setVarName($key);
                    $param->setVarValue($value);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateServiceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateServiceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Custom Argument to a Service */
    public function addCustomArgumentsToService($serviceName, $hostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nsp = new NagiosServicePeer;
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        // Find host
        if (!$service) {
            $error .= "Service:  $serviceName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosServiceCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosServiceCustomObjectVarPeer::SERVICE, $service->getId());
                $nscov = NagiosServiceCustomObjectVarPeer::doSelectOne($c);

                if (!$nscov) {
                    $param = new NagiosServiceCustomObjectVar();
                    $param->setNagiosService($service);
                    $param->setVarName($key);
                    $param->setVarValue($value);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$serviceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$serviceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Contact to Host */
    public function addContactToHost($hostName, $contactName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $ncp = new NagiosContactPeer;
        // Find host contact
        $tempContact = $ncp->getByName($contactName);
        if (!$tempContact) {
            $error .= "Contact $contactName not found\n";
        }

        $nhp = new NagiosHostPeer;
        $host = $nhp::getByName($hostName);
        if (!$host) {
            $error .= "Host Template $hostName not found\n";
        }


        if (empty($error)) {
            $c = new Criteria();
            $c->add(NagiosHostContactMemberPeer::HOST, $host->getId());
            $c->add(NagiosHostContactMemberPeer::CONTACT, $tempContact->getId());
            $membership = NagiosHostContactMemberPeer::doSelectOne($c);
            if ($membership) {
                $code = 1;
                $error .= "That contact already exists in that list!\n";
            } else {
                $membership = new NagiosHostContactMember();
                $membership->setHost($host->getId());
                $membership->setNagiosContact($tempContact);
                $membership->save();
                $hostName = $host->getName();
                $success .= "Contact $contactName added to host $hostName\n";
                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add command Parameter to a service Template */
    public function addCheckCommandParameterToServiceTemplate($templateServiceName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nstp = new NagiosServiceTemplatePeer();
        // Find host template

        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {
            //We prepared the list of existing parameters in the service
            $tempListParam = [];
            $c = new Criteria();
            $c->add(NagiosServiceCheckCommandParameterPeer::TEMPLATE, $template_service->getId());
            $c->addAscendingOrderByColumn(NagiosServiceCheckCommandParameterPeer::ID);

            $parameter_list = NagiosServiceCheckCommandParameterPeer::doSelect($c);
            foreach ($parameter_list as $paramObject) {
                array_push($tempListParam, $paramObject->getParameter());
            }
            foreach ($parameters as $paramName) {
                if (!in_array($paramName, $tempListParam)) {
                    $param = new NagiosServiceCheckCommandParameter();
                    $param->setNagiosServiceTemplate($template_service);
                    $param->setParameter($paramName);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateServiceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateServiceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add command Parameter to a service  */
    public function addCheckCommandParameterToServiceInHost($serviceName, $hostName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing parameters in the service
            $tempListParam = [];
            $c = new Criteria();
            $c->add(NagiosServiceCheckCommandParameterPeer::SERVICE, $service->getId());
            $c->addAscendingOrderByColumn(NagiosServiceCheckCommandParameterPeer::ID);

            $parameter_list = NagiosServiceCheckCommandParameterPeer::doSelect($c);
            foreach ($parameter_list as $paramObject) {
                array_push($tempListParam, $paramObject->getParameter());
            }
            foreach ($parameters as $paramName) {

                if (!in_array($paramName, $tempListParam)) {
                    $param = new NagiosServiceCheckCommandParameter();
                    $param->setNagiosService($service);
                    $param->setParameter($paramName);
                    $param->save();
                    $changed++;
                }
            }
        }


        if ($changed > 0) {
            $success .= "$serviceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$serviceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add command Parameter to a service in a host template  */
    public function addCheckCommandParameterToServiceInHostTemplate($serviceName, $hostTemplateName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing parameters in the service
            $tempListParam = [];
            $c = new Criteria();
            $c->add(NagiosServiceCheckCommandParameterPeer::SERVICE, $service->getId());
            $c->addAscendingOrderByColumn(NagiosServiceCheckCommandParameterPeer::ID);

            $parameter_list = NagiosServiceCheckCommandParameterPeer::doSelect($c);
            foreach ($parameter_list as $paramObject) {
                array_push($tempListParam, $paramObject->getParameter());
            }
            foreach ($parameters as $paramName) {

                if (!in_array($paramName, $tempListParam)) {
                    $param = new NagiosServiceCheckCommandParameter();
                    $param->setNagiosService($service);
                    $param->setParameter($paramName);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$serviceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$serviceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add command Parameter to a Host Template */
    public function addCheckCommandParameterToHostTemplate($templateHostName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhtp = new NagiosHostTemplatePeer();
        // Find host template

        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {
            //We prepared the list of existing parameters in the host
            $tempListParam = [];
            $c = new Criteria();
            $c->add(NagiosHostCheckCommandParameterPeer::HOST_TEMPLATE, $template_host->getId());
            $c->addAscendingOrderByColumn(NagiosHostCheckCommandParameterPeer::ID);

            $parameter_list = NagiosHostCheckCommandParameterPeer::doSelect($c);
            foreach ($parameter_list as $paramObject) {
                array_push($tempListParam, $paramObject->getParameter());
            }
            foreach ($parameters as $paramName) {

                if (!in_array($paramName, $tempListParam)) {
                    $param = new NagiosHostCheckCommandParameter();
                    $param->setNagiosHostTemplate($template_host);
                    $param->setParameter($paramName);
                    $param->save();
                    $changed++;
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateHostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateHostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Contact Group to Host */
    public function addContactGroupToHost($hostName, $contactGroupName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhp = new NagiosHostPeer;
        $host = $nhp::getByName($hostName);

        if (!$host) {
            $error .= "Host $hostName not found\n";
        }

        $ncgp = new NagiosContactGroupPeer;
        // Find host group contact
        $tempContactGroup = $ncgp->getByName($contactGroupName);
        if (!$tempContactGroup) {
            $error .= "Contact group $contactGroupName not found\n";
        }

        // Lauch actions if no errors
        if (empty($error)) {
            //Add a contact group to a host
            $c = new Criteria();
            $c->add(NagiosHostContactgroupPeer::HOST, $host->getId());
            $c->add(NagiosHostContactgroupPeer::CONTACTGROUP, $tempContactGroup->getId());
            $membership = NagiosHostContactgroupPeer::doSelectOne($c);

            //Test if contact group doesn't already exist
            if ($membership) {
                $code = 1;
                $error .= "That contact group already exists in that list!\n";
            } else {
                $membership = new NagiosHostContactgroup();
                $membership->setHost($host->getId());
                $membership->setNagiosContactGroup($tempContactGroup);
                $membership->save();
                $hostName = $host->getName();
                $success .= "Contact group $contactGroupName added to host $hostName\n";
            }

            if ($exportConfiguration) {
                $this->exportConfigurationToNagios($error, $success);
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Template to Host */
    public function addHostTemplateToHost($templateHostName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhp = new NagiosHostPeer;
        $host = $nhp::getByName($hostName);

        if (!$host) {
            $error .= "Host $hostName not found\n";
        }

        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        // We need to get the count of templates already inherited
        if ($host) {
            $templateList = $host->getNagiosHostTemplateInheritances();
            foreach ($templateList as $tempTemplate) {
                if ($tempTemplate->getId() == $template_host->getId()) {
                    $error .= "That template already exists in the inheritance chain\n";
                }
            }
        }

        if (empty($error)) {
            $newInheritance = new NagiosHostTemplateInheritance();
            $newInheritance->setNagiosHost($host);
            $newInheritance->setNagiosHostTemplateRelatedByTargetTemplate($template_host);
            $newInheritance->setOrder(count($templateList));
            try {
                $newInheritance->save();
                $success .= "Host template " . $templateHostName . " added to " . $hostName . "\n";

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage();
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Contact to Host Template */
    public function addContactToHostTemplate($contactName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $ncp = new NagiosContactPeer;
        // Find host contact
        $tempContact = $ncp->getByName($contactName);
        if (!$tempContact) {
            $error .= "Contact $contactName not found\n";
        }

        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {
            $c = new Criteria();
            $c->add(NagiosHostContactMemberPeer::TEMPLATE, $template_host->getId());
            $c->add(NagiosHostContactMemberPeer::CONTACT, $tempContact->getId());
            $membership = NagiosHostContactMemberPeer::doSelectOne($c);
            if ($membership) {
                $code = 1;
                $error .= "That contact already exists in that list!\n";
            } else {
                $membership = new NagiosHostContactMember();
                $membership->setTemplate($template_host->getId());
                $membership->setNagiosContact($tempContact);
                $membership->save();
                $success .= "Contact " . $contactName . " added to host template " . $templateHostName . "\n";

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Contact Group to Host Template */
    public function addContactGroupToHostTemplate($contactGroupName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $ncgp = new NagiosContactGroupPeer;
        $tempContactGroup = $ncgp->getByName($contactGroupName);
        if (!$tempContactGroup) {
            $error .= "Contact group $contactGroupName not found\n";
        }

        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {
            $c = new Criteria();
            $c->add(NagiosHostContactgroupPeer::HOST_TEMPLATE, $template_host->getId());
            $c->add(NagiosHostContactgroupPeer::CONTACTGROUP, $tempContactGroup->getId());
            $membership = NagiosHostContactgroupPeer::doSelectOne($c);
            if ($membership) {
                $code = 1;
                $error .= "That contact group already exists in that list!\n";
            } else {
                $membership = new NagiosHostContactgroup();
                $membership->setHostTemplate($template_host->getId());
                $membership->setNagiosContactgroup($tempContactGroup);
                $membership->save();
                $success .= "Contact group " . $contactGroupName . " added to host template " . $templateHostName . "\n";

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - create Service to Host template*/
    public function createServiceToHostTemplate($hostTemplateName, $service, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosHostTemplatePeer;
        $template = $nsp::getByName($hostTemplateName);

        if (!$template) {
            $code = 1;
            $error .= "template $hostTemplateName doesn't exist\n";
        }

        $nstp = new NagiosServiceTemplatePeer;

        //Test if the parent templates exist
        if (isset($service->inheritance)) {
            $templateName = $service->inheritance;
            $serviceTemplate = $nstp::getByName($templateName);
            if (!$serviceTemplate) {
                $code = 1;
                $error .= "Service Template $templateName not found\n";
            }
        }

        if (empty($error)) {
            try {
                // service interface
                $tempService = new NagiosService();
                $tempService->setDescription($service->name);
                $tempService->setHostTemplate($template->getId());
                $tempService->save();
                $success .= "Service $service->name added\n";
                if (isset($serviceTemplate)) {
                    $newInheritance = new NagiosServiceTemplateInheritance();
                    $newInheritance->setNagiosService($tempService);
                    $newInheritance->setNagiosServiceTemplateRelatedByTargetTemplate($serviceTemplate);
                    $newInheritance->save();
                    $success .= "Service Template " . $service->inheritance . " added to service $service->name \n";
                }

                if (isset($service->command)) {
                    $cmd = (new NagiosCommandPeer)->getByName($service->command);
                    if ($cmd) {
                        $tempService->setCheckCommand($cmd->getId());
                        $tempService->save();
                        $success .= "The command '" . $service->command . "' add to service $service->name \n";
                    } else {
                        $code = 1;
                        $error .= "The command '" . $service->command . "' doesn't exist.\n";
                    }
                    if (isset($service->parameters)) {
                        foreach ($service->parameters as $params) {
                            $param = new NagiosServiceCheckCommandParameter();
                            $param->setService($tempService->getId());
                            $param->setParameter($params);
                            $param->save();
                            $success .= "Command Parameter " . $params . " added to $service->name\n";
                        }
                    }
                }

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage() . "\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - create Service to Host*/
    public function createServiceToHost($hostName, $service, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosHostPeer;

        $host = $nsp::getByName($hostName);

        if (!$host) {
            $code = 1;
            $error .= "Host $hostName doesn't exist\n";
        }

        $nstp = new NagiosServiceTemplatePeer;
        //Test if the parent templates are given and set exist
        if (isset($service['inheritance'])) {
            $templateName = $service['inheritance'];
            $template = $nstp::getByName($templateName);
            if (!$template) {
                $code = 1;
                $error .= "Service Template $templateName not found\n";
            }
        }

        if (empty($error)) {
            try {
                // service interface
                $tempService = new NagiosService();
                $tempService->setDescription($service['name']);
                $tempService->setHost($host->getId());
                $tempService->save();
                $success .= "Service $service[name] added\n";
                if (isset($template)) {
                    $newInheritance = new NagiosServiceTemplateInheritance();
                    $newInheritance->setNagiosService($tempService);
                    $newInheritance->setNagiosServiceTemplateRelatedByTargetTemplate($template);
                    $newInheritance->save();
                    $success .= "Service Template " . $service['inheritance'] . " added to service $service[name] \n";
                }

                if (isset($service['command'])) {
                    $cmd = (new NagiosCommandPeer)->getByName($service['command']);
                    if ($cmd) {
                        $tempService->setCheckCommand($cmd->getId());
                        $tempService->save();
                        $success .= "The command '" . $service['command'] . "' add to service $service[name] \n";
                    } else {
                        $code = 1;
                        $error .= "The command '" . $service['command'] . "' doesn't exist.\n";
                    }
                    if (isset($service['parameters'])) {
                        foreach ($service['parameters'] as $params) {
                            $param = new NagiosServiceCheckCommandParameter();
                            $param->setService($tempService->getId());
                            $param->setParameter($params);
                            $param->save();
                            $success .= "Command Parameter " . $params . " added to $service[name]\n";
                        }
                    }
                }

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage() . "\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - addEventBroker */
    public function addEventBroker($broker, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";

        try {
            # Check if exist
            $module_list = NagiosBrokerModulePeer::doSelect(new Criteria());
            foreach ($module_list as $module) {
                if ($module->getLine() == $broker) {
                    $brokerExists = true;
                }
            }

            # Add broker
            if (!isset($brokerExists)) {
                $module = new NagiosBrokerModule();
                $module->setLine($broker);
                $module->save();
                $success .= "EventBroker added\n";
            } else {
                $success .= "EventBroker already exists\n";
            }
        } catch (Exception $e) {
            $error .= $e->getMessage() . "\n";
        }

        return $this->getLogs($error, $success);
    }

    /* LILAC - Add Host Groupe to Host Template */
    public function addHostGroupToHostTemplate($hostGroupName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {

            if ($template_host->addHostgroupByName($hostGroupName)) {
                $success .= "Hostgroup " . $hostGroupName . " added to host template " . $templateHostName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That hostGroup already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Host Groupe to Host */
    public function addHostGroupToHost($hostGroupName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhp = new NagiosHostPeer;
        // Find host
        $host = $nhp::getByName($hostName);
        if (!$host) {
            $error .= "Host $hostName not found\n";
        }

        if (empty($error)) {

            if ($host->addHostgroupByName($hostGroupName)) {
                $success .= "Hostgroup " . $hostGroupName . " added to host " . $hostName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That hostGroup already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Template to Host Template */
    public function addInheritanceTemplateToHostTemplate($inheritanceTemplateName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhtp = new NagiosHostTemplatePeer;
        // Find host template
        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {

            if ($template_host->addTemplateInheritance($inheritanceTemplateName)) {
                $success .= "Template Ihneritance " . $inheritanceTemplateName . " added to host template " . $templateHostName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That Template ihneritance already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Service groupe to Service Template */
    public function addServiceGroupToServiceTemplate($serviceGroupName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nstp = new NagiosServiceTemplatePeer;
        // Find host template
        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {
            if ($template_service->addServicegroupByName($serviceGroupName)) {
                $success .= "Service Group " . $serviceGroupName . " added to service template " . $templateServiceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That Service Group already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Service groupe to Service In Host */
    public function addServiceGroupToServiceInHost($serviceGroupName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }
        if (empty($error)) {

            if ($service->addServicegroupByName($serviceGroupName)) {
                $success .= "Service Group " . $serviceGroupName . " added to service " . $serviceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That Service Group already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Service groupe to Service In Host Template */
    public function addServiceGroupToServiceInHostTemplate($serviceGroupName, $serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }
        if (empty($error)) {

            if ($service->addServicegroupByName($serviceGroupName)) {
                $success .= "Service Group " . $serviceGroupName . " added to service " . $serviceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That Service Group already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact to Service Template */
    public function addContactToServiceTemplate($contactName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nstp = new NagiosServiceTemplatePeer;
        // Find host template
        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {

            if ($template_service->addContactByName($contactName)) {
                $success .= "Contact " . $contactName . " added to service template " . $templateServiceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That contact already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact Group to Service Template */
    public function addContactGroupToServiceTemplate($contactGroupName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nstp = new NagiosServiceTemplatePeer;
        // Find host template
        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {

            if ($template_service->addContactGroupByName($contactGroupName)) {
                $success .= "Contact group " . $contactGroupName . " added to service template " . $templateServiceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That contact group already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add an existing contact Group to Contact */
    public function addContactGroupToContact($contactName, $contactGroupName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $contact = (new NagiosContactPeer)->getByName($contactName);
            // Find host template
            if (!empty($contact)) {
                $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
                if (!empty($ncg)) {
                    $c = new Criteria();
                    $c->add(NagiosContactGroupMemberPeer::CONTACT, $contact->getId());
                    $c->add(NagiosContactGroupMemberPeer::CONTACTGROUP, $ncg->getId());
                    $ncgm = NagiosContactGroupMemberPeer::doSelectOne($c);
                    if (!empty($ncgm)) {
                        $code = 1;
                        $error .= "$contactName already bind to the group $contactGroupName ";
                    } else {
                        $contactGroupMember = new NagiosContactGroupMember();
                        $contactGroupMember->setContact($contact->getId());
                        $contactGroupMember->setContactgroup($ncg->getId());
                        $contactGroupMember->save();
                        $success .= "Membership has been established.";
                        if ($exportConfiguration) {
                            $this->exportConfigurationToNagios($error, $success);
                        }
                    }
                } else {
                    $code = 1;
                    $error .= "$contactGroupName don't exist. ";
                }
            } else {
                $code = 1;
                $error .= "$contactName don't exist. ";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add an existing command notification to Contact */
    public function addContactNotificationCommandToContact($contactName, $commandName, $type_command, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $contact = (new NagiosContactPeer)->getByName($contactName);
            // Find host template
            if (!empty($contact)) {
                $commandService = (new NagiosCommandPeer)->getByName($commandName);
                if (!empty($commandService)) {
                    $c = new Criteria();
                    $c->add(NagiosContactNotificationCommandPeer::TYPE, $type_command);
                    $c->add(NagiosContactNotificationCommandPeer::CONTACT_ID, $contact->getId());
                    $c->add(NagiosContactNotificationCommandPeer::COMMAND, $commandService->getId());
                    $ncnc = NagiosContactNotificationCommandPeer::doSelectOne($c);
                    if (!empty($ncnc)) {
                        $code = 1;
                        $error .= "Notification command already linked to $contactName.";
                    } else {
                        if ($type_command == "service") {
                            $contact->addServiceNotificationCommandByName($commandName);
                            $success .= "$commandName added to $contactName";
                        } elseif ($type_command == "host") {
                            $contact->addHostNotificationCommandByName($commandName);
                            $success .= "$commandName added to $contactName";
                        } else {
                            $code = 1;
                            $error .= "type not found.";
                        }

                        if ($exportConfiguration) {
                            $this->exportConfigurationToNagios($error, $success);
                        }
                    }
                } else {
                    $code = 1;
                    $error .= "$commandName don't exist. ";
                }
            } else {
                $code = 1;
                $error .= "$contactName don't exist. ";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Inheritance service Template to Service Template */
    public function addInheritServiceTemplateToServiceTemplate($inheritServiceTemplateName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nstp = new NagiosServiceTemplatePeer;
        // Find host template
        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {
            if ($template_service->addTemplateInheritance($inheritServiceTemplateName)) {
                $success .= "Inherit template " . $inheritServiceTemplateName . " added to service template " . $templateServiceName . "\n";
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }
            } else {
                $code = 1;
                $error .= "That Inheritance template already exists in that list or didn't exist!\n";
            }
        } else {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact Group to Service */
    public function addContactGroupToServiceInHost($contactGroupName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }

        if (empty($error)) {
            $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            if ($ncg) {
                $c = new Criteria();
                $c->add(NagiosServiceContactGroupMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactGroupMemberPeer::CONTACT_GROUP, $ncg->getId());
                $membership = NagiosServiceContactGroupMemberPeer::doSelectOne($c);

                if (!$membership) {
                    $tempMembership = new NagiosServiceContactGroupMember();
                    $tempMembership->setService($service->getId());
                    $tempMembership->setNagiosContactGroup($ncg);
                    $tempMembership->save();
                    $success .= "Contact group " . $contactGroupName . " added to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact group already exists in that list!\n";
                }
            } else {
                $code = 1;
                $error .= "That contact group didn't exist at all!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact Group to Service in a host template*/
    public function addContactGroupToServiceInHostTemplate($contactGroupName, $serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }

        if (empty($error)) {
            $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            if ($ncg) {
                $c = new Criteria();
                $c->add(NagiosServiceContactGroupMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactGroupMemberPeer::CONTACT_GROUP, $ncg->getId());
                $membership = NagiosServiceContactGroupMemberPeer::doSelectOne($c);

                if (!$membership) {
                    $tempMembership = new NagiosServiceContactGroupMember();
                    $tempMembership->setService($service->getId());
                    $tempMembership->setNagiosContactGroup($ncg);
                    $tempMembership->save();
                    $success .= "Contact group " . $contactGroupName . " added to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact group already exists in that list!\n";
                }
            } else {
                $code = 1;
                $error .= "That contact group didn't exist at all!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact to Service in a host */
    public function addContactToServiceInHost($contactName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }

        if (empty($error)) {
            $nc = (new NagiosContactPeer)->getByName($contactName);
            if ($nc) {
                $c = new Criteria();
                $c->add(NagiosServiceContactMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactMemberPeer::CONTACT, $nc->getId());
                $membership = NagiosServiceContactMemberPeer::doSelectOne($c);

                if (!$membership) {
                    $tempMembership = new NagiosServiceContactMember();
                    $tempMembership->setService($service->getId());
                    $tempMembership->setNagiosContact($nc);
                    $tempMembership->save();
                    $success .= "Contact " . $contactName . " added to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact already exists in that list!\n";
                }
            } else {
                $code = 1;
                $error .= "That contact already didn't exist at all!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add contact to Service in a host Template */
    public function addContactToServiceInHostTemplate($contactName, $serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }

        if (empty($error)) {
            $nc = (new NagiosContactPeer)->getByName($contactName);
            if ($nc) {
                $c = new Criteria();
                $c->add(NagiosServiceContactMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactMemberPeer::CONTACT, $nc->getId());
                $membership = NagiosServiceContactMemberPeer::doSelectOne($c);

                if (!$membership) {
                    $tempMembership = new NagiosServiceContactMember();
                    $tempMembership->setService($service->getId());
                    $tempMembership->setNagiosContact($nc);
                    $tempMembership->save();
                    $success .= "Contact " . $contactName . " added to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact already exists in that list!\n";
                }
            } else {
                $code = 1;
                $error .= "That contact already didn't exist at all!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Service template to Service */
    public function addServiceTemplateToServiceInHost($templateServiceName, $serviceName, $hostName)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }
        if (empty($error)) {
            $targetTemplate = NagiosServiceTemplatePeer::getByName($templateServiceName);
            if ($targetTemplate) {
                $c = new Criteria();
                $c->add(NagiosServiceTemplateInheritancePeer::SOURCE_SERVICE, $service->getId());
                $c->addAscendingOrderByColumn(NagiosServiceTemplateInheritancePeer::ORDER);
                $inheritanceList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                foreach ($inheritanceList as $inherit) {
                    if ($inherit->getNagiosServiceTemplateRelatedByTargetTemplate()->getId() == $targetTemplate->getId()) {
                        $code = 1;
                        $error .= "Service template $templateServiceName already exist in that list";
                        break;
                    }
                }
                if (empty($error)) {
                    $newInheritance = new NagiosServiceTemplateInheritance();
                    $newInheritance->setNagiosService($service);
                    $template = NagiosServiceTemplatePeer::getByName($templateServiceName);
                    $newInheritance->setNagiosServiceTemplateRelatedByTargetTemplate($template);
                    $newInheritance->save();
                    $success .= "Service Template $templateServiceName added to service $serviceName\n";
                }
            } else {
                $code = 1;
                $error .= "Service template $templateServiceName didn't exist.";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Add Service template to Service in a host template*/
    public function addServiceTemplateToServiceInHostTemplate($templateServiceName, $serviceName, $hostTemplateName)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }
        if (empty($error)) {
            $targetTemplate = NagiosServiceTemplatePeer::getByName($templateServiceName);
            if ($targetTemplate) {
                $c = new Criteria();
                $c->add(NagiosServiceTemplateInheritancePeer::SOURCE_SERVICE, $service->getId());
                $c->addAscendingOrderByColumn(NagiosServiceTemplateInheritancePeer::ORDER);
                $inheritanceList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                foreach ($inheritanceList as $inherit) {
                    if ($inherit->getNagiosServiceTemplateRelatedByTargetTemplate()->getId() == $targetTemplate->getId()) {
                        $code = 1;
                        $error .= "Service template $templateServiceName already exist in that list";
                        break;
                    }
                }
                if (empty($error)) {
                    $newInheritance = new NagiosServiceTemplateInheritance();
                    $newInheritance->setNagiosService($service);
                    $template = NagiosServiceTemplatePeer::getByName($templateServiceName);
                    $newInheritance->setNagiosServiceTemplateRelatedByTargetTemplate($template);
                    $newInheritance->save();
                    $success .= "Service Template $templateServiceName added to service $serviceName\n";
                }
            } else {
                $code = 1;
                $error .= "Service template $templateServiceName didn't exist.";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - modify contact */
    public function modifyContact($contactName, $newContactName = "", $contactAlias = "", $contactMail = "", $contactPager = "", $contactGroup = "", $serviceNotificationCommand = "", $hostNotificationCommand = "", $options = NULL, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 1;

        $ncp = new NagiosContactPeer;
        // Find host
        $tempContact = $ncp->getByName($contactName);
        if (!empty($tempContact)) {
            try {
                if (!empty($newContactName) && $contactName != $newContactName) {
                    $tempContact->setName($newContactName);
                    $code = 0;
                    $success .= "contact Name updated to  $contactName and become $newContactName";
                }
                if (!empty($contactAlias) && $tempContact->getAlias() != $contactAlias) {
                    $tempContact->setAlias($contactAlias);
                    $code = 0;
                    $success .= "contact Alias updated to  $contactName";
                }
                if (!empty($contactMail) && $tempContact->getEmail() != $contactMail) {
                    $tempContact->setEmail($contactMail);
                    $code = 0;
                    $success .= "contact mail updated to  $contactName";
                }
                if (!empty($contactPager) && $tempContact->getPager() != $contactPager) {
                    $code = 0;
                    $tempContact->setPager($contactPager);
                    $success .= "contact pager updated to  $contactName";
                }

                if (!empty($serviceNotificationCommand)) {
                    $commandService = (new NagiosCommandPeer)->getByName($serviceNotificationCommand);
                    if (!empty($commandService)) {
                        $c = new Criteria();
                        $c->add(NagiosContactNotificationCommandPeer::CONTACT_ID, $tempContact->getId());
                        $c->add(NagiosContactNotificationCommandPeer::COMMAND, $commandService->getId());
                        $ncnc = NagiosContactNotificationCommandPeer::doSelectOne($c);
                        if (!empty($ncnc)) {
                            $ncnc->delete();
                            $code = 0;
                            $success .= "$serviceNotificationCommand deleted to $contactName";
                        } else {
                            $tempContact->addServiceNotificationCommandByName($serviceNotificationCommand);
                            $code = 0;
                            $success .= "$serviceNotificationCommand added to $contactName";
                        }
                    } else {
                        $error .= "$serviceNotificationCommand does not exist.";
                    }
                }

                if (!empty($hostNotificationCommand)) {
                    $commandHost = (new NagiosCommandPeer)->getByName($hostNotificationCommand);
                    if (!empty($commandHost)) {
                        $c = new Criteria();
                        $c->add(NagiosContactNotificationCommandPeer::CONTACT_ID, $tempContact->getId());
                        $c->add(NagiosContactNotificationCommandPeer::COMMAND, $commandHost->getId());
                        $ncnc = NagiosContactNotificationCommandPeer::doSelectOne($c);
                        if (!empty($ncnc)) {
                            $ncnc->delete();
                            $code = 0;
                            $success .= "$hostNotificationCommand deleted to $contactName";
                        } else {
                            $tempContact->addHostNotificationCommandByName($hostNotificationCommand);
                            $code = 0;
                            $success .= "$hostNotificationCommand added to $contactName";
                        }
                    } else {
                        $code = 1;
                        $error .= "$hostNotificationCommand does not exist.";
                    }
                }

                if (!empty($options)) {
                    if (array_key_exists('host_notification_period', $options)) {
                        $tempContact->setHostNotificationPeriodByName(strval($options->host_notification_period));
                    }
                    if (array_key_exists('service_notification_period', $options)) {
                        $tempContact->setServiceNotificationPeriodByName(strval($options->service_notification_period));
                    }

                    if (array_key_exists('host_notification_options_down', $options) &&
                        $tempContact->getHostNotificationOnDown() != $options->host_notification_options_down) {
                        $code = 0;
                        $tempContact->setHostNotificationOnDown(intval($options->host_notification_options_down));
                    }
                    if (array_key_exists('host_notification_options_flapping', $options) &&
                        $tempContact->getHostNotificationOnFlapping() != $options->host_notification_options_flapping) {
                        $tempContact->setHostNotificationOnFlapping($options->host_notification_options_flapping);
                        $code = 0;
                    }

                    if (array_key_exists('host_notification_options_recovery', $options) &&
                        $tempContact->getHostNotificationOnRecovery() != $options->host_notification_options_recovery) {
                        $tempContact->setHostNotificationOnRecovery($options->host_notification_options_recovery);
                        $code = 0;
                    }

                    if (array_key_exists('host_notification_options_scheduled_downtime', $options) &&
                        $tempContact->getHostNotificationOnScheduledDowntime() != $options->host_notification_options_scheduled_downtime) {
                        $tempContact->setHostNotificationOnScheduledDowntime($options->host_notification_options_scheduled_downtime);
                        $code = 0;
                    }

                    if (array_key_exists('host_notification_options_unreachable', $options) &&
                        $tempContact->getHostNotificationOnUnreachable() != $options->host_notification_options_unreachable) {
                        $tempContact->setHostNotificationOnUnreachable($options->host_notification_options_unreachable);
                        $code = 0;
                    }

                    if (array_key_exists('service_notification_options_critical', $options) &&
                        $tempContact->getServiceNotificationOnCritical() != $options->service_notification_options_critical) {
                        $tempContact->setServiceNotificationOnCritical($options->service_notification_options_critical);
                        $code = 0;
                    }

                    if (array_key_exists('service_notification_options_flapping', $options) &&
                        $tempContact->getServiceNotificationOnFlapping() != $options->service_notification_options_flapping) {
                        $tempContact->setServiceNotificationOnFlapping($options->service_notification_options_flapping);
                        $code = 0;
                    }

                    if (array_key_exists('service_notification_options_recovery', $options) &&
                        $tempContact->getServiceNotificationOnRecovery() != $options->service_notification_options_recovery) {
                        $tempContact->setServiceNotificationOnRecovery($options->service_notification_options_recovery);
                        $code = 0;
                    }

                    if (array_key_exists('service_notification_options_unknown', $options) &&
                        $tempContact->getServiceNotificationOnUnknown() != $options->service_notification_options_unknown) {
                        $tempContact->setServiceNotificationOnUnknown($options->service_notification_options_unknown);
                        $code = 0;
                    }

                    if (array_key_exists('service_notification_options_warning', $options) &&
                        $tempContact->getServiceNotificationOnWarning() != $options->service_notification_options_warning) {
                        $tempContact->setServiceNotificationOnWarning($options->service_notification_options_warning);
                        $code = 0;
                    }

                    if (array_key_exists('can_submit_commands', $options) &&
                        $tempContact->getCanSubmitCommands() != $options->can_submit_commands) {
                        $tempContact->setCanSubmitCommands($options->can_submit_commands);
                        $code = 0;
                    }

                    if (array_key_exists('retain_status_information', $options) &&
                        $tempContact->getRetainStatusInformation() != $options->retain_status_information) {
                        $tempContact->setRetainStatusInformation($options->retain_status_information);
                        $code = 0;
                    }

                    if (array_key_exists('retain_nonstatus_information', $options) &&
                        $tempContact->getRetainNonstatusInformation() != $options->retain_nonstatus_information) {
                        $tempContact->setRetainNonstatusInformation($options->retain_nonstatus_information);
                        $code = 0;
                    }

                    if (array_key_exists('host_notifications_enabled', $options) &&
                        $tempContact->getHostNotificationsEnabled() != $options->host_notifications_enabled) {
                        $tempContact->setHostNotificationsEnabled($options->host_notifications_enabled);
                        $code = 0;
                    }

                    if (array_key_exists('service_notifications_enabled', $options) &&
                        $tempContact->getServiceNotificationsEnabled() != $options->service_notifications_enabled) {
                        $tempContact->setServiceNotificationsEnabled($options->service_notifications_enabled);
                        $code = 0;
                    }
                }

                $tempContact->save();

                if (!empty($contactGroup)) {
                    $ncg = (new NagiosContactGroupPeer)->getByName($contactGroup);
                    if (!empty($ncg)) {
                        $c = new Criteria();
                        $c->add(NagiosContactGroupMemberPeer::CONTACT, $tempContact->getId());
                        $c->add(NagiosContactGroupMemberPeer::CONTACTGROUP, $ncg->getId());
                        $ncgm = NagiosContactGroupMemberPeer::doSelectOne($c);
                        if (!empty($ncgm)) {
                            $ncgm->delete();
                            $code = 0;
                            $success .= " | Membership deleted withcontact group";
                        } else {
                            $contactGroupMember = new NagiosContactGroupMember();
                            $contactGroupMember->setContact($tempContact->getId());
                            $contactGroupMember->setContactgroup($ncg->getId());
                            $contactGroupMember->save();
                            $success .= " | Membership created with contact group";
                            $code = 0;
                        }
                    }
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage();
            }

            if ($code == 0) {
                $success .= "contact had been modified.";
            }

            if ($exportConfiguration) {
                $this->exportConfigurationToNagios($error, $success);
            }
        } else {
            $code = 1;
            $error .= "$contactName already exists\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Modify Service from Host template--- */
    public function modifyServicefromHostTemplate($hostTemplateName, $service, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $changed = 0;

        $hostTemplate = NagiosHostTemplatePeer::getByName($hostTemplateName);
        $nagioService = NagiosServicePeer::getByHostTemplateAndDescription($hostTemplateName, $service->name);

        if (!$hostTemplate) {
            $code = 1;
            $error .= "HostTemplate $hostTemplateName doesn't exist\n";
        }
        if (!$nagioService) {
            $code = 1;
            $error .= "Service $service->name doesn't exist\n";
        } else {
            if (isset($service->command)) {
                $cmd = NagiosCommandPeer::retrieveByPK($nagioService->getCheckCommand());
                if (!$cmd || ($cmd->getName() !== $service->command)) {
                    $command = (new NagiosCommandPeer)->getByName($service->command);
                    $nagioService->setCheckCommand($command->getId());
                    $nagioService->save();
                    $changed++;
                }
            }
            if (isset($service->new_name) && $nagioService->getDescription() !== $service->new_name) {
                $nagioService->setDescription($service->new_name);
                $nagioService->save();
                $changed++;
            }
            if (isset($service->parameters)) {
                //We prepared the list of existing parameters in the service
                $tempListParam = [];
                foreach ($nagioService->getNagiosServiceCheckCommandParameters()->toArray() as $paramObject) {
                    array_push($tempListParam, $paramObject["Parameter"]);
                }
                foreach ($service->parameters as $paramName) {

                    if (!in_array($paramName, $tempListParam)) {
                        $param = new NagiosServiceCheckCommandParameter();
                        $param->setService($nagioService->getId());
                        $param->setParameter($paramName);
                        $param->save();
                        $changed++;
                    }
                }
            }
            if ($changed > 0) {
                $success .= $nagioService->getDescription() . " in host Template $hostTemplateName has been updated.\n";
            } else {
                $code = 1;
                $error .= $nagioService->getDescription() . " in host Template $hostTemplateName don't update\n";
            }
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Modify Service --- */
    public function modifyServicefromHost($hostName, $service, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $changed = 0;

        $host = NagiosHostPeer::getByName($hostName);
        $nagioService = NagiosServicePeer::getByHostAndDescription($hostName, $service->name);

        if (!$host) {
            $code = 1;
            $error .= "Host $hostName doesn't exist\n";
        }
        if (!$nagioService) {
            $code = 1;
            $error .= "Service $service->name doesn't exist\n";
        } else {
            if (isset($service->command)) {
                $cmd = NagiosCommandPeer::retrieveByPK($nagioService->getCheckCommand());
                if (!$cmd || ($cmd->getName() !== $service->command)) {
                    $command = (new NagiosCommandPeer)->getByName($service->command);
                    $nagioService->setCheckCommand($command->getId());
                    $nagioService->save();
                    $changed++;
                }
            }
            if (isset($service->new_name) && $nagioService->getDescription() !== $service->new_name) {
                $nagioService->setDescription($service->new_name);
                $nagioService->save();
                $changed++;
            }
            if (isset($service->parameters)) {
                //We prepared the list of existing parameters in the service
                $tempListParam = [];
                foreach ($nagioService->getNagiosServiceCheckCommandParameters()->toArray() as $paramObject) {
                    array_push($tempListParam, $paramObject["Parameter"]);
                }
                foreach ($service->parameters as $paramName) {

                    if (!in_array($paramName, $tempListParam)) {
                        $param = new NagiosServiceCheckCommandParameter();
                        $param->setService($nagioService->getId());
                        $param->setParameter($paramName);
                        $param->save();
                        $changed++;
                    }
                }
            }
            if ($changed > 0) {
                $success .= $nagioService->getDescription() . " in host $hostName has been updated.\n";
            } else {
                $code = 1;
                $error .= $nagioService->getDescription() . " in host $hostName don't update\n";
            }
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Modify Command --- */
    public function modifyCommand($commandName, $newCommandName, $commandLine, $commandDescription = NULL)
    {
        /*---Modify check command ==> 'dummy_ok'---*/
        //TODO ==> Change command to 'dummy_ok' for template GENERIC_HOST (inheritance)
        $error = "";
        $success = "";
        $code = 0;

        $changed = 0;

        $ncp = new NagiosCommandPeer;
        $targetCommand = $ncp->getByName($commandName);
        if (!$targetCommand) {
            $code = 1;
            $error .= $error .= "The command '" . $commandName . "' does not exist\n";
        } else {
            if (isset($newCommandName) && $newCommandName != $commandName) {
                $targetCommand->setName($newCommandName);
                $targetCommand->save();
                $changed++;
            }
            if (isset($commandDescription) && $commandDescription != $targetCommand->getDescription()) {
                $targetCommand->setDescription($commandDescription);
                $targetCommand->save();
                $changed++;
            }
            if ($commandLine != $targetCommand->getLine()) {
                $targetCommand->setLine($commandLine);
                $targetCommand->save();
                $changed++;
            }

            if ($changed > 0) {
                $success .= "The command '" . $targetCommand->getName() . "' has been updated.";
            } else {
                $code = 1;
                $error .= "The command '" . $targetCommand->getName() . "' failed to update\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs, "changes" => $changed);
    }

    /**
     * @brief  Modify one or many Nagios Resources
     * @details  Nagios resources are $USER$ Nagios macros. RGM use these to store customer's constants.
     * @param  array $resources a JSON structure that reflects the resources to modify in the form of:
     *
     *     {
     *         "resources": {
     *             "User1": "value1",
     *             "User12": "value12"
     *         }
     *     }
     *
     * @return  array An array with the return status of the call
     */
    public function modifyNagiosResources($resources)
    {
        $error = "";
        $success = "";
        $code = 0;
        // print_r($resources);
        try {
            $resourceCfg = NagiosResourcePeer::doSelectOne(new Criteria());
            if (!$resourceCfg) {
                $resourceCfg = new NagiosResource();
                $resourceCfg->save();
            }

            foreach ($resources as $key => $value) {
                $resourceCfg->setByName(ucfirst(strtolower($key)), $value);
            }
            $row = $resourceCfg->save();

            if ($row == 0) {
                $code++;
            } else {
                $success .= "Resources updated.";
            }
        } catch (Exception $e) {
            $code++;
            $error .= "An exception occured : $e";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs, "resources" => $resources);
    }

    /* LILAC - Modify Host */
    public function modifyHost($templateHostName, $hostName, $newHostName = NULL, $hostIp = NULL, $hostAlias = "", $contactName = NULL, $contactGroupName = NULL, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nhp = new NagiosHostPeer;
        // Find host
        $host = $nhp::getByName($hostName);
        if (!isset($host)) {
            $code = 1;
            $error .= "Host $hostName doesn't exist\n";
        }

        // Lauch actions if no errors
        if (empty($error)) {
            try {
                // host
                $changed = 0;
                if (isset($newHostName) && $host->getName() != $newHostName) {
                    $host->setName($newHostName);
                    $changed++;
                }
                $host->setAlias($hostAlias);
                if (isset($hostIp) && $host->getAddress() != $hostIp) {
                    $host->setAddress($hostIp);
                    $changed++;
                }
                $host->save();
                if ($changed > 0) {
                    $success .= "Host $hostName updated\n";
                }

                // host-template
                if (isset($templateHostName)) {
                    $nhtp = new NagiosHostTemplatePeer;
                    // Find host template
                    $template_host = $nhtp::getByName($templateHostName);
                    if (!$template_host) {
                        $code = 1;
                        $error .= "Host Template $templateHostName not found\n";
                    } else {
                        $c = new Criteria();
                        $c->add(NagiosHostTemplateInheritancePeer::SOURCE_TEMPLATE, $template_host->getId());
                        $c->add(NagiosHostTemplateInheritancePeer::TARGET_TEMPLATE, $targetInheritanceTemplate->getId());
                        $membership = NagiosHostTemplateInheritancePeer::doSelectOne($c);
                        if (!$membership) {
                            $newInheritance = new NagiosHostTemplateInheritance();
                            $newInheritance->setNagiosHost($host);
                            $newInheritance->setNagiosHostTemplateRelatedByTargetTemplate($template_host);
                            $newInheritance->save();
                            $changed++;
                            $success .= "Host Template " . $templateHostName . " added to host " . $hostName . "\n";
                        } else {
                            $error .= "Host Template " . $templateHostName . " already linked to host " . $hostName . "\n";
                        }
                    }
                }

                //Add a contact to a host
                if ($contactName != NULL && $this->addContactToHost($host, $contactName)["code"] == 0) {
                    $changed++;
                }

                // Add contactgroup to host
                if ($contactGroupName != NULL && $this->addContactGroupToHost($host, $contactGroupName)["code"] == 0) {
                    $changed++;
                }

                // Export
                if ($exportConfiguration) {
                    $this->exportConfigurationToNagios($error, $success);
                }

                if ($changed == 0) {
                    $code = 1;
                }
            } catch (Exception $e) {
                $code = 1;
                $error .= $e->getMessage() . "\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Modify CheckCommand for a service template--- */
    public function modifyCheckCommandToServiceTemplate($commandName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        $targetTemplateService = NagiosServiceTemplatePeer::getByName($templateServiceName);
        try {
            if (!$targetTemplateService) {
                $code = 1;
                $error .= "The Template service '" . $templateServiceName . "' does not exist\n";
            } else {
                $cmd = NagiosCommandPeer::retrieveByPK($targetTemplateService->getCheckCommand());

                if ($cmd && $cmd->getName() == $commandName) {
                    $code = 1;
                    $error .= "The command '" . $commandName . "' is already set to this template\n";
                } else {
                    if ($targetTemplateService->setCheckCommandByName($commandName)) {
                        $success .= "The command : $commandName had been set to the service template: $templateServiceName.";
                        $targetTemplateService->save();
                    } else {
                        $code = 1;
                        $error .= "The command '" . $commandName . "' does not exist.\n";
                    }
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }
        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Modify CheckCommand for a host template--- */
    public function modifyCheckCommandToHostTemplate($commandName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $targetTemplateHost = NagiosHostTemplatePeer::getByName($templateHostName);
            if (!$targetTemplateHost) {
                $code = 1;
                $error .= "The Template service '" . $templateHostName . "' does not exist\n";
            } else {
                $cmd = NagiosCommandPeer::retrieveByPK($targetTemplateHost->getCheckCommand());
                if ($cmd && $cmd->getName() == $commandName) {
                    $code = 1;
                    $error .= "The command '" . $commandName . "' is already set to this template\n";
                } else {
                    if ($targetTemplateHost->setCheckCommandByName($commandName)) {
                        $success .= "The command : $commandName had been set to the service template: $templateHostName.||";
                        $targetTemplateHost->save();
                    } else {
                        $code = 1;
                        $error .= "The command '" . $commandName . "' does not exist.\n";
                    }
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete host Downtimes */
    public function deleteHostDowntime($idDowntime)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $CommandFile = "/srv/rgm/nagios/var/log/rw/nagios.cmd";
            //$success .= $date_end->format('d-m-Y H:i:s');
            $date = new DateTime();
            $timestamp = $date->getTimestamp();
            $cmdline = '[' . $timestamp . '] DEL_HOST_DOWNTIME;' . $idDowntime . '' . PHP_EOL;
            file_put_contents($CommandFile, $cmdline, FILE_APPEND);
            $downtimesList = $this->getDowntimes();
            $x = 0;
            $verify = True;
            while ($x < count($downtimesList) && $verify) {
                if ($idDowntime != $downtimesList[$x]["id"]) {
                    $verify = False;
                    $success .= "Schedule host downtimes succesfully deleted.";
                }
                $x++;
            }

            if ($verify) {
                $code = 1;
                $error .= "An error occurred nothing happen.";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete service Downtimes */
    public function deleteServiceDowntime($idDowntime)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $CommandFile = "/srv/rgm/nagios/var/log/rw/nagios.cmd";
            //$success .= $date_end->format('d-m-Y H:i:s');
            $date = new DateTime();
            $timestamp = $date->getTimestamp();
            $cmdline = '[' . $timestamp . '] DEL_SVC_DOWNTIME;' . $idDowntime . '' . PHP_EOL;
            file_put_contents($CommandFile, $cmdline, FILE_APPEND);
            $downtimesList = $this->getDowntimes();
            $x = 0;
            $verify = True;
            while ($x < count($downtimesList) && $verify) {
                if ($idDowntime != $downtimesList[$x]["id"]) {
                    $verify = False;
                    $success .= "Schedule service downtimes succesfully deleted.";
                }
                $x++;
            }

            if ($verify) {
                $code = 1;
                $error .= "An error occurred nothing happen.";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Custom Argument to a host */
    public function deleteCustomArgumentsToHost($hostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhp = new NagiosHostPeer;
        $host = $nhp::getByName($hostName);
        // Find host
        if (!$host) {
            $error .= "Host :  $hostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosHostCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosHostCustomObjectVarPeer::HOST, $host->getId());
                $nhcov = NagiosHostCustomObjectVarPeer::doSelectOne($c);

                if ($nhcov) {
                    $nhcov->delete();
                    $success .= "$key has been deleted.\n";
                    $changed++;
                } else {
                    $error .= "$key not existed in that host\n";
                }
            }
        }

        if ($changed > 0) {
            $success .= "$hostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$hostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Custom Argument to a host */
    public function deleteCustomArgumentsToHostTemplate($templateHostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhtp = new NagiosHostTemplatePeer;
        $templateHost = $nhtp::getByName($templateHostName);
        // Find template host
        if (!$templateHost) {
            $error .= "Tempalte Host :  $templateHostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosHostCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosHostCustomObjectVarPeer::HOST_TEMPLATE, $templateHost->getId());
                $nhcov = NagiosHostCustomObjectVarPeer::doSelectOne($c);

                if ($nhcov) {
                    $nhcov->delete();
                    $success .= "$key has been deleted.\n";
                    $changed++;
                } else {
                    $error .= "$key not existed in that host\n";
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateHostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateHostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Custom Argument to a Service Template */
    public function deleteCustomArgumentsToServiceTemplate($templateServiceName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nstp = new NagiosServiceTemplatePeer;
        $templateService = $nstp::getByName($templateServiceName);
        // Find template host
        if (!$templateService) {
            $error .= "Tempalte Service:  $templateServiceName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosServiceCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosServiceCustomObjectVarPeer::SERVICE_TEMPLATE, $templateService->getId());
                $nscov = NagiosServiceCustomObjectVarPeer::doSelectOne($c);

                if ($nscov) {
                    $nscov->delete();
                    $success .= "$key has been deleted.\n";
                    $changed++;
                } else {
                    $error .= "$key not existed in that host\n";
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateServiceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateServiceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Custom Argument to a Service  */
    public function deleteCustomArgumentsToService($serviceName, $hostName, $customArguments)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nsp = new NagiosServicePeer;
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        // Find template host
        if (!$service) {
            $error .= "Service:  $serviceName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing custom arg in the Host
            foreach ($customArguments as $key => $value) {
                $c = new Criteria();
                $c->add(NagiosServiceCustomObjectVarPeer::VAR_NAME, $key);
                $c->add(NagiosServiceCustomObjectVarPeer::SERVICE, $service->getId());
                $nscov = NagiosServiceCustomObjectVarPeer::doSelectOne($c);

                if ($nscov) {
                    $nscov->delete();
                    $success .= "$key has been deleted.\n";
                    $changed++;
                } else {
                    $error .= "$key not existed in that host\n";
                }
            }
        }

        if ($changed > 0) {
            $success .= "$serviceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$serviceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete an existing command Notification to Contact */
    public function deleteContactNotificationCommandToContact($contactName, $commandName, $type_command, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $contact = (new NagiosContactPeer)->getByName($contactName);
            // Find host template
            if (!empty($contact)) {
                $commandService = (new NagiosCommandPeer)->getByName($commandName);
                if (!empty($commandService)) {
                    $c = new Criteria();

                    $c->add(NagiosContactNotificationCommandPeer::TYPE, $type_command);
                    $c->add(NagiosContactNotificationCommandPeer::CONTACT_ID, $contact->getId());
                    $c->add(NagiosContactNotificationCommandPeer::COMMAND, $commandService->getId());
                    $ncnc = NagiosContactNotificationCommandPeer::doSelectOne($c);
                    if (!empty($ncnc)) {
                        $ncnc->delete();
                        $success .= "$commandName deleted to $contactName";
                        if ($exportConfiguration) {
                            $this->exportConfigurationToNagios($error, $success);
                        }
                    } else {
                        $code = 1;
                        $error .= "Membership don't exist. ";
                    }
                } else {
                    $code = 1;
                    $error .= "$commandName don't exist. ";
                }
            } else {
                $code = 1;
                $error .= "$contactName don't exist. ";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }


    /* LILAC - Delete contact Group to Service */
    public function deleteContactGroupToServiceInHost($contactGroupName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);

        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }

        if (empty($error)) {
            $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            if ($ncg) {
                $c = new Criteria();
                $c->add(NagiosServiceContactGroupMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactGroupMemberPeer::CONTACT_GROUP, $ncg->getId());
                $membership = NagiosServiceContactGroupMemberPeer::doSelectOne($c);

                if ($membership) {
                    $membership->delete();
                    $success .= "Contact Group " . $contactGroupName . " deleted to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact group already not existing in that list !\n";
                }
            } else {
                $code = 1;
                $error .= "That contact group didn't exist!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact Group to Service in host template */
    public function deleteContactGroupToServiceInHostTemplate($contactGroupName, $serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);

        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }

        if (empty($error)) {
            $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            if ($ncg) {
                $c = new Criteria();
                $c->add(NagiosServiceContactGroupMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactGroupMemberPeer::CONTACT_GROUP, $ncg->getId());
                $membership = NagiosServiceContactGroupMemberPeer::doSelectOne($c);

                if ($membership) {
                    $membership->delete();
                    $success .= "Contact Group " . $contactGroupName . " deleted to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact group already not existing in that list !\n";
                }
            } else {
                $code = 1;
                $error .= "That contact group didn't exist!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete an existing contact Group to Contact */
    public function deleteContactGroupToContact($contactName, $contactGroupName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $contact = (new NagiosContactPeer)->getByName($contactName);
            // Find host template
            if (!empty($contact)) {
                $ncg = (new NagiosContactGroupPeer)->getByName($contactGroupName);
                if (!empty($ncg)) {
                    $c = new Criteria();
                    $c->add(NagiosContactGroupMemberPeer::CONTACT, $contact->getId());
                    $c->add(NagiosContactGroupMemberPeer::CONTACTGROUP, $ncg->getId());
                    $ncgm = NagiosContactGroupMemberPeer::doSelectOne($c);
                    if (!empty($ncgm)) {
                        $ncgm->delete();
                        $success .= "Membership has been delete.";
                        if ($exportConfiguration) {
                            $this->exportConfigurationToNagios($error, $success);
                        }
                    } else {
                        $code = 1;
                        $error .= "$contactName already bind to the group $contactGroupName ";
                    }
                } else {
                    $code = 1;
                    $error .= "$contactGroupName don't exist. ";
                }
            } else {
                $code = 1;
                $error .= "$contactName don't exist. ";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage();
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact to Service */
    public function deleteContactToServiceInHost($contactName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);

        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }

        if (empty($error)) {
            $nc = (new NagiosContactPeer)->getByName($contactName);
            if ($nc) {
                $c = new Criteria();
                $c->add(NagiosServiceContactMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactMemberPeer::CONTACT, $nc->getId());
                $membership = NagiosServiceContactMemberPeer::doSelectOne($c);

                if ($membership) {
                    $membership->delete();
                    $success .= "Contact " . $contactName . " deleted to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact already not existing in that list !\n";
                }
            } else {
                $code = 1;
                $error .= "That contact didn't exist!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact to Service in Host Template */
    public function deleteContactToServiceInHostTemplate($contactName, $serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);

        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }

        if (empty($error)) {
            $nc = (new NagiosContactPeer)->getByName($contactName);
            if ($nc) {
                $c = new Criteria();
                $c->add(NagiosServiceContactMemberPeer::SERVICE, $service->getId());
                $c->add(NagiosServiceContactMemberPeer::CONTACT, $nc->getId());
                $membership = NagiosServiceContactMemberPeer::doSelectOne($c);

                if ($membership) {
                    $membership->delete();
                    $success .= "Contact " . $contactName . " deleted to service template " . $serviceName . "\n";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios($error, $success);
                    }
                } else {
                    $code = 1;
                    $error .= "That contact already not existing in that list !\n";
                }
            } else {
                $code = 1;
                $error .= "That contact didn't exist!\n";
            }
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete serviceTemplate to Service in a host */
    public function deleteServiceTemplateToServiceInHost($templateServiceName, $serviceName, $hostName)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }
        if (empty($error)) {
            $targetTemplate = NagiosServiceTemplatePeer::getByName($templateServiceName);
            if ($targetTemplate) {
                $c = new Criteria();
                $c->add(NagiosServiceTemplateInheritancePeer::SOURCE_SERVICE, $service->getId());
                $c->addAscendingOrderByColumn(NagiosServiceTemplateInheritancePeer::ORDER);
                $inheritanceList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                $found = false;
                foreach ($inheritanceList as $inherit) {
                    if ($inherit->getNagiosServiceTemplateRelatedByTargetTemplate()->getId() == $targetTemplate->getId()) {
                        // Delete the inheritance
                        $inherit->delete();
                        // Okay, regrab the list
                        $newList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                        for ($counter = 0; $counter < count($newList); $counter++) {
                            // Reordering
                            $newList[$counter]->setOrder($counter);
                            $newList[$counter]->save();
                        }
                        $success .= "Service Template $templateServiceName removed from service $serviceName\n";
                        $found = true;
                    }
                }
                if (!$found) {
                    $error .= "$templateServiceName doesn't exist in this service.\n";
                }
            } else {
                $error .= "$templateServiceName doesn't exist .\n";
            }
        }
        if (!empty($error)) {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete serviceTemplate to Service in a host template */
    public function deleteServiceTemplateToServiceInHostTemplate($templateServiceName, $serviceName, $hostTemplateName)
    {
        $error = "";
        $success = "";
        $code = 0;

        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostTemplateName not found\n";
        }
        if (empty($error)) {
            $targetTemplate = NagiosServiceTemplatePeer::getByName($templateServiceName);
            if ($targetTemplate) {
                $c = new Criteria();
                $c->add(NagiosServiceTemplateInheritancePeer::SOURCE_SERVICE, $service->getId());
                $c->addAscendingOrderByColumn(NagiosServiceTemplateInheritancePeer::ORDER);
                $inheritanceList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                $found = false;
                foreach ($inheritanceList as $inherit) {
                    if ($inherit->getNagiosServiceTemplateRelatedByTargetTemplate()->getId() == $targetTemplate->getId()) {
                        // Delete the inheritance
                        $inherit->delete();
                        // Okay, regrab the list
                        $newList = NagiosServiceTemplateInheritancePeer::doSelect($c);
                        for ($counter = 0; $counter < count($newList); $counter++) {
                            // Reordering
                            $newList[$counter]->setOrder($counter);
                            $newList[$counter]->save();
                        }
                        $success .= "Service Template $templateServiceName removed from service $serviceName\n";
                        $found = true;
                    }
                }
                if (!$found) {
                    $error .= "$templateServiceName doesn't exist in this service.\n";
                }
            } else {
                $error .= "$templateServiceName doesn't exist .\n";
            }
        }
        if (!empty($error)) {
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete command Parameter to a service Template */
    public function deleteCheckCommandParameterToServiceTemplate($templateServiceName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nstp = new NagiosServiceTemplatePeer();
        // Find host template

        $template_service = $nstp::getByName($templateServiceName);
        if (!$template_service) {
            $error .= "Service Template $templateServiceName not found\n";
        }

        if (empty($error)) {
            //We prepared the list of existing parameters in the service
            $c = new Criteria();
            $c->add(NagiosServiceCheckCommandParameterPeer::TEMPLATE, $template_service->getId());
            $c->addAscendingOrderByColumn(NagiosServiceCheckCommandParameterPeer::ID);

            $parameter_list = NagiosServiceCheckCommandParameterPeer::doSelect($c);

            foreach ($parameters as $paramName) {
                foreach ($parameter_list as $paramObject) {
                    if ($paramName == $paramObject->getParameter()) {
                        $paramObject->delete();
                        $changed++;
                    }
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateServiceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateServiceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete command Parameter to a service  */
    public function deleteCheckCommandParameterToServiceInHost($serviceName, $hostName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nsp = new NagiosServicePeer();
        $service = $nsp::getByHostAndDescription($hostName, $serviceName);
        if (!$service) {
            $code = 1;
            $error .= "Service $serviceName or $hostName not found\n";
        }
        if (empty($error)) {
            //We prepared the list of existing parameters in the service
            $c = new Criteria();
            $c->add(NagiosServiceCheckCommandParameterPeer::SERVICE, $service->getId());
            $c->addAscendingOrderByColumn(NagiosServiceCheckCommandParameterPeer::ID);

            $parameter_list = NagiosServiceCheckCommandParameterPeer::doSelect($c);

            foreach ($parameters as $paramName) {
                foreach ($parameter_list as $paramObject) {
                    if ($paramName == $paramObject->getParameter()) {
                        $paramObject->delete();
                        $changed++;
                    }
                }
            }
        }
        if ($changed > 0) {
            $success .= "$serviceName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$serviceName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete command Parameter to a host Template */
    public function deleteCheckCommandParameterToHostTemplate($templateHostName, $parameters)
    {
        $error = "";
        $success = "";
        $code = 0;
        $changed = 0;
        $nhtp = new NagiosHostTemplatePeer();
        // Find host template

        $template_host = $nhtp::getByName($templateHostName);
        if (!$template_host) {
            $error .= "Host Template $templateHostName not found\n";
        }

        if (empty($error)) {
            //We prepared the list of existing parameters in the host
            $c = new Criteria();
            $c->add(NagiosHostCheckCommandParameterPeer::HOST_TEMPLATE, $template_host->getId());
            $c->addAscendingOrderByColumn(NagiosHostCheckCommandParameterPeer::ID);

            $parameter_list = NagiosHostCheckCommandParameterPeer::doSelect($c);

            foreach ($parameters as $paramName) {
                foreach ($parameter_list as $paramObject) {
                    if ($paramName == $paramObject->getParameter()) {
                        $paramObject->delete();
                        $changed++;
                    }
                }
            }
        }

        if ($changed > 0) {
            $success .= "$templateHostName has been updated.\n";
        } else {
            $code = 1;
            $error .= "$templateHostName don't update\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact */
    public function deleteContact($contactName)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $ncp = new NagiosContactPeer;
            // Find host contact
            $contact = $ncp->getByName($contactName);
            if (!$contact) {
                $code = 1;
                $error .= "Contact $contactName doesn't exist\n";
            } else {
                $contact->delete();
                $success .= "$contactName as been deleted\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact Group */
    public function deleteContactGroup($contactGroupName)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $ncp = new NagiosContactGroupPeer;
            // Find host contact
            $contactGroup = $ncp->getByName($contactGroupName);
            if (!$contactGroup) {
                $code = 1;
                $error .= "Contact Group $contactGroupName doesn't exist\n";
            } else {
                $contactGroup->delete();
                $success .= "$contactGroupName as been deleted\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Service by host*/
    public function deleteService($serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $nhp = new NagiosHostPeer;

            $host = $nhp::getByName($hostName);
            if (!$host) {
                $code++;
                $error .= "Host $hostName doesn't exist\n";
            } else {
                $service = NagiosServicePeer::getByHostAndDescription($hostName, $serviceName);

                if (!$service) {
                    $error .= "Service didn't exist.";
                    $code++;
                } else {
                    $service->delete();
                    $success .= "$serviceName in host $hostName had been deleted";
                    // Export
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios();
                    }
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Service by host Template*/
    public function deleteServiceByHostTemplate($serviceName, $hostTemplateName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;
        try {
            $nhtp = new NagiosHostTemplatePeer;

            $host = $nhtp::getByName($hostTemplateName);
            if (!$host) {
                $code++;
                $error .= "Host $hostTemplateName doesn't exist\n";
            } else {
                $service = NagiosServicePeer::getByHostTemplateAndDescription($hostTemplateName, $serviceName);
                if (!$service) {
                    $error .= "Service didn't exist";
                    $code++;
                } else {
                    $service->delete();
                    $success .= "$serviceName in host $hostTemplateName had been deleted";
                    if ($exportConfiguration) {
                        $this->exportConfigurationToNagios();
                    }
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delete service template */
    public function deleteServiceTemplate($templateServiceName)
    {
        $error = "";
        $success = "";

        try {
            $targetTemplate = NagiosServiceTemplatePeer::getByName($templateServiceName);

            if (!$targetTemplate) {
                $code = 1;
                $error .= "The template '" . $templateServiceName . "'does not exist\n";
            } else {
                $targetTemplate->delete();
                $success .= "The template '" . $templateServiceName . "' deleted.\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        return $this->getLogs($error, $success);
    }

    /* LILAC - delete Command */
    public function deleteCommand($commandName)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $ncp = new NagiosCommandPeer;

            $targetCommand = $ncp->getByName($commandName);
            if (!$targetCommand) {
                $code = 1;
                $error .= "The command '" . $commandName . "'does not exist\n";
            } else {
                $targetCommand->delete();
                $success .= "The command '" . $commandName . "' deleted.\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Host */
    public function deleteHost($hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $nhp = new NagiosHostPeer;
            $host = $nhp::getByName($hostName);

            if ($host) {
                $host->delete();
                $success .= "Host " . $hostName . " deleted\n";
            } else {
                $code = 1;
                $error .= "Host " . $hostName . " not found\n";
            }

            // Export
            if ($exportConfiguration) {
                $this->exportConfigurationToNagios();
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Templates Hosts */
    public function deleteHostTemplate($templateHostName)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetTemplate = NagiosHostTemplatePeer::getByName($templateHostName);

            if (!$targetTemplate) {
                $code = 1;
                $error .= "The template '" . $templateHostName . "'does not exist\n";
            } else {
                $targetTemplate->delete();
                $success .= "The template '" . $templateHostName . "' deleted.\n";
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete HostTemplates to Host */
    public function deleteHostTemplateToHost($templateHostName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetTemplate = NagiosHostTemplatePeer::getByName($templateHostName);
            $targetHost = NagiosHostPeer::getByName($hostName);
            $find = false;

            if (!$targetTemplate || !$targetHost) {
                $code = 1;
                $error .= (!$targetTemplate ? "The template '" . $templateHostName . "'does not exist\n" : "The host '" . $hostName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostTemplateInheritancePeer::SOURCE_HOST, $targetHost->getId());
                $c->addAscendingOrderByColumn(NagiosHostTemplateInheritancePeer::ORDER);
                $listAllHostsTemplate = NagiosHostTemplateInheritancePeer::doSelect($c);

                foreach ($listAllHostsTemplate as $template) {
                    if ($template->getTargetTemplate() == $targetTemplate->getId()) {
                        $template->delete();
                        $find = true;
                    }
                }
                if (!$find) {
                    $error .= "This template " . $targetTemplate->getName() . " is not find for this host : " . $targetHost->getName() . "!";
                } else {
                    $listAllHostsTemplate = NagiosHostTemplateInheritancePeer::doSelect($c);
                    if (sizeof($listAllHostsTemplate) == 0) {
                        $this->addHostTemplateToHost("GENERIC_HOST", $hostName);
                    }
                    $success .= "The template '" . $templateHostName . "' deleted.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }
        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact to Hosts */
    public function deleteContactToHost($contactName, $hostName)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContact = (new NagiosContactPeer)->getByName($contactName);
            $targetHost = NagiosHostPeer::getByName($hostName);

            if (!$targetHost || !$targetContact) {
                $code = 1;
                $error .= (!$targetContact ? "The contact '" . $contactName . "'does not exist\n" : "The host '" . $hostName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostContactMemberPeer::HOST, $targetHost->getId());
                $c->add(NagiosHostContactMemberPeer::CONTACT, $targetContact->getId());
                $relationship = NagiosHostContactMemberPeer::doSelectOne($c);
                if ($relationship) {
                    $relationship->delete();
                    $success .= "The contact '" . $contactName . "' has been deleted.\n";
                } else {
                    $error .= "The contact '" . $contactName . "' doesn't link with this host : $hostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contactGroup to Hosts */
    public function deleteContactGroupToHost($contactGroupName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContactGp = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            $targetHost = NagiosHostPeer::getByName($hostName);

            if (!$targetHost || !$targetContactGp) {
                $code = 1;
                $error .= (!$targetContactGp ? "The contact group '" . $contactGroupName . "'does not exist\n" : "The host '" . $hostName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostContactgroupPeer::HOST, $targetHost->getId());
                $c->add(NagiosHostContactgroupPeer::CONTACTGROUP, $targetContactGp->getId());
                $relationship = NagiosHostContactGroupPeer::doSelectOne($c);
                if ($relationship) {
                    $relationship->delete();
                    $success .= "The contact group '" . $contactGroupName . "' has been deleted.\n";
                } else {
                    $error .= "The contact group '" . $contactGroupName . "' doesn't link with this host : $hostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - delEventBroker */
    public function delEventBroker($broker, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";

        try {
            // Check if exist
            $module_list = NagiosBrokerModulePeer::doSelect(new Criteria());
            foreach ($module_list as $module) {
                if ($module->getLine() == $broker) {
                    $brokerExists = $module;
                }
            }

            // Add broker
            if (isset($brokerExists)) {
                $brokerExists->delete();
                $success .= "EventBroker deleted\n";
            } else {
                $success .= "EventBroker not exists\n";
            }
        } catch (Exception $e) {
            $error .= $e->getMessage() . "\n";
        }

        return $this->getLogs($error, $success);
    }

    /* LILAC - Delete Host Group */
    public function deleteHostGroup($hostGroupName, $exportConfiguration = FALSE)
    {
        global $lilac;
        $error = "";
        $success = "";
        $code = 0;

        // Check for pre-existing contact with same name
        if ($lilac->hostgroup_exists($hostGroupName)) {
            // All is well for error checking, add the hostgroup into the db.
            $hostGroup = NagiosHostgroupPeer::getByName($hostGroupName);
            $hostGroup->delete();

            $success .= "Host group " . $hostGroupName . " deleted\n";

            if ($exportConfiguration) {
                $this->exportConfigurationToNagios($error, $success);
            }
        } else {
            $code = 1;
            $error .= "A host group with that name didn't exists!\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete HostGroup to Hosts template */
    public function deleteHostGroupToHostTemplate($hostGroupName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetHostGroup = NagiosHostgroupPeer::getByName($hostGroupName);
            $targetTemplateHost = NagiosHostTemplatePeer::getByName($templateHostName);

            if (!$targetHostGroup || !$targetTemplateHost) {
                $code = 1;
                $error .= (!$hostGroupName ? "The Host group '" . $targetHostGroup . "'does not exist\n" : "The Template host '" . $targetTemplateHost . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostgroupMembershipPeer::HOST_TEMPLATE, $targetTemplateHost->getId());
                $c->add(NagiosHostgroupMembershipPeer::HOSTGROUP, $targetHostGroup->getId());
                $membership = NagiosHostgroupMembershipPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The host group '" . $hostGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The host group '" . $hostGroupName . "' doesn't link with this host : $templateHostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete HostGroup to Host */
    public function deleteHostGroupToHost($hostGroupName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetHostGroup = NagiosHostgroupPeer::getByName($hostGroupName);
            $targetHost = NagiosHostPeer::getByName($hostName);

            if (!$targetHostGroup || !$targetHost) {
                $code = 1;
                $error .= (!$hostGroupName ? "The Host group '" . $targetHostGroup . "'does not exist\n" : "The host '" . $targetHost . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostgroupMembershipPeer::HOST, $targetHost->getId());
                $c->add(NagiosHostgroupMembershipPeer::HOSTGROUP, $targetHostGroup->getId());
                $membership = NagiosHostgroupMembershipPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The host group '" . $hostGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The host group '" . $hostGroupName . "' doesn't link with this host : $hostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact to Hosts template */
    public function deleteContactToHostTemplate($contactName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContact = (new NagiosContactPeer)->getByName($contactName);
            $targetTemplateHost = NagiosHostTemplatePeer::getByName($templateHostName);

            if (!$targetContact || !$targetTemplateHost) {
                $code = 1;
                $error .= (!$targetContact ? "The contact '" . $contactName . "'does not exist\n" : "The Template host '" . $targetTemplateHost . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostContactMemberPeer::TEMPLATE, $targetTemplateHost->getId());
                $c->add(NagiosHostcontactMemberPeer::CONTACT, $targetContact->getId());
                $membership = NagiosHostContactMemberPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The contact '" . $contactName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The contact '" . $contactName . "' doesn't link with this host : $templateHostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete contact group to Hosts template */
    public function deleteContactGroupToHostTemplate($contactGroupName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContactGroup = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            $targetTemplateHost = NagiosHostTemplatePeer::getByName($templateHostName);

            if (!$targetContactGroup || !$targetTemplateHost) {
                $code = 1;
                $error .= (!$targetContactGroup ? "The contact group '" . $contactGroupName . "'does not exist\n" : "The Template host '" . $targetTemplateHost . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostContactgroupPeer::HOST_TEMPLATE, $targetTemplateHost->getId());
                $c->add(NagiosHostcontactgroupPeer::CONTACTGROUP, $targetContactGroup->getId());
                $membership = NagiosHostContactgroupPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The contact group '" . $contactGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The contact group'" . $contactGroupName . "' doesn't link with this host : $templateHostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Inheritance Template to Hosts template */
    public function deleteInheritanceTemplateToHostTemplate($inheritanceTemplateName, $templateHostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetInheritanceTemplate = NagiosHostTemplatePeer::getByName($inheritanceTemplateName);
            $targetTemplateHost = NagiosHostTemplatePeer::getByName($templateHostName);

            if (!$targetInheritanceTemplate || !$targetTemplateHost) {
                $code = 1;
                $error .= (!$targetInheritanceTemplate ? "The  Inheritance Template '" . $inheritanceTemplateName . "'does not exist\n" : "The Template host '" . $targetTemplateHost . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosHostTemplateInheritancePeer::SOURCE_TEMPLATE, $targetTemplateHost->getId());
                $c->add(NagiosHostTemplateInheritancePeer::TARGET_TEMPLATE, $targetInheritanceTemplate->getId());
                $membership = NagiosHostTemplateInheritancePeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  Inheritance Template '" . $inheritanceTemplateName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  Inheritance Template'" . $inheritanceTemplateName . "' doesn't link with this host : $templateHostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Inheritance Template to Service template */
    public function deleteInheritServiceTemplateToServiceTemplate($inheritanceTemplateName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetInheritanceTemplate = NagiosServiceTemplatePeer::getByName($inheritanceTemplateName);
            $targetTemplateService = NagiosServiceTemplatePeer::getByName($templateServiceName);

            if (!$targetInheritanceTemplate || !$targetTemplateService) {
                $code = 1;
                $error .= (!$targetInheritanceTemplate ? "The  Inheritance Template '" . $inheritanceTemplateName . "'does not exist\n" : "The Template service '" . $templateServiceName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosServiceTemplateInheritancePeer::SOURCE_TEMPLATE, $targetTemplateService->getId());
                $c->add(NagiosServiceTemplateInheritancePeer::TARGET_TEMPLATE, $targetInheritanceTemplate->getId());
                $membership = NagiosServiceTemplateInheritancePeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  Inheritance Template '" . $inheritanceTemplateName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  Inheritance Template'" . $inheritanceTemplateName . "' doesn't link with this host : $templateServiceName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Contact Group to Service template */
    public function deleteContactGroupToServiceTemplate($contactGroupName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContactGroup = (new NagiosContactGroupPeer)->getByName($contactGroupName);
            $targetTemplateService = NagiosServiceTemplatePeer::getByName($templateServiceName);

            if (!$targetContactGroup || !$targetTemplateService) {
                $code = 1;
                $error .= (!$targetContactGroup ? "The  Contact Group '" . $contactGroupName . "'does not exist\n" : "The Template service '" . $templateServiceName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosServiceContactGroupMemberPeer::CONTACT_GROUP, $targetContactGroup->getId());
                $c->add(NagiosServiceContactGroupMemberPeer::TEMPLATE, $targetTemplateService->getId());
                $membership = NagiosServiceContactGroupMemberPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  Contact Group '" . $contactGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  Contact Group '" . $contactGroupName . "' doesn't link with this host : $templateServiceName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete Contact  to Service template */
    public function deleteContactToServiceTemplate($contactName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetContact = (new NagiosContactPeer)->getByName($contactName);
            $targetTemplateService = NagiosServiceTemplatePeer::getByName($templateServiceName);

            if (!$targetContact || !$targetTemplateService) {
                $code = 1;
                $error .= (!$targetContact ? "The  Contact  '" . $contactName . "'does not exist\n" : "The Template service '" . $templateServiceName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosServiceContactMemberPeer::CONTACT, $targetContact->getId());
                $c->add(NagiosServiceContactMemberPeer::TEMPLATE, $targetTemplateService->getId());
                $membership = NagiosServiceContactMemberPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  Contact '" . $contactName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  Contact '" . $contactName . "' doesn't link with this host : $templateServiceName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete service group to Service template */
    public function deleteServiceGroupToServiceTemplate($serviceGroupName, $templateServiceName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetServiceGroup = (new NagiosServiceGroupPeer)->getByName($serviceGroupName);
            $targetTemplateService = NagiosServiceTemplatePeer::getByName($templateServiceName);

            if (!$targetServiceGroup || !$targetTemplateService) {
                $code = 1;
                $error .= (!$targetServiceGroup ? "The  service group  '" . $serviceGroupName . "'does not exist\n" : "The Template service '" . $templateServiceName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosServiceGroupMemberPeer::SERVICE_GROUP, $targetServiceGroup->getId());
                $c->add(NagiosServiceGroupMemberPeer::TEMPLATE, $targetTemplateService->getId());
                $membership = NagiosServiceGroupMemberPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  service group '" . $serviceGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  service group '" . $serviceGroupName . "' doesn't link with this host : $templateServiceName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Delete service group to Service template */
    public function deleteServiceGroupToServiceInHost($serviceGroupName, $serviceName, $hostName, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        try {
            $targetServiceGroup = (new NagiosServiceGroupPeer)->getByName($serviceGroupName);
            $targetService = NagiosServicePeer::getByHostAndDescription($hostName, $serviceName);

            if (!$targetServiceGroup || !$targetService) {
                $code = 1;
                $error .= (!$targetServiceGroup ? "The  service group  '" . $serviceGroupName . "'does not exist\n" : "The service '" . $serviceName . "'does not exist\n");
            } else {
                $c = new Criteria();
                $c->add(NagiosServiceGroupMemberPeer::SERVICE_GROUP, $targetServiceGroup->getId());
                $c->add(NagiosServiceGroupMemberPeer::SERVICE, $targetService->getId());
                $membership = NagiosServiceGroupMemberPeer::doSelectOne($c);
                if ($membership) {
                    $membership->delete();
                    $success .= "The  service group '" . $serviceGroupName . "' has been deleted.\n";
                } else {
                    $code = 1;
                    $error .= "The  service group '" . $serviceGroupName . "' doesn't link with this service : $serviceName in this host $hostName.\n";
                }
            }
        } catch (Exception $e) {
            $code = 1;
            $error .= $e->getMessage() . "\n";
        }

        $logs = $this->getLogs($error, $success);

        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - duplicate Service */
    public function duplicateService($hostName, $service, $exportConfiguration = FALSE)
    {
        $error = "";
        $success = "";
        $code = 0;

        $host = NagiosHostPeer::getByName($hostName);
        $services = NagiosServicePeer::getByHostAndDescription($hostName, $service->name);

        if (!$host) {
            $error .= "The host : '" . $host->getName() . "' doesn't exist";
        }
        if (!$services) {
            $error .= "The service : '" . $services->name . "' doesn't exist";
        }

        if (isset($host) && isset($services)) {
            foreach ($service->targets as $tar) {
                $newService = $services->duplicate();
                if (isset($tar->name)) {
                    $newService->setDescription($tar->name);
                    $newService->save();
                }
                if (isset($tar->command)) {
                    if ((new NagiosCommandPeer)->getByName($tar->command)) {
                        $command = (new NagiosCommandPeer)->getByName($tar->command);
                        $newService->setCheckCommand($command->getId());
                        $newService->save();
                    }
                } else {
                    $code = 1;
                    $error .= "Command empty ";
                }
                if (isset($tar->parameters)) {
                    foreach ($newService->getNagiosServiceCheckCommandParameters() as $initParam) {
                        $initParam->delete();
                    }
                    foreach ($tar->parameters as $params) {
                        $newService->addCheckCommandParameter($params);
                    }
                    $newService->setDescription($tar->name);
                    $newService->save();
                }
                $success .= "$service->name was duplicated by " . $newService->getDescription();
            }
        } else {
            $error .= "One of this element is wrong : HostsName/Service";
            $code = 1;
        }

        $logs = $this->getLogs($error, $success);
        return array("code" => $code, "description" => $logs);
    }

    /* LILAC - Export Nagios Configuration */
    public function exportConfiguration($jobName = "Nagios Export")
    {
        $error = "";
        $success = "";

        $this->exportConfigurationToNagios($error, $success, $jobName);

        return $this->getLogs($error, $success);
    }

    /* LIVESTATUS - List backends */
    public function listNagiosBackends()
    {
        $backends = getRGMConfig("sockets", "array");
        $backends_json = array();
        for ($i = 0; $i < count($backends); $i++) {
            $backends_json[$i]["id"] = $i;
            $backends_json[$i]["backend"] = $backends[$i];
        }
        return $backends_json;
    }

    /**
     * @brief list LiveStatus Nagios objects
     * @details LiveStatus is a the request interface of Checkmk, a Nagios plugin
     *      that allows to get all the monitored host's and service's data,
     *      including live data, using LiveStatus Query Language (LQL)
     *      see https://checkmk.com/cms_livestatus.html
     * @param  string $object the LV object to query (hosts, services, etc.)
     * @param  string $backendid the LV backend id (as specified in rgmweb's $sockets
     *      array definition)
     *      defaults: null => all sockets will be processed
     * @param  boolean $columns specify the columns to retrieve
     *      default: false => all available columns are returned
     * @param  boolean $filters specify a query filter to apply
     *      default: false => no filter applied
     * @return  array An array containing the query result
     */
    /* LIVESTATUS - List nagios objects */
    public function listNagiosObjects($object, $backendid = NULL, $columns = FALSE, $filters = FALSE)
    {
        // loop on each socket
        $sockets = getRGMConfig("sockets", "array");

        if ($backendid != NULL) {
            $sockets = array_slice($sockets, $backendid, 1);
        }
        if (sizeof($sockets, 0) > 0) {
            foreach ($sockets as $socket) {
                $socket_parts = explode(":", $socket);
                $socket_type = $socket_parts[0];
                $socket_address = $socket_parts[1];
                $socket_port = $socket_parts[2];
                $socket_path = $socket_parts[3];
                $socket_name = $socket;

                // check if socket disabled
                if (isset($socket_parts[4]) && $backendid == NULL) {
                    continue;
                }
                // check if socket is up
                if ($this->checkLiveStatusSocket($socket_type, $socket_address, $socket_port, $socket_path)) {
                    if ($socket_port == -1) {
                        $socket_port = "";
                        $socket_address = "";
                        $socket_name = "default";
                    }
                    $options = array(
                        'socketType' => $socket_type,
                        'socketAddress' => $socket_address,
                        'socketPort' => $socket_port,
                        'socketPath' => $socket_path
                    );
                    // construct mklivestatus request, and get the response
                    $client = new Client($options);
                    try {
                        // get objects
                        $result[$socket_name] = $client->get($object);
                        // get columns
                        if ($columns) {
                            $result[$socket_name] = $result[$socket_name]->columns($columns);
                        }
                        // get filters
                        if (is_array($filters)) {
                            foreach ($filters as $filter) {
                                $result[$socket_name] = $result[$socket_name]->filter($filter);
                            }
                        }
                        // set user
                        $result[$socket_name] = $result[$socket_name]->authUser($this->authUser);
                        // execute
                        $result[$socket_name] = $result[$socket_name]->executeAssoc();
                    } catch (Exception $e) {
                        $result['error'] = $e->getMessage();
                        var_dump($e);
                    }
                } else {
                    $result['error'] = 'Error: host down';
                }
            }
        } else {
            $result['error'] = 'Error: no LV socket';
        }
        // response for the Ajax call
        return $result;
    }

    /* LIVESTATUS - List nagios states */
    public function listNagiosStates($backendid = NULL, $filters = FALSE)
    {
        // loop on each socket
        $sockets = getRGMConfig("sockets", "array");

        $result = array();
        $result["hosts"]["pending"] = 0;
        $result["hosts"]["up"] = 0;
        $result["hosts"]["down"] = 0;
        $result["hosts"]["unreachable"] = 0;
        $result["hosts"]["unknown"] = 0;
        $result["services"]["pending"] = 0;
        $result["services"]["ok"] = 0;
        $result["services"]["warning"] = 0;
        $result["services"]["critical"] = 0;
        $result["services"]["unknown"] = 0;

        if ($backendid != NULL) {
            $sockets = array_slice($sockets, $backendid, 1);
        }

        foreach ($sockets as $socket) {
            $socket_parts = explode(":", $socket);
            $socket_type = $socket_parts[0];
            $socket_address = $socket_parts[1];
            $socket_port = $socket_parts[2];
            $socket_path = $socket_parts[3];

            // check if socket disabled
            if (isset($socket_parts[4]) && $backendid == NULL) {
                continue;
            }

            // check if socket is up
            if ($this->checkLiveStatusSocket($socket_type, $socket_address, $socket_port, $socket_path)) {
                if ($socket_port == -1) {
                    $socket_port = "";
                    $socket_address = "";
                }
                $options = array(
                    'socketType' => $socket_type,
                    'socketAddress' => $socket_address,
                    'socketPort' => $socket_port,
                    'socketPath' => $socket_path
                );

                // construct mklivestatus request, and get the response
                $client = new Client($options);

                // get all host PENDING
                $test = $client
                    ->get('hosts')
                    ->filter('has_been_checked = 0');

                // get filters
                if ($filters) {
                    foreach ($filters as $filter) {
                        $test = $test->filter($filter);
                    }
                }

                // set user
                $test = $test->authUser($this->authUser);

                // execute
                $test = $test->execute();
                $result["hosts"]["pending"] += count($test) - 1;

                // construct mklivestatus request, and get the response
                $response = $client
                    ->get('hosts')
                    ->stat('state = 0')
                    ->stat('state = 1')
                    ->stat('state = 2')
                    ->stat('state = 3')
                    ->filter('has_been_checked = 1');

                // get filters
                if ($filters) {
                    foreach ($filters as $filter) {
                        $response = $response->filter($filter);
                    }
                }

                // set user
                $response = $response->authUser($this->authUser);

                // execute
                $response = $response->execute();

                $result["hosts"]["up"] += $response[0][0];
                $result["hosts"]["down"] += $response[0][1];
                $result["hosts"]["unreachable"] += $response[0][2];
                $result["hosts"]["unknown"] += $response[0][3];

                // get all service PENDING
                $test = $client
                    ->get('services')
                    ->filter('has_been_checked = 0');

                // get filters
                if ($filters) {
                    foreach ($filters as $filter) {
                        $test = $test->filter($filter);
                    }
                }

                // set user
                $test = $test->authUser($this->authUser);

                // execute
                $test = $test->execute();
                $result["services"]["pending"] += count($test) - 1;

                // construct mklivestatus request, and get the response
                $response = $client
                    ->get('services')
                    ->stat('state = 0')
                    ->stat('state = 1')
                    ->stat('state = 2')
                    ->stat('state = 3')
                    ->filter('has_been_checked = 1');

                // get filters
                if ($filters) {
                    foreach ($filters as $filter) {
                        $response = $response->filter($filter);
                    }
                }

                // set user
                $response = $response->authUser($this->authUser);

                // execute
                $response = $response->execute();

                $result["services"]["ok"] += $response[0][0];
                $result["services"]["warning"] += $response[0][1];
                $result["services"]["critical"] += $response[0][2];
                $result["services"]["unknown"] += $response[0][3];
            }
        }

        // response for the Ajax call
        return $result;
    }

    /**
     * @brief  enumerates all defined "tags" used to classify one-liners items
     * @details  enumerates all defined "tags" used to classify one-liners items
     * @return  array an array of available tag names
     */
    public function listOneLinersTags()
    {
        global $database_rgmweb;
        $tags = array();
        $stmt = sqlrequest($database_rgmweb, "SELECT name FROM ol_tags");
        while ($sql_raw = mysqli_fetch_array($stmt)) {
            $tags[] = $sql_raw[0];
        }
        return $tags;
    }

    /**
     * @brief  enumerates one-liners items or a subset filtered by tags
     * @param  string $tagsFilter a comma separated string of tag names, or null if no filter specified
     * @return  array an array of array of one-liner items
     */
    public function listOneLinersItems($tagsFilter = '')
    {
        global $database_rgmweb;
        $tags_ids = array();
        $items = array();

        if ($tagsFilter != '') {
            $tags = explode(',', $tagsFilter);
            foreach ($tags as $tagname) {
                $stmt = sqlrequest($database_rgmweb, "SELECT id FROM ol_tags WHERE name = '$tagname'");
                $sql_raw = mysqli_fetch_array($stmt);
                if ($sql_raw) {
                    $tags_ids[] = $sql_raw[0];
                }
            }
        }
        $statement = "SELECT DISTINCT it.name AS `name`, it.command AS `command` FROM rgmweb.ol_items AS it INNER JOIN ol_items_tags AS ta ON it.id = ta.id_item";
        if (count($tags_ids) > 0) {
            $statement .= ' WHERE ta.id_tags = ' . implode(' OR ta.id_tags = ', $tags_ids);
        }
        $stmt = sqlrequest($database_rgmweb, $statement);
        while ($sql_raw = mysqli_fetch_array($stmt)) {
            $items[] = array(
                'name' => $sql_raw[0],
                'command' => $sql_raw[1]
            );
        }
        return $items;
    }
}
