<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A cache section that the merged config does not contain, "crontab" above all, used to write no
 * cache entry at all. The next getNode() for that section then looked like a broken cache: the
 * config disabled its own cache and re-parsed every module XML file. An absent section now stores
 * a marker, so the miss stays a plain miss. See https://github.com/MahoCommerce/maho/issues/1402
 */

/** Drives the two section-cache methods against an in-memory cache. */
class ConfigSectionCacheProbe extends Mage_Core_Model_Config
{
    /** @var array<string, string> */
    public array $entries = [];

    public bool $reinitCalled = false;

    public static function marker(): string
    {
        return self::ABSENT_SECTION_CACHE_VALUE;
    }

    /** @return array<string, string> */
    public function saveSection(string $sectionName, Mage_Core_Model_Config_Element $source): array
    {
        $this->_cachePartsForSave = [];
        $this->_saveSectionCache($this->getCacheId(), $sectionName, $source);
        return $this->_cachePartsForSave;
    }

    public function loadSection(string $sectionName): false|SimpleXMLElement
    {
        return $this->_loadSectionCache($sectionName);
    }

    #[\Override]
    public function reinit($options = [])
    {
        $this->reinitCalled = true;
        return $this;
    }

    #[\Override]
    protected function _loadCache($id)
    {
        return $this->entries[$id] ?? false;
    }
}

function configSectionSource(string $xml): Mage_Core_Model_Config_Element
{
    return new Mage_Core_Model_Config_Element($xml);
}

it('stores a marker for a section that the merged config does not contain', function () {
    $probe = new ConfigSectionCacheProbe();

    $parts = $probe->saveSection('crontab', configSectionSource('<config><global/></config>'));

    expect($parts)->toBe(['config_global_crontab' => ConfigSectionCacheProbe::marker()]);
});

it('still stores the real XML for a section that exists', function () {
    $probe = new ConfigSectionCacheProbe();

    $parts = $probe->saveSection('crontab', configSectionSource('<config><crontab><jobs/></crontab></config>'));

    expect($parts['config_global_crontab'])->toContain('<jobs');
});

it('reads the marker as a plain miss, and keeps the config cache', function () {
    $probe = new ConfigSectionCacheProbe();
    $probe->entries['config_global_crontab'] = ConfigSectionCacheProbe::marker();

    expect($probe->loadSection('crontab'))->toBeFalse();
    expect($probe->reinitCalled)->toBeFalse();
});

it('still treats a genuinely missing cache entry as a cache failure', function () {
    $probe = new ConfigSectionCacheProbe();

    expect($probe->loadSection('crontab'))->toBeFalse();
    expect($probe->reinitCalled)->toBeTrue();
});

it('still parses a section that the cache holds', function () {
    $probe = new ConfigSectionCacheProbe();
    $probe->entries['config_global_crontab'] = '<crontab><jobs/></crontab>';

    $section = $probe->loadSection('crontab');

    expect($section)->toBeInstanceOf(Mage_Core_Model_Config_Element::class);
    expect($probe->reinitCalled)->toBeFalse();
});
