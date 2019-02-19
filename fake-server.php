#!/usr/bin/php
<?php
require('fakeSMTP.php');

$hp = new fakeSMTP;
$hp->serverHello = 'my.org ESMTP Postfix'; // Server identity (optional)
$hp->logFile = '/app/log.txt'; // Log the transaction files (optional)
$hp->receive();
if (!$hp->mail['rawEmail']) {
    exit; // Script failed to receive a complete transaction
}

$email = json_encode($hp->mail);

$STDOUT = fopen('/dev/stdout', 'wb');
fwrite($STDOUT, $email . "\n");