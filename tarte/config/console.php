<?php
return array(
    'basePath' => __DIR__ . '/..',
    'name' => 'Tarte/0.1',
    'preload' => array(
        'log',
        'zendHelper',
    ),
    'import' => array(
        'application.models.*',
        'application.models.Twitter.*',
        'application.models.Dictionary.*',
        'application.components.*',
        'application.components.OAuth.*',
        'application.components.Twitter.*',
        'application.vendors.*',
    ),
    'components' => array(
        'db' => array(
            'connectionString'      => 'pgsql:host=localhost;port=5432;dbname=tarte',
            'username'              => 'tarte',
            'password'              => 'tarte',
            'enableParamLogging'    => false,
            'enableProfiling'       => false,
        ),
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class'  => 'CFileLogRoute',
                    'levels' => 'error, warning, info, profile',
                ),
                array(
                    'class'  => 'ConsoleLogRoute',
                    'levels' => 'error, warning, info',
                ),
            ),
        ),
        'oauth' => array(
            'class' => 'OAuth',
            'consumer' => require(__DIR__ . '/oauth.php'),
        ),
        'wakuflow' => array(
            'class' => 'Wakuflow',
            'path' => 'application.vendors.wakuflow',
            'exec' => 'wakuflow',
            'font' => 'font.otf',
            'font2' => 'animal.ttf',
            'font3' => 'animal.ttf',
        ),
        'zendHelper' => array(
            'class' => 'ZendHelper',
        ),
        'cliColor' => array(
            'class' => 'ext.yii-cli-color.components.KCliColor',
        ),
    ),
    'modules' => array(
	),
    'params' => array(
        'mecab'     => '/usr/local/bin/mecab',
        'accounts'  => require(__DIR__ . '/accounts.php'),
        'twitpic'   => require(__DIR__ . '/twitpic.php'),
    ),
);
