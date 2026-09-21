<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * @method Mage_Core_Model_Resource_Config_Data _getResource()
 * @method Mage_Core_Model_Resource_Config_Data getResource()
 * @method Mage_Core_Model_Resource_Config_Data_Collection getCollection()
 * @method array|bool|float|int|string|null getValue()
 * @method $this setValue(array|bool|float|int|string|null $value)
 * @method $this unsConfigId()
 * @method $this unsValue()
 */
class Mage_Core_Model_Config_Data extends Mage_Core_Model_Abstract
{
    public const ENTITY = 'core_config_data';
    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'core_config_data';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'config_data';

    /**
     * Varien model constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/config_data');
    }

    /**
     * Add availability call after load as public
     * @return $this
     */
    #[\Override]
    public function afterLoad()
    {
        $this->_afterLoad();
        return $this;
    }

    /**
     * Check if config data value was changed
     *
     * @return bool
     */
    public function isValueChanged()
    {
        return $this->getValue() != $this->getOldValue();
    }

    /**
     * Get old value from existing config
     *
     * @return string
     */
    public function getOldValue()
    {
        $storeCode   = $this->getStoreCode();
        $websiteCode = $this->getWebsiteCode();
        $path        = $this->getPath();

        if ($storeCode) {
            return Mage::app()->getStore($storeCode)->getConfig($path);
        }
        if ($websiteCode) {
            return Mage::app()->getWebsite($websiteCode)->getConfig($path);
        }
        return (string) Mage::getConfig()->getNode('default/' . $path);
    }

    /**
     * Get value by key for new user data from <section>/groups/<group>/fields/<field>
     *
     * @param string $key
     * @return string
     */
    public function getFieldsetDataValue($key)
    {
        $data = $this->_getData('fieldset_data');
        return (is_array($data) && isset($data[$key])) ? $data[$key] : null;
    }

    public function setConfigId(?int $value): static
    {
        return $this->setData('config_id', $value);
    }

    public function getField(): ?string
    {
        $value = $this->getData('field');
        return $value === null ? null : (string) $value;
    }

    public function setField(?string $value): static
    {
        return $this->setData('field', $value);
    }

    public function getFieldConfig(): SimpleXMLElement|\Maho\Simplexml\Element|false|null
    {
        return $this->getData('field_config');
    }

    public function setFieldConfig(SimpleXMLElement|\Maho\Simplexml\Element|false|null $value): static
    {
        return $this->setData('field_config', $value);
    }

    public function setFieldsetData(?array $value): static
    {
        return $this->setData('fieldset_data', $value);
    }

    public function getGroupId(): int|string|null
    {
        return $this->getData('group_id');
    }

    public function setGroupId(int|string|null $value): static
    {
        return $this->setData('group_id', $value);
    }

    public function setGroups(?array $value): static
    {
        return $this->setData('groups', $value);
    }

    public function getPath(): ?string
    {
        $value = $this->getData('path');
        return $value === null ? null : (string) $value;
    }

    public function setPath(?string $value): static
    {
        return $this->setData('path', $value);
    }

    public function getScope(): ?string
    {
        $value = $this->getData('scope');
        return $value === null ? null : (string) $value;
    }

    public function setScope(?string $value): static
    {
        return $this->setData('scope', $value);
    }

    public function getScopeId(): ?int
    {
        $value = $this->getData('scope_id');
        return $value === null ? null : (int) $value;
    }

    public function setScopeId(?int $value): static
    {
        return $this->setData('scope_id', $value);
    }

    public function getStoreCode(): ?string
    {
        $value = $this->getData('store_code');
        return $value === null ? null : (string) $value;
    }

    public function setStoreCode(?string $value): static
    {
        return $this->setData('store_code', $value);
    }

    public function getWebsiteCode(): ?string
    {
        $value = $this->getData('website_code');
        return $value === null ? null : (string) $value;
    }

    public function setWebsiteCode(?string $value): static
    {
        return $this->setData('website_code', $value);
    }

}
