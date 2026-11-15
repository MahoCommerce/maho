<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Transformer Factory', function () {
    test('returns all available transformers', function () {
        $available = Maho_FeedManager_Model_Transformer::getAvailableTransformers();
        expect($available)->toBeArray()
            ->and($available)->toContain('strip_tags')
            ->and($available)->toContain('truncate')
            ->and($available)->toContain('default_value')
            ->and($available)->toContain('prepend_append')
            ->and($available)->toContain('replace')
            ->and($available)->toContain('map_values')
            ->and($available)->toContain('format_price')
            ->and($available)->toContain('format_date')
            ->and($available)->toContain('url_encode')
            ->and($available)->toContain('combine_fields')
            ->and($available)->toContain('conditional')
            ->and($available)->toContain('round')
            ->and($available)->toContain('uppercase')
            ->and($available)->toContain('lowercase')
            ->and($available)->toContain('capitalise')
            ->and($available)->toContain('relative_date_range')
            ->and($available)->toContain('currency_convert')
            ->and($available)->toHaveCount(18);
    });

    test('getTransformer returns null for nonexistent transformer', function () {
        $transformer = Maho_FeedManager_Model_Transformer::getTransformer('nonexistent');
        expect($transformer)->toBeNull();
    });

    test('hasTransformer returns true for registered transformers', function () {
        expect(Maho_FeedManager_Model_Transformer::hasTransformer('strip_tags'))->toBeTrue();
        expect(Maho_FeedManager_Model_Transformer::hasTransformer('uppercase'))->toBeTrue();
    });

    test('hasTransformer returns false for unregistered transformers', function () {
        expect(Maho_FeedManager_Model_Transformer::hasTransformer('nonexistent'))->toBeFalse();
    });

    test('getTransformerOptions excludes internal-only transformers', function () {
        $options = Maho_FeedManager_Model_Transformer::getTransformerOptions();
        expect($options)->toBeArray()
            ->and($options)->not->toHaveKey('combine_fields')
            ->and($options)->toHaveKey('strip_tags')
            ->and($options)->toHaveKey('uppercase');
    });

    test('getTransformersByCategory returns all categories', function () {
        $categories = Maho_FeedManager_Model_Transformer::getTransformersByCategory();
        expect($categories)->toBeArray()
            ->and($categories)->toHaveKey('Text Formatting')
            ->and($categories)->toHaveKey('Values & Defaults')
            ->and($categories)->toHaveKey('Numbers & Prices')
            ->and($categories['Numbers & Prices'])->toHaveKey('current_price')
            ->and($categories)->toHaveKey('Dates & URLs')
            ->and($categories)->toHaveKey('Advanced');
    });

    test('apply returns original value for nonexistent transformer', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello', 'nonexistent');
        expect($result)->toBe('hello');
    });

    test('pipeline chains multiple transformations', function () {
        $result = Maho_FeedManager_Model_Transformer::pipeline('hello world', [
            ['transformer' => 'uppercase'],
            ['transformer' => 'truncate', 'options' => ['max_length' => '5']],
        ]);
        expect($result)->toBe('HELLO');
    });

    test('parseChainString parses transformer chain', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('uppercase|truncate:max_length=10');
        expect($chain)->toHaveCount(2)
            ->and($chain[0]['transformer'])->toBe('uppercase')
            ->and($chain[0]['options'])->toBe([])
            ->and($chain[1]['transformer'])->toBe('truncate')
            ->and($chain[1]['options'])->toBe(['max_length' => '10']);
    });

    test('parseChainString returns empty array for empty string', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('');
        expect($chain)->toBe([]);
    });

    test('parseChainString skips nonexistent transformers', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('uppercase|nonexistent|lowercase');
        expect($chain)->toHaveCount(2)
            ->and($chain[0]['transformer'])->toBe('uppercase')
            ->and($chain[1]['transformer'])->toBe('lowercase');
    });

    test('buildChainString round-trips with parseChainString', function () {
        $original = 'uppercase|truncate:max_length=10,suffix=...';
        $chain = Maho_FeedManager_Model_Transformer::parseChainString($original);
        $rebuilt = Maho_FeedManager_Model_Transformer::buildChainString($chain);
        expect($rebuilt)->toBe($original);
    });
});

describe('TransformerInterface compliance', function () {
    $transformerCodes = [
        'strip_tags', 'truncate', 'default_value', 'prepend_append', 'replace',
        'map_values', 'format_price', 'format_date', 'url_encode', 'combine_fields',
        'conditional', 'round', 'uppercase', 'lowercase', 'capitalise', 'relative_date_range',
    ];

    foreach ($transformerCodes as $code) {
        test("{$code} implements getCode correctly", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            expect($transformer->getCode())->toBe($code);
        });

        test("{$code} has a non-empty name", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            expect($transformer->getName())->toBeString()->not->toBeEmpty();
        });

        test("{$code} has a non-empty description", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            expect($transformer->getDescription())->toBeString()->not->toBeEmpty();
        });

        test("{$code} validateOptions names each missing required option", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            $required = array_filter($transformer->getOptionDefinitions(), fn(array $definition): bool => !empty($definition['required']));
            $missing = array_map(fn(array $definition): string => "Missing required option: {$definition['label']}", array_values($required));
            expect(array_slice($transformer->validateOptions([]), 0, count($missing)))->toBe($missing);
        });
    }
});

describe('StripTags Transformer', function () {
    test('strips all HTML tags', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('<p>Hello <b>world</b></p>', 'strip_tags');
        expect($result)->toBe('Hello world');
    });

    test('preserves allowed tags', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            '<p>Hello <b>world</b></p>',
            'strip_tags',
            ['allowed_tags' => '<b>'],
        );
        expect($result)->toBe('Hello <b>world</b>');
    });

    test('decodes HTML entities by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello &amp; world', 'strip_tags');
        expect($result)->toBe('Hello & world');
    });

    test('preserves HTML entities when decode_entities is false', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            'Hello &amp; world',
            'strip_tags',
            ['decode_entities' => '0'],
        );
        expect($result)->toBe('Hello &amp; world');
    });

    test('normalizes whitespace', function () {
        $result = Maho_FeedManager_Model_Transformer::apply("<p>Hello\n\n  world</p>", 'strip_tags');
        expect($result)->toBe('Hello world');
    });
});

describe('Truncate Transformer', function () {
    test('truncates text exceeding max length', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            'Hello World',
            'truncate',
            ['max_length' => '5'],
        );
        expect($result)->toBe('Hello');
    });

    test('does not truncate text within max length', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            'Hello',
            'truncate',
            ['max_length' => '10'],
        );
        expect($result)->toBe('Hello');
    });

    test('appends suffix when truncating', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            'Hello World',
            'truncate',
            ['max_length' => '8', 'suffix' => '...'],
        );
        expect($result)->toBe('Hello...');
    });

    test('respects word boundary', function () {
        // 'Hello beautiful world' with max_length=18, suffix='...'
        // effectiveLength = 18 - 3 = 15
        // substr(0,15) = 'Hello beautiful'
        // lastSpace = 5, but 5 > 15*0.5=7.5 is false, so no word break
        // Result: 'Hello beautiful...'
        $result = Maho_FeedManager_Model_Transformer::apply(
            'Hello beautiful world is great',
            'truncate',
            ['max_length' => '20', 'suffix' => '...', 'word_boundary' => '1'],
        );
        // effectiveLength = 20 - 3 = 17
        // substr(0,17) = 'Hello beautiful w'
        // lastSpace = 15 (after 'beautiful'), 15 > 8.5 = true
        // truncated to 'Hello beautiful'
        expect($result)->toBe('Hello beautiful...');
    });

    test('handles empty string', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            '',
            'truncate',
            ['max_length' => '10'],
        );
        expect($result)->toBe('');
    });
});

describe('DefaultValue Transformer', function () {
    test('returns default when value is null', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'default_value', ['default' => 'N/A']);
        expect($result)->toBe('N/A');
    });

    test('returns default when value is empty string', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'default_value', ['default' => 'N/A']);
        expect($result)->toBe('N/A');
    });

    test('returns default when value is empty array', function () {
        $result = Maho_FeedManager_Model_Transformer::apply([], 'default_value', ['default' => 'N/A']);
        expect($result)->toBe('N/A');
    });

    test('returns original value when not empty', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello', 'default_value', ['default' => 'N/A']);
        expect($result)->toBe('Hello');
    });

    test('does not treat zero as empty by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(0, 'default_value', ['default' => 'N/A']);
        expect($result)->toBe(0);
    });

    test('treats zero as empty when empty_includes_zero is enabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(0, 'default_value', [
            'default' => 'N/A',
            'empty_includes_zero' => '1',
        ]);
        expect($result)->toBe('N/A');
    });

    test('treats string zero as empty when empty_includes_zero is enabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('0', 'default_value', [
            'default' => 'N/A',
            'empty_includes_zero' => '1',
        ]);
        expect($result)->toBe('N/A');
    });
});

describe('PrependAppend Transformer', function () {
    test('prepends text', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('World', 'prepend_append', ['prepend' => 'Hello ']);
        expect($result)->toBe('Hello World');
    });

    test('appends text', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello', 'prepend_append', ['append' => ' World']);
        expect($result)->toBe('Hello World');
    });

    test('prepends and appends together', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('World', 'prepend_append', [
            'prepend' => 'Hello ',
            'append' => '!',
        ]);
        expect($result)->toBe('Hello World!');
    });

    test('skips empty value by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'prepend_append', [
            'prepend' => 'Hello ',
            'append' => '!',
        ]);
        expect($result)->toBe('');
    });

    test('applies to empty value when skip_if_empty is disabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'prepend_append', [
            'prepend' => 'Hello ',
            'append' => '!',
            'skip_if_empty' => '0',
        ]);
        expect($result)->toBe('Hello !');
    });
});

describe('Replace Transformer', function () {
    test('performs simple string replacement', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello World', 'replace', [
            'search' => 'World',
            'replace' => 'PHP',
        ]);
        expect($result)->toBe('Hello PHP');
    });

    test('removes text when replace is empty', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello World', 'replace', [
            'search' => ' World',
        ]);
        expect($result)->toBe('Hello');
    });

    test('performs case-insensitive replacement', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello WORLD', 'replace', [
            'search' => 'world',
            'replace' => 'PHP',
            'case_sensitive' => '0',
        ]);
        expect($result)->toBe('Hello PHP');
    });

    test('performs regex replacement', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Price: $19.99', 'replace', [
            'search' => '/\$[\d.]+/',
            'replace' => 'PRICE',
            'is_regex' => '1',
        ]);
        expect($result)->toBe('Price: PRICE');
    });

    test('returns value unchanged when search is empty', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello', 'replace', ['search' => '']);
        expect($result)->toBe('Hello');
    });

    test('validates invalid regex pattern', function () {
        $transformer = Maho_FeedManager_Model_Transformer::getTransformer('replace');
        $errors = $transformer->validateOptions(['search' => '/[invalid/', 'is_regex' => '1']);
        expect($errors)->not->toBeEmpty();
    });
});

describe('MapValues Transformer', function () {
    test('maps value according to mapping', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('1', 'map_values', [
            'mapping' => "1=in_stock\n2=out_of_stock",
        ]);
        expect($result)->toBe('in_stock');
    });

    test('returns original value when no mapping found and no default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3', 'map_values', [
            'mapping' => "1=in_stock\n2=out_of_stock",
        ]);
        expect($result)->toBe('3');
    });

    test('returns default when no mapping found', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3', 'map_values', [
            'mapping' => "1=in_stock\n2=out_of_stock",
            'default' => 'unknown',
        ]);
        expect($result)->toBe('unknown');
    });

    test('performs case-insensitive matching by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('YES', 'map_values', [
            'mapping' => "yes=true\nno=false",
        ]);
        expect($result)->toBe('true');
    });

    test('performs case-sensitive matching when enabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('YES', 'map_values', [
            'mapping' => "yes=true\nno=false",
            'case_sensitive' => '1',
        ]);
        expect($result)->toBe('YES');
    });
});

describe('FormatPrice Transformer', function () {
    test('formats price with currency', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('19.99', 'format_price', ['currency' => 'EUR']);
        expect($result)->toBe('19.99 EUR');
    });

    test('formats price with custom decimals', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('19.999', 'format_price', [
            'decimals' => '2',
            'currency' => 'USD',
        ]);
        expect($result)->toBe('20.00 USD');
    });

    test('formats price with custom separators', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('1234.56', 'format_price', [
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'currency' => 'EUR',
        ]);
        expect($result)->toBe('1.234,56 EUR');
    });

    test('returns empty string when skip_if_empty is enabled and value is null', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'format_price', ['skip_if_empty' => '1']);
        expect($result)->toBe('');
    });

    test('returns empty string when skip_if_empty is enabled and value is empty', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'format_price', ['skip_if_empty' => '1']);
        expect($result)->toBe('');
    });

    test('returns empty string when skip_if_empty is enabled and value is zero', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('0', 'format_price', ['skip_if_empty' => '1']);
        expect($result)->toBe('');
    });

    test('returns non-numeric values unchanged when skip_if_empty is disabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('not-a-number', 'format_price');
        expect($result)->toBe('not-a-number');
    });

    test('returns empty for non-numeric when skip_if_empty is enabled', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('not-a-number', 'format_price', ['skip_if_empty' => '1']);
        expect($result)->toBe('');
    });

    test('formats without currency when not specified', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('19.99', 'format_price');
        expect($result)->toBe('19.99');
    });
});

describe('FormatDate Transformer', function () {
    test('formats date with output format', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('2025-06-15 14:30:00', 'format_date', [
            'output_format' => 'Y-m-d',
        ]);
        expect($result)->toBe('2025-06-15');
    });

    test('formats date with specific input format', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('15/06/2025', 'format_date', [
            'input_format' => 'd/m/Y',
            'output_format' => 'Y-m-d',
        ]);
        expect($result)->toBe('2025-06-15');
    });

    test('returns empty value unchanged', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'format_date', ['output_format' => 'Y-m-d']);
        expect($result)->toBe('');
    });

    test('returns null unchanged', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'format_date', ['output_format' => 'Y-m-d']);
        expect($result)->toBeNull();
    });

    test('returns original value when input format does not match', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('not-a-date', 'format_date', [
            'input_format' => 'd/m/Y',
            'output_format' => 'Y-m-d',
        ]);
        expect($result)->toBe('not-a-date');
    });

    test('converts timezone', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('2025-06-15 00:00:00', 'format_date', [
            'output_format' => 'Y-m-d H:i:s',
            'timezone' => 'Australia/Sydney',
        ]);
        expect($result)->toBe('2025-06-15 10:00:00');
    });
});

describe('UrlEncode Transformer', function () {
    test('encodes with rawurlencode by default (path mode)', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'url_encode');
        expect($result)->toBe('hello%20world');
    });

    test('encodes with urlencode in query mode', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'url_encode', ['encode_type' => 'query']);
        expect($result)->toBe('hello+world');
    });

    test('encodes full URL preserving structure', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(
            'https://example.com/path with spaces/page',
            'url_encode',
            ['encode_type' => 'full'],
        );
        expect($result)->toContain('https://example.com');
        expect($result)->toContain('path%20with%20spaces');
    });
});

describe('CombineFields Transformer', function () {
    test('combines fields using template', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'combine_fields', [
            'template' => '{{brand}} - {{name}}',
        ], [
            'brand' => 'Acme',
            'name' => 'Widget',
        ]);
        expect($result)->toBe('Acme - Widget');
    });

    test('returns empty when skip_if_any_empty and field is missing', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'combine_fields', [
            'template' => '{{brand}} - {{name}}',
            'skip_if_any_empty' => '1',
        ], [
            'brand' => 'Acme',
        ]);
        expect($result)->toBe('');
    });

    test('returns template with empty placeholder when field is missing', function () {
        $result = Maho_FeedManager_Model_Transformer::apply(null, 'combine_fields', [
            'template' => '{{brand}} - {{name}}',
        ], [
            'brand' => 'Acme',
        ]);
        expect($result)->toBe('Acme - ');
    });

    test('returns original value when template is empty', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('original', 'combine_fields', ['template' => '']);
        expect($result)->toBe('original');
    });

    test('returns template when no placeholders', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('original', 'combine_fields', [
            'template' => 'Static text',
        ]);
        expect($result)->toBe('Static text');
    });
});

describe('Conditional Transformer', function () {
    test('applies the operator', function (mixed $value, array $options, string $expected) {
        expect(Maho_FeedManager_Model_Transformer::apply($value, 'conditional', $options))->toBe($expected);
    })->with([
        'eq matches' => ['1', ['operator' => 'eq', 'compare_value' => '1', 'true_value' => 'in stock', 'false_value' => 'out of stock'], 'in stock'],
        'eq does not match' => ['0', ['operator' => 'eq', 'compare_value' => '1', 'true_value' => 'in stock', 'false_value' => 'out of stock'], 'out of stock'],
        'neq' => ['active', ['operator' => 'neq', 'compare_value' => 'disabled', 'true_value' => 'yes', 'false_value' => 'no'], 'yes'],
        'gt' => ['10', ['operator' => 'gt', 'compare_value' => '5', 'true_value' => 'high', 'false_value' => 'low'], 'high'],
        'gte' => ['5', ['operator' => 'gte', 'compare_value' => '5', 'true_value' => 'yes', 'false_value' => 'no'], 'yes'],
        'lt' => ['3', ['operator' => 'lt', 'compare_value' => '5', 'true_value' => 'low', 'false_value' => 'high'], 'low'],
        'lte' => ['5', ['operator' => 'lte', 'compare_value' => '5', 'true_value' => 'yes', 'false_value' => 'no'], 'yes'],
        'empty' => ['', ['operator' => 'empty', 'true_value' => 'missing', 'false_value' => '{{value}}'], 'missing'],
        'not_empty' => ['hello', ['operator' => 'not_empty', 'true_value' => '{{value}}', 'false_value' => 'missing'], 'hello'],
        'contains' => ['Hello World', ['operator' => 'contains', 'compare_value' => 'World', 'true_value' => 'found', 'false_value' => 'not found'], 'found'],
        'not_contains' => ['Hello World', ['operator' => 'not_contains', 'compare_value' => 'Foo', 'true_value' => 'absent', 'false_value' => 'present'], 'absent'],
        'in' => ['red', ['operator' => 'in', 'compare_value' => 'red, green, blue', 'true_value' => 'primary', 'false_value' => 'other'], 'primary'],
        'not_in' => ['yellow', ['operator' => 'not_in', 'compare_value' => 'red, green, blue', 'true_value' => 'other', 'false_value' => 'primary'], 'other'],
    ]);

    test('replaces {{value}} placeholder in output', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello', 'conditional', [
            'operator' => 'not_empty',
            'true_value' => 'Value: {{value}}',
            'false_value' => 'empty',
        ]);
        expect($result)->toBe('Value: Hello');
    });

    test('checks condition_field from productData', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('current_value', 'conditional', [
            'condition_field' => 'status',
            'operator' => 'eq',
            'compare_value' => '1',
            'true_value' => 'enabled',
            'false_value' => 'disabled',
        ], [
            'status' => '1',
        ]);
        expect($result)->toBe('enabled');
    });
});

describe('RelativeDateRange Transformer', function () {
    test('emits a start/end range spanning the configured offset', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'relative_date_range', [
            'output_format' => 'Y-m-d',
            'start_amount' => '0',
            'end_amount' => '90',
            'timezone' => 'UTC',
        ]);
        expect($result)->toContain('/');
        [$start, $end] = explode('/', $result);
        $days = new DateTime($start)->diff(new DateTime($end))->days;
        expect($days)->toBe(90);
    });

    test('ignores the incoming value', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('ignored', 'relative_date_range', [
            'output_format' => 'Y-m-d',
            'timezone' => 'UTC',
        ]);
        expect($result)->not->toContain('ignored');
        expect($result)->toContain('/');
    });

    test('supports a backward start direction', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'relative_date_range', [
            'output_format' => 'Y-m-d',
            'start_direction' => '-',
            'start_amount' => '7',
            'start_unit' => 'days',
            'end_amount' => '0',
            'timezone' => 'UTC',
        ]);
        [$start, $end] = explode('/', $result);
        expect((new DateTime($start)) < (new DateTime($end)))->toBeTrue();
        $days = new DateTime($start)->diff(new DateTime($end))->days;
        expect($days)->toBe(7);
    });

    test('returns the original value when the timezone is invalid', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('original', 'relative_date_range', [
            'timezone' => 'Not/ARealZone',
        ]);
        expect($result)->toBe('original');
    });
});

describe('Direction-aware option trimming', function () {
    test('preserves the inside-edge space on prepend_append append', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('prepend_append:append= kg');
        expect($chain[0]['options']['append'])->toBe(' kg');
    });

    test('preserves the inside-edge space on prepend_append prepend', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('prepend_append:prepend=Approx ');
        expect($chain[0]['options']['prepend'])->toBe('Approx ');
    });

    test('strips the outer edge on prepend_append affixes', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('prepend_append:append=kg ');
        expect($chain[0]['options']['append'])->toBe('kg');
    });

    test('fully trims option values for other transformers', function () {
        $chain = Maho_FeedManager_Model_Transformer::parseChainString('conditional:compare_value= 1 ');
        expect($chain[0]['options']['compare_value'])->toBe('1');
    });
});

describe('Round Transformer', function () {
    test('rounds to specified precision', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3.456', 'round', ['precision' => '2']);
        expect($result)->toBe(3.46);
    });

    test('rounds to zero decimal places by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3.6', 'round');
        expect($result)->toBe(4.0);
    });

    test('supports ceil mode', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3.21', 'round', [
            'precision' => '1',
            'mode' => 'ceil',
        ]);
        expect($result)->toBe(3.3);
    });

    test('supports floor mode', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3.29', 'round', [
            'precision' => '1',
            'mode' => 'floor',
        ]);
        expect($result)->toBe(3.2);
    });

    test('returns non-numeric values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply('abc', 'round'))->toBe('abc');
    });
});

describe('Uppercase Transformer', function () {
    test('converts text to uppercase', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'uppercase');
        expect($result)->toBe('HELLO WORLD');
    });

    test('handles already uppercase text', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('HELLO', 'uppercase');
        expect($result)->toBe('HELLO');
    });

    test('handles multibyte characters', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('café', 'uppercase');
        expect($result)->toBe('CAFÉ');
    });
});

describe('Lowercase Transformer', function () {
    test('converts text to lowercase', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('HELLO WORLD', 'lowercase');
        expect($result)->toBe('hello world');
    });

    test('handles already lowercase text', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello', 'lowercase');
        expect($result)->toBe('hello');
    });

    test('handles multibyte characters', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('CAFÉ', 'lowercase');
        expect($result)->toBe('café');
    });
});

describe('Capitalise Transformer', function () {
    test('converts to title case by default', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'capitalise');
        expect($result)->toBe('Hello World');
    });

    test('capitalises first letter only', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'capitalise', ['mode' => 'first']);
        expect($result)->toBe('Hello world');
    });

    test('capitalises sentences', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world. goodbye world', 'capitalise', ['mode' => 'sentence']);
        expect($result)->toBe('Hello world. Goodbye world');
    });

    test('handles empty string in first mode', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'capitalise', ['mode' => 'first']);
        expect($result)->toBe('');
    });
});

describe('Text transformers', function () {
    test('return a non-string value unchanged', function (string $transformer, array $options, mixed $value) {
        expect(Maho_FeedManager_Model_Transformer::apply($value, $transformer, $options))->toBe($value);
    })->with([
        'strip_tags integer' => ['strip_tags', [], 42],
        'strip_tags null' => ['strip_tags', [], null],
        'truncate' => ['truncate', ['max_length' => '1'], 42],
        'replace' => ['replace', ['search' => '4'], 42],
        'url_encode' => ['url_encode', [], 42],
        'uppercase' => ['uppercase', [], 42],
        'lowercase' => ['lowercase', [], 42],
        'capitalise' => ['capitalise', [], 42],
    ]);
});
