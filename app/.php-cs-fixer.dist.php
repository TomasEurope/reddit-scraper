<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'align_single_space_minimal'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'no_extra_blank_lines' => true,
        'no_whitespace_in_blank_line' => true,
        'single_quote' => true,
        'multiline_whitespace_before_semicolons' => false,
        'line_ending' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'trim_array_spaces' => true,
        'visibility_required' => ['elements' => ['property', 'method']],
        'phpdoc_trim' => true,
        'phpdoc_align' => ['align' => 'vertical'],
    ]);
