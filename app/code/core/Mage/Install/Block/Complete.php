<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Block_Complete extends Mage_Install_Block_Abstract
{
    private const AVAILABLE_LANGUAGE_PACKS = [
        'de_DE', 'el_GR', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR', 'pt_PT',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/complete.phtml');
    }

    public function getLocale(): string
    {
        return (string) Mage::getStoreConfig('general/locale/code');
    }

    public function getCountryCode(): ?string
    {
        $parsed = \Locale::parseLocale($this->getLocale());
        return $parsed['region'] ?? null;
    }

    public function getCountryName(): string
    {
        return \Locale::getDisplayRegion($this->getLocale(), 'en');
    }

    public function getLanguageName(): string
    {
        return \Locale::getDisplayLanguage($this->getLocale(), 'en');
    }

    public function hasLanguagePack(): bool
    {
        return in_array($this->getLocale(), self::AVAILABLE_LANGUAGE_PACKS, true);
    }

    public function getLanguagePackName(): string
    {
        return 'mahocommerce/maho-language-' . strtolower($this->getLocale());
    }

    public function needsLocalization(): bool
    {
        return $this->getLocale() !== 'en_US' && $this->getCountryCode() !== null;
    }

    public function getRegionsImportUrl(): string
    {
        return $this->getUrl('install/wizard/regionsImport');
    }

    public function getLanguagePackUrl(): string
    {
        return $this->getUrl('install/wizard/languagePack');
    }
}
