<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

// These transformations do not need an application or a database.
uses(PHPUnit\Framework\TestCase::class);

it('keeps truncated text within the configured limit including its suffix', function (string $value, array $options, string $expected, int $limit): void {
    $result = Maho_FeedManager_Model_Transformer::pipeline($value, [
        ['transformer' => 'truncate', 'options' => $options],
    ]);

    expect($result)->toBe($expected);
    expect(mb_strlen($result))->toBeLessThanOrEqual($limit);
})->with([
    'short_suffix_plain' => ['abcdef', ['max_length' => '2', 'suffix' => '...'], 'ab', 2],
    'short_suffix_word' => ['alpha beta gamma', ['max_length' => '2', 'suffix' => '...', 'word_boundary' => '1'], 'al', 2],
    'zero_suffix' => ['abcdef', ['max_length' => '0', 'suffix' => '...'], '', 0],
    'multibyte_short_suffix' => ['é漢字猫犬', ['max_length' => '2', 'suffix' => '...'], 'é漢', 2],
    'ordinary_suffix' => ['abcdefghij', ['max_length' => '7', 'suffix' => '...'], 'abcd...', 7],
    'ordinary_word' => ['alpha beta gamma', ['max_length' => '11', 'suffix' => '...', 'word_boundary' => '1'], 'alpha...', 11],
    'no_truncation' => ['abc', ['max_length' => '7', 'suffix' => '...'], 'abc', 7],
    'equal_suffix' => ['abcdef', ['max_length' => '3', 'suffix' => '...'], '...', 3],
    'zero_no_suffix' => ['abcdef', ['max_length' => '0'], '', 0],
    'negative_limit' => ['abcdef', ['max_length' => -1, 'suffix' => '...', 'word_boundary' => false], '', 0],
    'negative_empty_text' => ['', ['max_length' => -1, 'suffix' => '...', 'word_boundary' => false], '', 0],
    'fitting_text_with_long_suffix' => ['ab', ['max_length' => 2, 'suffix' => 'very long', 'word_boundary' => false], 'ab', 2],
    'unicode_suffix_omitted' => ['é漢字猫犬', ['max_length' => 1, 'suffix' => '終端', 'word_boundary' => false], 'é', 1],
    'unicode_suffix_equal' => ['é漢字猫犬', ['max_length' => 2, 'suffix' => '終端', 'word_boundary' => false], '終端', 2],
    'unicode_suffix_fits' => ['é漢字猫犬', ['max_length' => 3, 'suffix' => '終端', 'word_boundary' => false], 'é終端', 3],
    'unicode_word_boundary' => ['é漢 猫犬 狐狸', ['max_length' => 7, 'suffix' => '…', 'word_boundary' => '1'], 'é漢 猫犬…', 7],
    'combining_character_codepoints' => ['éxy', ['max_length' => 1, 'suffix' => '', 'word_boundary' => false], 'e', 1],
    'trim_truncated_prefix' => ['  abcdef  ', ['max_length' => 5, 'suffix' => '.', 'word_boundary' => false], 'ab.', 5],
    'fitting_whitespace_unchanged' => [' ab ', ['max_length' => 4, 'suffix' => 'long suffix', 'word_boundary' => false], ' ab ', 4],
    'numeric_string_limit' => ['abcdef', ['max_length' => '2', 'suffix' => '', 'word_boundary' => false], 'ab', 2],
    'float_limit' => ['abcdef', ['max_length' => 2.9, 'suffix' => '', 'word_boundary' => false], 'ab', 2],
    'false_limit' => ['abcdef', ['max_length' => false, 'suffix' => '', 'word_boundary' => false], '', 0],
    'true_limit' => ['abcdef', ['max_length' => true, 'suffix' => '', 'word_boundary' => false], 'a', 1],
    'nonnumeric_limit' => ['abcdef', ['max_length' => 'nonnumeric', 'suffix' => '', 'word_boundary' => false], '', 0],
    'null_default_limit' => ['abcdef', ['max_length' => null, 'suffix' => '...', 'word_boundary' => false], 'abcdef', 255],
    'numeric_suffix' => ['abcdef', ['max_length' => 3, 'suffix' => 12, 'word_boundary' => false], 'a12', 3],
    'word_boundary_zero_string' => ['alpha beta gamma', ['max_length' => 11, 'suffix' => '...', 'word_boundary' => '0'], 'alpha be...', 11],
    'word_boundary_nonempty_string' => ['alpha beta gamma', ['max_length' => 11, 'suffix' => '...', 'word_boundary' => 'false'], 'alpha...', 11],
    'minimum_integer_limit' => ['abcdef', ['max_length' => PHP_INT_MIN, 'suffix' => '...'], '', 0],
    'missing_default_limit' => ['abcdef', [], 'abcdef', 255],
]);

it('preserves non-string values without interpreting truncation options', function (mixed $value): void {
    $result = Maho_FeedManager_Model_Transformer::pipeline($value, [
        ['transformer' => 'truncate', 'options' => ['max_length' => -1, 'suffix' => new stdClass()]],
    ]);

    expect($result)->toBe($value);
})->with([
    'null' => [null],
    'integer' => [42],
    'boolean' => [false],
    'array' => [['value']],
    'object' => [new stdClass()],
]);

it('applies the same bound to a parsed transformer chain', function (): void {
    $chain = Maho_FeedManager_Model_Transformer::parseChainString('uppercase|truncate:max_length=2,suffix=...');

    expect(Maho_FeedManager_Model_Transformer::pipeline('abcdef', $chain))->toBe('AB');
});
