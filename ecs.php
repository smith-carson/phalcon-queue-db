<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\NoUnneededImportAliasFixer;
use PhpCsFixer\Fixer\Import\SingleImportPerStatementFixer;
use PhpCsFixer\Fixer\NamespaceNotation\CleanNamespaceFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->parallel();

    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $ecsConfig->sets([
        SetList::SPACES,
        SetList::ARRAY,
        SetList::PSR_12,
        SetList::NAMESPACES
    ]);

    $ecsConfig->rule(NoUnusedImportsFixer::class);
    $ecsConfig->rule(FullyQualifiedStrictTypesFixer::class);
    $ecsConfig->rule(NoUnneededImportAliasFixer::class);
    $ecsConfig->rule(CleanNamespaceFixer::class);
    $ecsConfig->rule(SingleImportPerStatementFixer::class);
    $ecsConfig->ruleWithConfiguration(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ]);

};
