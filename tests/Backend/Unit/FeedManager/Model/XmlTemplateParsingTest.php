<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

class XmlTemplateParsingMapper extends Maho_FeedManager_Model_Mapper
{
    public array $resolvedConfigs = [];

    public function __construct() {}

    #[\Override]
    public function extractProductData(Mage_Catalog_Model_Product $product): array
    {
        return $product->getData();
    }

    #[\Override]
    public function resolveFieldValue(array $config, array $rawData, Mage_Catalog_Model_Product $product): mixed
    {
        $this->resolvedConfigs[] = $config;

        return parent::resolveFieldValue($config, $rawData, $product);
    }
}

class XmlTemplateParsingGenerator extends Maho_FeedManager_Model_Generator
{
    public XmlTemplateParsingMapper $templateMapper;

    public function __construct()
    {
        $this->_mapper = $this->templateMapper = new XmlTemplateParsingMapper();
    }

    public function render(string $template, array $data, array $feedData = []): string
    {
        $product = new ReflectionClass(Mage_Catalog_Model_Product::class)->newInstanceWithoutConstructor();
        $product->setData($data);
        $feed = new ReflectionClass(Maho_FeedManager_Model_Feed::class)->newInstanceWithoutConstructor();
        $feed->setData($feedData);

        return $this->_renderItemTemplate($template, $product, $feed);
    }
}

it('resolves each product again when the item template repeats', function () {
    $generator = new XmlTemplateParsingGenerator();
    $template = '<item><sku>{type="attribute" value="sku"}</sku><name>{type="attribute" value="name" format="uppercase" length="5"}</name></item>';

    expect($generator->render($template, ['sku' => 'one', 'name' => 'first name']))
        ->toBe('<item><sku>one</sku><name>FIRST</name></item>');
    expect($generator->render($template, ['sku' => 'two', 'name' => 'second name']))
        ->toBe('<item><sku>two</sku><name>SECON</name></item>');
});

it('reads changed templates on the same generator', function () {
    $generator = new XmlTemplateParsingGenerator();
    $data = ['sku' => 'one', 'name' => 'First'];

    expect($generator->render('<sku>{type="attribute" value="sku"}</sku>', $data))->toBe('<sku>one</sku>');
    expect($generator->render('<name>{type="attribute" value="name"}</name>', $data))->toBe('<name>First</name>');
    expect($generator->render('<sku>{type="attribute" value="sku"}</sku>', $data))->toBe('<sku>one</sku>');
    expect($generator->render('<empty/>', $data))->toBe('<empty/>');
});

it('keeps the enclosing tag for each occurrence of the same field', function () {
    $generator = new XmlTemplateParsingGenerator();
    $field = '{type="attribute" value="sku"}';
    $template = '<sku>' . $field . '</sku><g:id>' . $field . '</g:id><mixed>prefix ' . $field . '</mixed>';

    $generator->render($template, ['sku' => 'one']);
    $generator->render($template, ['sku' => 'two']);
    $generator->render('<new>' . $field . '</new>', ['sku' => 'three']);

    expect(array_map(static fn(array $config): string => $config['tag'] ?? '', $generator->templateMapper->resolvedConfigs))
        ->toBe(['sku', 'g:id', '', 'sku', 'g:id', '', 'new']);
});

it('keeps the second placeholder pass dependent on the resolved product value', function () {
    $generator = new XmlTemplateParsingGenerator();
    $template = '<name>{type="attribute" value="name"}</name><sku>{{sku}}</sku>';

    expect($generator->render($template, ['sku' => 'one', 'name' => '{{sku}}']))
        ->toBe('<name>one</name><sku>one</sku>');
    expect($generator->render($template, ['sku' => 'two', 'name' => 'Second']))
        ->toBe('<name>Second</name><sku>two</sku>');
});

it('applies changed feed formatting to a repeated template', function () {
    $generator = new XmlTemplateParsingGenerator();
    $template = '<amount>{type="attribute" value="amount" format="price"}</amount>';
    $data = ['amount' => '1234.5'];

    expect($generator->render($template, $data, ['price_currency' => 'USD', 'price_decimals' => 2]))
        ->toBe('<amount>1234.50 USD</amount>');
    expect($generator->render($template, $data, ['price_currency' => 'EUR', 'price_decimals' => 1, 'price_decimal_point' => ',']))
        ->toBe('<amount>1234,5 EUR</amount>');
});

it('runs transformer chains for each product without changing the cached parameters', function () {
    $generator = new XmlTemplateParsingGenerator();
    $template = '<name>{type="attribute" value="name" transformers="uppercase" length="1"}</name>';

    expect($generator->render($template, ['name' => 'first']))->toBe('<name>FIRST</name>');
    expect($generator->render($template, ['name' => 'second']))->toBe('<name>SECOND</name>');
});

it('uses the parent fallback from the current product row', function () {
    $generator = new XmlTemplateParsingGenerator();
    $template = '<sku>{type="attribute" value="sku" parent="yes"}</sku>';

    expect($generator->render($template, ['sku' => '', 'parent_sku' => 'parent-one']))->toBe('<sku>parent-one</sku>');
    expect($generator->render($template, ['sku' => 'child-two', 'parent_sku' => 'parent-two']))->toBe('<sku>child-two</sku>');
});
