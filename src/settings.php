<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],
        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
        // LineBot settings
        'bot' => [
            'channelToken' => getenv('LINEBOT_CHANNEL_TOKEN') ?: '39OJDQB8nK89CIRnT2IAardiIrr5OmxaAunEUgfU0KUOBSkQ1wpT1b9P7lkPLUXYXxLVrV/4Zvw/aSuL1EJ5CS3IzWdM6KnqDrkDJVD4X5BpGu0JOgVhriLzoAyTNXtaEN79zoGaMiFSX86dQcY8VAdB04t89/1O/w1cDnyilFU=',
            'channelSecret' => getenv('LINEBOT_CHANNEL_SECRET') ?: 'a9bcf76a893ca99ad15a1be0d417197e',
            'apiEndpointBase' => getenv('LINEBOT_API_ENDPOINT_BASE'),
        ],
        // PDO settings
        'db' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'dbname' => 'shiftbot',
            'user' => 'shiftbot',
            'password' => '7FSl32+a'
        ],

        'title' => '非公式エミュリボン',
        'siteBaseUrl' => 'https://kazy111.info/line_bot/',
    ],
];
