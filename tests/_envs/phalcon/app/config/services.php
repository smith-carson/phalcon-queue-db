<?php
/**
 * @var \Phalcon\Di\FactoryDefault $di
 * @var \Phalcon\Config\Adapter\Php $config
 */

$di->setShared('db', function() use ($config) {
    /** @var \Phalcon\Db\Adapter\Pdo $adapter */
    $db_config = $config->database->toArray();
    $adapter = $db_config['adapter'];
    unset($db_config['adapter']);
    return new $adapter($db_config);
});