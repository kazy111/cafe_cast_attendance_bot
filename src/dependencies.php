<?php
// DIC configuration

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;

$container = $app->getContainer();
// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};
// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// line bot
$container['bot'] = function ($c) {
    $settings = $c->get('settings');
    $channelSecret = $settings['bot']['channelSecret'];
    $channelToken = $settings['bot']['channelToken'];
    $apiEndpointBase = $settings['bot']['apiEndpointBase'];
    $bot = new LINEBot(new CurlHTTPClient($channelToken), [
        'channelSecret' => $channelSecret,
        'endpointBase' => $apiEndpointBase, // <= Normally, you can omit this
    ]);
    return $bot;
};

// PDO
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    $db = new PDO($settings['driver'].':host='.$settings['host'].';port=5432;dbname='.$settings['dbname'].';user='.$settings['user'].';password='.$settings['password']);
    return $db;
};

// setting
