<?php

return [
    'database' => [
        'adapter' => Phalcon\Db\Adapter\Pdo\Sqlite::class,
        'user' => 'marcelo',
        'password' => 'marcelo',
        'options' => [],

        // see bug https://github.com/Codeception/Codeception/issues/3319
        'dbname' => dirname(dirname(dirname(dirname(__DIR__)))) . '/_data/base.db',
        //        'dbname'  => dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/:memory:',
        //        'dbname'  => ':memory:',
    ],
];
