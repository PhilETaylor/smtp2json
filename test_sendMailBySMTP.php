<?php
require 'vendor/autoload.php';

// Or to use the Echo Logger
$logger = new Swift_Plugins_Loggers_EchoLogger();

$message = new Swift_Message();
$message->addTo('phil@phil-taylor.com');
$message->setFrom('me@phil-taylor.com');
$message->setSubject('Audit Mailer Test');
$message->setBody('https://thecabanajersey.com/');

$transport = new Swift_SmtpTransport();
$transport->setHost('127.0.0.1');

$mailer = new Swift_Mailer($transport);
$mailer->registerPlugin(new Swift_Plugins_LoggerPlugin($logger));
$res = $mailer->send($message);

echo $logger->dump();