<?php
use Phalcon\Config\Adapter\Php as Config;
use Phalcon\Di\FactoryDefault as DefaultDi;
use Phalcon\Mvc\Application;

$config = new Config(__DIR__ . '/config.php');

//include __DIR__ . '/loader.php';

$di = new DefaultDi();
include __DIR__ . '/services.php';

return new Application($di);
