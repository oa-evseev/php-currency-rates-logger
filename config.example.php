<?php
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=database;charset=utf8',
        'username' => 'username',
        'password' => 'passoword',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'primary_currency' => 'GBP',
    'base_currency' => 'EUR',
    'notification_emails' => [],

    'ecb_url' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',

    'ecb_currency_filter_mode' => 'blacklist',
    'ecb_currency_filter_list' => [],

    'custom_providers' => [
        'CrcProvider' => [
            'api_url' => 'https://apim.bccr.fi.cr/SDDE/api/Bccr.Ge.SDDE.Publico.Indicadores.API',
            'api_token' => 'YOUR_TOKEN',
            'indicator_buy' => '317',
            'indicator_sell' => '318',
            'language' => 'ES',
            'timeout' => 20,
        ],
        'AmdProvider' => [
            'api_url' => 'https://api.cba.am/exchangerates.asmx',
            'source_currency' => 'USD',
            'target_currency' => 'AMD',
            'timeout' => 20,
        ],
    ],
    'sanity_checks' => [
        // 'CRC' => [
        //     'max_relative_change' => 0.3,
        // ],
    ],
];

?>
