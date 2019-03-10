<?php

use NPF\Logger;

$logger = new Logger\Logger();

$logger->routes->attach(new Logger\Routes\FileRoute([
    'isEnable' => true,
    'filePath' => 'logs/default.log',
]));

/*
$logger->info("INFO");
$logger->alert("ALERT");
$logger->error("ERROR");
$logger->debug("DEBUG");
$logger->notice("Notice");
$logger->warning("Warning");
$logger->critical("CRITICAL");
$logger->emergency("Emergency");

*/
