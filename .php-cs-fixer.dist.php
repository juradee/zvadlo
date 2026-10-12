<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/app')
    ->in(__DIR__.'/bin')
    ->in(__DIR__.'/tests')
    ->exclude('_output')
    ->exclude('_generated')
    ->name('console')
    ->name('latte-lint');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'not_operator_with_successor_space' => true,
        'phpdoc_separation' => false,
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/temp/.php-cs-fixer.cache')
    ->setLineEnding("\n");
