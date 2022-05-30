<?php
return [
    'id' => 'geoip_ms',
    // basePath (базовый путь) приложения будет каталог `geoip_ms`
    'basePath' => __DIR__,
    // это пространство имен где приложение будет искать все контроллеры
    'bootstrap' => ['log'],
    'controllerNamespace' => 'geoip_ms\controllers',
    // установим псевдоним '@geoip_ms', чтобы включить автозагрузку классов из пространства имен 'geoip_ms'
    'aliases' => [
        '@geoip_ms' => __DIR__,
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\MemCache',
            'servers' => [
                [
                    'host' => 'localhost',
                    'port' => 11211,
                    'persistent' => true,
                ],
            ],
        ],
        'errorHandler' =>  [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'logFile' => '@runtime/logs/runtime.log',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'request' => [
            'class' => 'yii\web\Request',
            'enableCookieValidation' => false,
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],
        'response' => [
            'class'=>'yii\web\Response',
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => [
                    'class' => 'yii\web\JsonResponseFormatter',
                    'prettyPrint' => YII_DEBUG, // используем "pretty" в режиме отладки
                    'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    // ...
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
        ],
   ],
    'params' => [
        //For prevent permanent waiting for very frequency queries to external API
        //issued only one query by time. It may produce a long queue & time for executing query
        'max_wait_timeout'=>15,

        //Declaration of external API
        //Name of field country_code for return result
        'country_code_name'=>'country_code',
        //url for external api service
        'ext_geoip_url'=> 'http://ip-api.com/json/',
        //url part after queried IP-address
        'ext_adding' => '?fields=status,message,countryCode,query',
        //Name of method (see API doc & names for methods yii2)
        'ext_geoip_method' => 'GET',
        //Max count of queries in 1 minute
        'max_frequency' => '45',
        //Field name and value for good result or error
        'response_status'=>['ok'=>['key'=>'status',
            'value'=>'success'],
            'error'=>['key'=>'status',
                'value'=>'fail']
        ],
        //Name of field for error message in external geoip
        'response_error'=>['message'=>'message'],
        //Names of fields for fields result in external geoip
        'response_ok'=>[//'country'=>'',
            //Two-letter country code ISO 3166-1 alpha-2
            'country_code'=>'countryCode',
            //'region'=>'',
            //'region_code'=>''
        ],
    ]
];
