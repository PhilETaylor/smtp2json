#!/usr/bin/php
<?php

use GuzzleHttp\Client;
use Symfony\Component\Dotenv\Dotenv;

require 'vendor/autoload.php';
require('src/SMTPServer.php');

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');
$env = getenv('ENV');

$hp = new SMTPServer();
$client = new Client();

$proxyOptions = [];

if ($env === 'dev') {
    $hp->logFile = '/app/log.txt'; // Log the transaction files (optional)
    $proxyOptions = [
        'proxy' => 'tcp://host.docker.internal:8888',
        'verify' => false
    ];
}

$hp->receive();

$response = $client->post(getenv('ENDPOINT'), array_merge($proxyOptions, [
            'headers' => [
                'Content-Type' => 'application/json',
                'accept' => 'application/json'
            ],
            'body' => $hp->getJSON()
        ]
    )
);

if ($env === 'dev') {
    file_put_contents('/app/log/emails.txt', $hp->getJSON(), FILE_APPEND);
    file_put_contents('/app/log/res.txt', $response->getBody()->getContents(), FILE_APPEND);
}