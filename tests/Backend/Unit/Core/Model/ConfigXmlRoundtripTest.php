<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final class ConfigXmlRoundtripProbe extends Mage_Core_Model_Config
{
    /** @var array<string, string> */
    public array $entries = [];

    public function saveSection(Mage_Core_Model_Config_Element $source): void
    {
        $this->_saveSectionCache($this->getCacheId(), 'adminhtml', $source);
        $this->entries = $this->_cachePartsForSave;
    }

    public function loadSection(): false|SimpleXMLElement
    {
        return $this->_loadSectionCache('adminhtml');
    }

    #[\Override]
    protected function _loadCache($id)
    {
        return $this->entries[$id] ?? false;
    }
}

it('preserves decoded configuration attributes before and after section cache serialization', function (string $copy): void {
    $source = new Mage_Core_Model_Config_Element(
        '<config><adminhtml label="Tea &amp; &quot;Café&quot; &amp;amp; 東京"><item label="Tea &amp; &quot;Café&quot; &amp;amp; 東京"/></adminhtml></config>',
    );
    $merged = new Mage_Core_Model_Config_Element('<config/>');
    if ($copy === 'appendChild') {
        $merged->appendChild($source->adminhtml);
    } elseif ($copy === 'extend') {
        $merged->extend($source);
    } else {
        $merged = $source;
    }
    $expected = (string) $source->adminhtml->item['label'];
    expect((string) $merged->adminhtml['label'])->toBe($expected)
        ->and((string) $merged->adminhtml->item['label'])->toBe($expected);

    $cache = new ConfigXmlRoundtripProbe();
    $cache->saveSection($merged);
    $section = $cache->loadSection();

    expect($section)->toBeInstanceOf(Mage_Core_Model_Config_Element::class)
        ->and((string) $section['label'])->toBe($expected)
        ->and((string) $section->item['label'])->toBe($expected);
})->with(['direct', 'appendChild', 'extend']);
