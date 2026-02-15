<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
            ->and($available)->toHaveCount(15);
    });

    test('getTransformer returns correct instance', function () {
        $transformer = Maho_FeedManager_Model_Transformer::getTransformer('strip_tags');
        expect($transformer)->toBeInstanceOf(Maho_FeedManager_Model_Transformer_StripTags::class);
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
            ->and($categories)->toHaveKey('Dates & URLs')
            ->and($categories)->toHaveKey('Advanced');
    });

    test('apply transforms a value correctly', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello world', 'uppercase');
        expect($result)->toBe('HELLO WORLD');
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
        'conditional', 'round', 'uppercase', 'lowercase', 'capitalise',
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

        test("{$code} returns array from getOptionDefinitions", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            expect($transformer->getOptionDefinitions())->toBeArray();
        });

        test("{$code} returns array from validateOptions", function () use ($code) {
            $transformer = Maho_FeedManager_Model_Transformer::getTransformer($code);
            expect($transformer->validateOptions([]))->toBeArray();
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'strip_tags'))->toBe(42);
        expect(Maho_FeedManager_Model_Transformer::apply(null, 'strip_tags'))->toBeNull();
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'truncate', ['max_length' => '1']))->toBe(42);
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'replace', ['search' => '4']))->toBe(42);
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
        expect($result)->not->toBe('2025-06-15 00:00:00');
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'url_encode'))->toBe(42);
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
    test('returns true_value when condition matches (eq)', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('1', 'conditional', [
            'operator' => 'eq',
            'compare_value' => '1',
            'true_value' => 'in stock',
            'false_value' => 'out of stock',
        ]);
        expect($result)->toBe('in stock');
    });

    test('returns false_value when condition does not match (eq)', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('0', 'conditional', [
            'operator' => 'eq',
            'compare_value' => '1',
            'true_value' => 'in stock',
            'false_value' => 'out of stock',
        ]);
        expect($result)->toBe('out of stock');
    });

    test('supports neq operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('active', 'conditional', [
            'operator' => 'neq',
            'compare_value' => 'disabled',
            'true_value' => 'yes',
            'false_value' => 'no',
        ]);
        expect($result)->toBe('yes');
    });

    test('supports gt operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('10', 'conditional', [
            'operator' => 'gt',
            'compare_value' => '5',
            'true_value' => 'high',
            'false_value' => 'low',
        ]);
        expect($result)->toBe('high');
    });

    test('supports gte operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('5', 'conditional', [
            'operator' => 'gte',
            'compare_value' => '5',
            'true_value' => 'yes',
            'false_value' => 'no',
        ]);
        expect($result)->toBe('yes');
    });

    test('supports lt operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('3', 'conditional', [
            'operator' => 'lt',
            'compare_value' => '5',
            'true_value' => 'low',
            'false_value' => 'high',
        ]);
        expect($result)->toBe('low');
    });

    test('supports lte operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('5', 'conditional', [
            'operator' => 'lte',
            'compare_value' => '5',
            'true_value' => 'yes',
            'false_value' => 'no',
        ]);
        expect($result)->toBe('yes');
    });

    test('supports empty operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('', 'conditional', [
            'operator' => 'empty',
            'true_value' => 'missing',
            'false_value' => '{{value}}',
        ]);
        expect($result)->toBe('missing');
    });

    test('supports not_empty operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('hello', 'conditional', [
            'operator' => 'not_empty',
            'true_value' => '{{value}}',
            'false_value' => 'missing',
        ]);
        expect($result)->toBe('hello');
    });

    test('supports contains operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello World', 'conditional', [
            'operator' => 'contains',
            'compare_value' => 'World',
            'true_value' => 'found',
            'false_value' => 'not found',
        ]);
        expect($result)->toBe('found');
    });

    test('supports not_contains operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('Hello World', 'conditional', [
            'operator' => 'not_contains',
            'compare_value' => 'Foo',
            'true_value' => 'absent',
            'false_value' => 'present',
        ]);
        expect($result)->toBe('absent');
    });

    test('supports in operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('red', 'conditional', [
            'operator' => 'in',
            'compare_value' => 'red, green, blue',
            'true_value' => 'primary',
            'false_value' => 'other',
        ]);
        expect($result)->toBe('primary');
    });

    test('supports not_in operator', function () {
        $result = Maho_FeedManager_Model_Transformer::apply('yellow', 'conditional', [
            'operator' => 'not_in',
            'compare_value' => 'red, green, blue',
            'true_value' => 'other',
            'false_value' => 'primary',
        ]);
        expect($result)->toBe('other');
    });

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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'uppercase'))->toBe(42);
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'lowercase'))->toBe(42);
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

    test('returns non-string values unchanged', function () {
        expect(Maho_FeedManager_Model_Transformer::apply(42, 'capitalise'))->toBe(42);
    });
});
