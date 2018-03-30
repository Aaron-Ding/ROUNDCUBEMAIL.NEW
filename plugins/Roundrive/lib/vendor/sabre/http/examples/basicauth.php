<?php

/**
 * This example shows how to do Basic authentication.
 * *
 * @copyright Copyright (C) 2009-2014 fruux GmbH. All rights reserved.
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */

$userList = [
    "user1" => "password",
    "user2" => "password",
];

use
    Sabre\HTTP\Sapi,
    Sabre\HTTP\Response,
    Sabre\HTTP\Auth;


// Find the autoloader
$paths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/vendor/autoload.php',

];

foreach($paths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}

$request = Sapi::getRequest();
$response = new Response();

$basicAuth = new Auth\Basic("Locked down area", $request, $response);
if (!$userPass = $basicAuth->getCredentials()) {

    // No username or password given
    $basicAuth->requireLogin();

} elseif (!isset($userList[$userPass[0]]) || $userList[$userPass[0]] !== $userPass[1]) {

    // Username or password are incorrect 
    $basicAuth->requireLogin();
} else {

    // Success !
    $response->setBody('You are logged in!');

}

// Sending the response
Sapi::sendResponse($response);

