<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

abstract class Mage_Eav_Model_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    /**
     * Name of the module
     * Override it
     */
    //const MODULE_NAME = 'Mage_Eav';

    /**
     * Prefix of model events object
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'attribute';

    /**
     * Active Website instance
     *
     * @var Mage_Core_Model_Website|null
     */
    protected $_website;

    /**
     * Set active website instance
     *
     * @param Mage_Core_Model_Website|int $website
     * @return Mage_Eav_Model_Attribute
     */
    public function setWebsite($website)
    {
        $this->_website = Mage::app()->getWebsite($website);
        return $this;
    }

    /**
     * Return active website instance
     *
     * @return Mage_Core_Model_Website
     */
    public function getWebsite()
    {
        $this->_website ??= Mage::app()->getWebsite();
        return $this->_website;
    }

    #[\Override]
    protected function _afterSave()
    {
        Mage::getSingleton('eav/config')->clear();
        return parent::_afterSave();
    }

    /**
     * Return forms in which the attribute
     *
     * @return array
     */
    public function getUsedInForms()
    {
        $forms = $this->getData('used_in_forms');
        if (is_null($forms)) {
            $forms = $this->_getResource()->getUsedInForms($this);
            $this->setData('used_in_forms', $forms);
        }
        return $forms;
    }

    /**
     * Return scope value by key
     *
     * @param string $key
     * @return mixed
     */
    protected function _getScopeValue($key)
    {
        $scopeKey = sprintf('scope_%s', $key);
        return $this->getData($scopeKey) ?? $this->getData($key);
    }

    /**
     * Return is attribute value required
     */
    #[\Override]
    public function getIsRequired(): ?bool
    {
        $value = $this->_getScopeValue('is_required');
        return $value === null ? null : (bool) $value;
    }

    /**
     * Return is visible attribute flag
     */
    #[\Override]
    public function getIsVisible(): ?bool
    {
        $value = $this->_getScopeValue('is_visible');
        return $value === null ? null : (bool) $value;
    }

    /**
     * Return default value for attribute
     *
     * @return mixed
     */
    #[\Override]
    public function getDefaultValue()
    {
        return $this->_getScopeValue('default_value');
    }

    /**
     * Return count of lines for multiply line attribute
     *
     * @return mixed
     */
    public function getMultilineCount()
    {
        return $this->_getScopeValue('multiline_count');
    }
}
