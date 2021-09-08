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

include_once('../include/Slim/Slim.php');
include_once('/srv/rgm/rgmweb/include/database.php');
include_once('api_common.class.php');
include_once('api_methods.class.php');

// On trace tout uniquement en mode TRACE
if (RgmConfig::get('rgm_log_level') == 5) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}

Slim::registerAutoloader();
$app = new Slim();

/**
 * getAuthToken and checkAuthToken are *not* wrapped through addRoute()
 * as they handle token stuff an addRoute() assume the token is *already*
 * generated.
 */
$app->map('/token', function () {
    RgmApiCommon::getAuthToken();
})->via('POST');
$app->map('/token', function () {
    RgmApiCommon::checkAuthToken();
})->via('GET');
$app->map('/testing/(:test)', function ($test) use ($app) {
    $res = array();

    $r = $app->request();
    $q = $r->params("q", "noquestion");
    $ct = $r->getContentType();
    $mt = $r->getMediaType();
    $path = $r->getPath();
    $method = $r->getMethod();

    $res[] = array("test-value" => $test);
    $res[] = array("q" => $q);
    $res[] = array("content-type" => $ct);
    $res[] = array("media-type" => $mt);
    $res[] = array("path" => $path);
    $res[] = array("method" => $method);

    $app->response->headers->set('Content-Type', 'application/json');
    $app->response->setStatus(200);

    echo json_encode($res, JSON_PRETTY_PRINT);
})->via('GET');

// Now includes all routes
include_once 'routes.php';

$app->run();
