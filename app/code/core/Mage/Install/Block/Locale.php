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

class Mage_Install_Block_Locale extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/locale.phtml');
    }

    public function getLocale(): string
    {
        $locale = $this->getData('locale');
        if ($locale === null) {
            $locale = Mage::app()->getLocale()->getLocaleCode();
            $this->setData('locale', $locale);
        }
        return $locale;
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/localePost');
    }

    public function getChangeUrl(): string
    {
        return $this->getUrl('*/*/localeChange');
    }

    public function getLocaleSelect(): string
    {
        return $this->getLayout()->createBlock('core/html_select')
            ->setName('config[locale]')
            ->setId('locale')
            ->setTitle($this->helper('install')->__('Locale'))
            ->setExtraParams('required')
            ->setValue($this->getLocale())
            ->setOptions(Mage::app()->getLocale()->getTranslatedOptionLocales())
            ->getHtml();
    }

    public function getTimezoneSelect(): string
    {
        return $this->getLayout()->createBlock('core/html_select')
            ->setName('config[timezone]')
            ->setId('timezone')
            ->setTitle($this->helper('install')->__('Time Zone'))
            ->setExtraParams('required')
            ->setValue($this->getTimezone())
            ->setOptions(Mage::app()->getLocale()->getOptionTimezones())
            ->getHtml();
    }

    public function getTimezone(): string
    {
        $timezone = Mage::getSingleton('install/session')->getTimezone() ?: Mage::app()->getLocale()->getTimezone();
        if ($timezone === Mage_Core_Model_Locale::DEFAULT_TIMEZONE) {
            $timezone = 'America/Los_Angeles';
        }
        return $timezone;
    }

    public function getCurrencySelect(): string
    {
        return $this->getLayout()->createBlock('core/html_select')
            ->setName('config[currency]')
            ->setId('currency')
            ->setTitle($this->helper('install')->__('Default Currency'))
            ->setExtraParams('required')
            ->setValue($this->getCurrency())
            ->setOptions(Mage::app()->getLocale()->getOptionCurrencies())
            ->getHtml();
    }

    public function getCurrency(): string
    {
        return Mage::getSingleton('install/session')->getCurrency() ?: Mage::app()->getLocale()->getCurrency();
    }

    public function needsLocalization(): bool
    {
        $locale = $this->getLocale();
        $parsed = \Locale::parseLocale($locale);
        return $locale !== 'en_US' && isset($parsed['region']);
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
        return in_array($this->getLocale(), Mage_Install_Helper_Data::AVAILABLE_LANGUAGE_PACKS, true);
    }

    public function getLanguagePackName(): string
    {
        return 'mahocommerce/maho-language-' . strtolower($this->getLocale());
    }
}
