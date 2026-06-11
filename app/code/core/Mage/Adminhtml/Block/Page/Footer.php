<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Page_Footer extends Mage_Adminhtml_Block_Template
{
    public const LOCALE_CACHE_LIFETIME = 7200;
    public const LOCALE_CACHE_KEY      = 'footer_locale';
    public const LOCALE_CACHE_TAG      = 'adminhtml';

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('page/footer.phtml');
        $this->setShowProfiler(true);
    }

    /**
     * @return string
     */
    public function getChangeLocaleUrl()
    {
        return $this->getUrl('adminhtml/index/changeLocale');
    }

    /**
     * @return string
     */
    public function getUrlForReferer()
    {
        return $this->getUrlEncoded('*/*/*', ['_current' => true]);
    }

    /**
     * @return string
     */
    public function getRefererParamName()
    {
        return Mage_Core_Controller_Varien_Action::PARAM_NAME_URL_ENCODED;
    }

    /**
     * @return string
     */
    public function getLanguageSelect()
    {
        $locale  = Mage::app()->getLocale();
        $cacheId = self::LOCALE_CACHE_KEY . $locale->getLocaleCode();
        $html    = Mage::app()->loadCache($cacheId);

        if (!$html) {
            $html = $this->getLayout()->createBlock('adminhtml/html_select')
                ->setName('locale')
                ->setId('interface_locale')
                ->setTitle(Mage::helper('page')->__('Interface Language'))
                ->setExtraParams('style="width:200px"')
                ->setValue($locale->getLocaleCode())
                ->setOptions($locale->getOptionLocales())
                ->getHtml();
            Mage::app()->saveCache($html, $cacheId, [self::LOCALE_CACHE_TAG], self::LOCALE_CACHE_LIFETIME);
        }

        return $html;
    }

    /**
     * @return $this
     */
    public function setReportIssuesUrl(string $url)
    {
        return $this->setData('report_issues_url', $url);
    }

    public function getReportIssuesUrl(): string
    {
        return (string) $this->_getData('report_issues_url');
    }

    /**
     * @return $this
     */
    public function setMahoProjectUrl(string $url)
    {
        return $this->setData('maho_project_url', $url);
    }

    public function getMahoProjectUrl(): string
    {
        return (string) $this->_getData('maho_project_url');
    }
}
