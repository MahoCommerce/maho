<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

declare(strict_types=1);

final class Api2XmlRoundtripResource extends Mage_Api2_Model_Resource
{
    public function renderResponse(array $data): void
    {
        $this->_render($data);
    }
}

it('preserves decoded XML response values, nested records and sanitized field names', function (string $value): void {
    $configProperty = new ReflectionProperty(Mage::class, '_config');
    $previous = $configProperty->getValue();
    $config = new Mage_Core_Model_Config();
    $config->loadString('<config><global><models><api2><class>Mage_Api2_Model</class></api2></models></global></config>');
    $configProperty->setValue(null, $config);

    try {
        $resource = new Api2XmlRoundtripResource();
        $response = new Mage_Api2_Model_Response();
        $resource->setResponse($response);
        $resource->setRenderer(new Mage_Api2_Model_Renderer_Xml());
        $resource->renderResponse([
            'product name' => $value,
            '9:code!' => 'Café',
            'details' => ['name' => $value],
        ]);
        $decoded = simplexml_load_string($response->getBody());

        expect($decoded)->toBeInstanceOf(SimpleXMLElement::class)
            ->and($decoded->getName())->toBe('magento_api')
            ->and((string) $decoded->product_name)->toBe($value)
            ->and((string) $decoded->data_item_9_code)->toBe('Café')
            ->and((string) $decoded->details->name)->toBe($value);
    } finally {
        $configProperty->setValue(null, $previous);
    }
})->with([
    'plain' => 'ordinary label',
    'ampersand' => 'Tea & Coffee',
    'quotes' => '\'single\' and "double"',
    'angles' => '<small> > <large>',
    'literal entities' => '&amp; &quot; &#38;',
    'unicode' => 'Café 東京 🛍',
    'empty' => '',
]);
