<?php

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHPUnit75Migration:risky' => true,
        'php_unit_dedicate_assert' => ['target' => '5.6'],
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'protected_to_private' => false,
        'native_constant_invocation' => true,
        'combine_nested_dirname' => true,
        'list_syntax' => ['syntax' => 'short'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__.'/src')
    )
;