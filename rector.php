<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\Name\FixClassCaseSensitivityNameRector;
use Rector\CodeQuality\Rector\PropertyFetch\ExplicitMethodCallOverMagicGetSetRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\FuncCall\GetCalledClassToStaticClassRector;
use Rector\PHPOffice\Set\PHPOfficeSetList;
use Rector\PostRector\Rector\NameImportingPostRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\Set\ValueObject\SetList;
use Rector\Core\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->phpVersion(PhpVersion::PHP_74);

    // get parameters
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests/unit',
    ]);

    // Define what rule sets will be applied
    $rectorConfig->sets([SetList::DEAD_CODE]);
    $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
        'Phalcon\Cache' => 'Phalcon\Cache\Cache',
        'Phalcon\Collection' => 'Phalcon\Support\Collection',
        'Phalcon\Config' => 'Phalcon\Config\Config',
        'Phalcon\Container' => 'Phalcon\Container\Container',
        'Phalcon\Crypt' => 'Phalcon\Encryption\Crypt',
        'Phalcon\Debug' => 'Phalcon\Support\Debug',
        'Phalcon\Di' => 'Phalcon\Di\Di',
        'Phalcon\DI' => 'Phalcon\Di\Di',
        'Phalcon\Escaper' => 'Phalcon\Html\Escaper',
        'Phalcon\Filter' => 'Phalcon\Filter\Filter',
        'Phalcon\Helper' => 'Phalcon\Support\Helper',
        'Phalcon\Loader' => 'Phalcon\Autoload\Loader',
        'Phalcon\Logger' => 'Phalcon\Logger\Logger',
        'Phalcon\Registry' => 'Phalcon\Support\Registry',
        'Phalcon\Security' => 'Phalcon\Encryption\Security',
        'Phalcon\Text' => 'Phalcon\Support\Helper',
        'Phalcon\Url' => 'Phalcon\Mvc\Url',
        'Phalcon\Validation' => 'Phalcon\Filter\Validation',
        'Phalcon\Version' => 'Phalcon\Support\Version',
        'Phalcon\Acl\ComponentAware' => 'Phalcon\Acl\ComponentAwareInterface',
        'Phalcon\Acl\RoleAware' => 'Phalcon\Acl\RoleAwareInterface',
    ]);

    // get services (needed for register a single rule)
    // $services = $containerConfigurator->services();

    // register a single rule
    // $services->set(TypedPropertyRector::class);
};
