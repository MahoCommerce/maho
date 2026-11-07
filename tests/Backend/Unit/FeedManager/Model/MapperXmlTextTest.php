<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class XmlTextMapper extends Maho_FeedManager_Model_Mapper
{
    public function __construct() {}

    #[\Override]
    public function extractProductData(Mage_Catalog_Model_Product $product): array
    {
        return $product->getData();
    }
}

it('preserves product text in generated visual XML structures', function (string $text, bool $cdata, bool $nested): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-xml-text-');
    $previousErrors = libxml_use_internal_errors(true);
    $mapper = new XmlTextMapper();
    $product = new Mage_Catalog_Model_Product();
    $product->setDescription($text);
    $field = [
        'tag' => 'description',
        'source_type' => 'attribute',
        'source_value' => 'description',
        'cdata' => $cdata,
    ];
    $structure = $nested ? [['tag' => 'details', 'children' => [$field]]] : [$field];

    try {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><feed>'
            . $mapper->mapProductToXmlStructure($product, $structure, 'item', 1)
            . '</feed>';
        file_put_contents($path, $xml);

        $document = new DOMDocument();
        expect($document->load($path, LIBXML_NONET))->toBeTrue();

        $xpath = new DOMXPath($document);
        $valuePath = $nested ? '/feed/item/details/description' : '/feed/item/description';
        expect($xpath->query($valuePath))->toHaveCount(1);
        expect($xpath->evaluate('string(' . $valuePath . ')'))->toBe($text);
        expect(new Maho_FeedManager_Model_Validator()->validate($path, 'xml'))->toBeTrue();
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        unlink($path);
    }
})->with([
    'literal terminator' => ['Text with ]]> inside'],
    'leading terminator' => [']]>start'],
    'trailing terminator' => ['end]]>'],
    'adjacent terminators' => [']]>]]>'],
    'Unicode and markup' => ['Été 🛍 & <b> ]]> "quoted"'],
    'ordinary text' => ['Ordinary & <text> "quoted"'],
    'empty text' => [''],
    'zero text' => ['0'],
])->with([true, false])->with([false, true]);

it('preserves CDATA text from static and array values while retaining optional elements', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'feed-xml-text-');
    $previousErrors = libxml_use_internal_errors(true);
    $product = new Mage_Catalog_Model_Product();
    $product->setData('labels', ['first]]>', null, '', 'été&second]]>']);
    $mapper = new XmlTextMapper();
    $structure = [
        ['tag' => 'note', 'source_type' => 'static', 'source_value' => 'Literal ]]> note', 'cdata' => true],
        ['tag' => 'labels', 'source_type' => 'attribute', 'source_value' => 'labels', 'cdata' => true],
        ['tag' => 'required', 'source_type' => 'static', 'source_value' => '', 'cdata' => true],
        ['tag' => 'optional', 'source_type' => 'static', 'source_value' => '', 'cdata' => true, 'optional' => true],
    ];

    try {
        file_put_contents($path, '<feed>' . $mapper->mapProductToXmlStructure($product, $structure) . '</feed>');
        $document = new DOMDocument();
        expect($document->load($path, LIBXML_NONET))->toBeTrue();

        $xpath = new DOMXPath($document);
        expect($xpath->evaluate('string(/feed/item/note)'))->toBe('Literal ]]> note');
        expect($xpath->evaluate('string(/feed/item/labels)'))->toBe('first]]>,été&second]]>');
        expect($xpath->query('/feed/item/required'))->toHaveCount(1);
        expect($xpath->query('/feed/item/optional'))->toHaveCount(0);
        expect(new Maho_FeedManager_Model_Validator()->validate($path, 'xml'))->toBeTrue();
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        unlink($path);
    }
});
