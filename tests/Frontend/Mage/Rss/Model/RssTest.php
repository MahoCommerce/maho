<?php

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

it('generates valid RSS 2.0 XML', function () {
    $rss = Mage::getModel('rss/rss');

    $rss->_addHeader([
        'title' => 'Test Feed',
        'description' => 'Test Description',
        'link' => 'https://example.com',
        'charset' => 'UTF-8',
        'language' => 'en-US',
    ]);

    $rss->_addEntry([
        'title' => 'Item 1',
        'link' => 'https://example.com/item1',
        'description' => 'Description for item 1',
    ]);

    $rss->_addEntry([
        'title' => 'Item 2',
        'link' => 'https://example.com/item2',
        'description' => 'Description for item 2',
    ]);

    $xml = $rss->createRssXml();

    // Verify it's valid XML
    expect($xml)->toBeString();
    expect($xml)->toContain('<?xml version="1.0"');

    // Parse the XML
    $dom = new DOMDocument();
    expect($dom->loadXML($xml))->toBeTrue();

    // Verify RSS 2.0 structure
    $rssElement = $dom->getElementsByTagName('rss')->item(0);
    expect($rssElement)->not->toBeNull();
    expect($rssElement->getAttribute('version'))->toBe('2.0');

    // Verify channel elements
    $channel = $dom->getElementsByTagName('channel')->item(0);
    expect($channel)->not->toBeNull();

    $title = $channel->getElementsByTagName('title')->item(0);
    expect($title->textContent)->toBe('Test Feed');

    $link = $channel->getElementsByTagName('link')->item(0);
    expect($link->textContent)->toBe('https://example.com');

    $description = $channel->getElementsByTagName('description')->item(0);
    expect($description->textContent)->toBe('Test Description');

    $language = $channel->getElementsByTagName('language')->item(0);
    expect($language->textContent)->toBe('en-US');

    // Verify items
    $items = $channel->getElementsByTagName('item');
    expect($items->length)->toBe(2);

    // Check first item
    $item1 = $items->item(0);
    expect($item1->getElementsByTagName('title')->item(0)->textContent)->toBe('Item 1');
    expect($item1->getElementsByTagName('link')->item(0)->textContent)->toBe('https://example.com/item1');
    expect($item1->getElementsByTagName('description')->item(0)->textContent)->toBe('Description for item 1');

    // Check second item
    $item2 = $items->item(1);
    expect($item2->getElementsByTagName('title')->item(0)->textContent)->toBe('Item 2');
    expect($item2->getElementsByTagName('link')->item(0)->textContent)->toBe('https://example.com/item2');
    expect($item2->getElementsByTagName('description')->item(0)->textContent)->toBe('Description for item 2');
});

it('handles HTML content in descriptions', function () {
    $rss = Mage::getModel('rss/rss');

    $rss->_addHeader([
        'title' => 'Test Feed',
        'description' => 'Test Description',
        'link' => 'https://example.com',
    ]);

    $rss->_addEntry([
        'title' => 'Product Item',
        'link' => 'https://example.com/product',
        'description' => '<table><tr><td><img src="image.jpg" /></td><td>Product description</td></tr></table>',
    ]);

    $xml = $rss->createRssXml();

    // Verify it's valid XML
    $dom = new DOMDocument();
    expect($dom->loadXML($xml))->toBeTrue();

    // Verify HTML is properly encoded in CDATA or escaped
    expect($xml)->toContain('Product description');
});

it('handles empty feed gracefully', function () {
    $rss = Mage::getModel('rss/rss');

    $rss->_addHeader([
        'title' => 'Empty Feed',
        'description' => 'No items',
        'link' => 'https://example.com',
    ]);

    $xml = $rss->createRssXml();

    // Verify it's valid XML even without items
    $dom = new DOMDocument();
    expect($dom->loadXML($xml))->toBeTrue();

    $items = $dom->getElementsByTagName('item');
    expect($items->length)->toBe(0);
});

it('supports optional item fields', function () {
    $rss = Mage::getModel('rss/rss');

    $rss->_addHeader([
        'title' => 'Test Feed',
        'description' => 'Test Description',
        'link' => 'https://example.com',
    ]);

    $rss->_addEntry([
        'title' => 'Item with extras',
        'link' => 'https://example.com/item',
        'description' => 'Description',
        'author' => 'author@example.com',
        'guid' => 'unique-id-123',
        'pubDate' => 'Mon, 01 Jan 2025 00:00:00 GMT',
    ]);

    $xml = $rss->createRssXml();

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $item = $dom->getElementsByTagName('item')->item(0);
    expect($item->getElementsByTagName('author')->item(0)->textContent)->toBe('author@example.com');
    expect($item->getElementsByTagName('guid')->item(0)->textContent)->toBe('unique-id-123');
    expect($item->getElementsByTagName('pubDate')->item(0)->textContent)->toBe('Mon, 01 Jan 2025 00:00:00 GMT');
});
