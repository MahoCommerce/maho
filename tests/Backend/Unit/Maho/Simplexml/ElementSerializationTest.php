<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

it('preserves decoded attributes and leaf text when formatting XML', function (string $value, int|bool $level): void {
    $source = new Maho\Simplexml\Element('<config><item/></config>');
    $source->item->addAttribute('label', $value);
    $source->item = $value;

    $output = $source->asNiceXml('', $level);
    $decoded = simplexml_load_string($output);

    expect($decoded)->toBeInstanceOf(SimpleXMLElement::class)
        ->and((string) $decoded->item['label'])->toBe($value)
        ->and((string) $decoded->item)->toBe($value);
})->with([
    'plain' => 'ordinary label',
    'ampersand' => 'Tea & Coffee',
    'quotes' => '\'single\' and "double"',
    'angles' => '<small> > <large>',
    'literal entities' => '&amp; &quot; &#38;',
    'unicode' => 'Café 東京 🛍',
])->with([0, false]);
