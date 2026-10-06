<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('tells if a node has child elements', function (string $node, bool $expected) {
    $xml = new Maho\Simplexml\Element('<root><attributesOnly code="1"/><parent><child/></parent></root>');

    expect($xml->{$node}->hasChildren())->toBe($expected);
})->with([
    'a node that does not exist' => ['missing', false],
    'a node with only attributes' => ['attributesOnly', false],
    'a node with a child element' => ['parent', true],
]);
