<?php
/*
* Templates need the same rules as the PHP sources, minus the four that
* misbehave on files with inline HTML.
*/
$config = require __DIR__ . '/.php-cs-fixer.php';
return $config
    ->setRules([
        ...$config->getRules(),
        // throws on switch/case in alternative syntax
        'no_break_comment' => false,
        // flattens PHP nested inside indented HTML
        'statement_indentation' => false,
        'blank_line_after_opening_tag' => false,
        'single_line_comment_spacing' => false,
    ])
    ->setCacheFile('.php-cs-fixer.phtml.cache')
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/app'])
            ->name(['*.phtml'])
            ->notName('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true),
    );
