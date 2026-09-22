<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * @method Mage_Core_Model_Resource_Flag _getResource()
 * @method Mage_Core_Model_Resource_Flag getResource()
 * @method bool hasFlagData()
 */
class Mage_Core_Model_Flag extends Mage_Core_Model_Abstract
{
    /**
     * Flag code
     *
     * @var string
     */
    protected $_flagCode;

    /**
     * Init resource model
     * Set flag_code if it is specified in arguments
     */
    #[\Override]
    protected function _construct()
    {
        if ($this->hasData('flag_code')) {
            $this->_flagCode = $this->getData('flag_code');
        }
        $this->_init('core/flag');
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (is_null($this->_flagCode)) {
            Mage::throwException(Mage::helper('core')->__('Please define flag code.'));
        }

        $this->setFlagCode($this->_flagCode);
        $this->setLastUpdate(Mage::app()->getLocale()->formatDateForDb('now'));

        return parent::_beforeSave();
    }

    /**
     * Retrieve flag data
     *
     * @return mixed
     */
    public function getFlagData()
    {
        if ($this->hasFlagData()) {
            return Mage::helper('core/string')->unserialize($this->getData('flag_data'));
        }
        return null;
    }

    /**
     * Set flag data
     *
     * @param mixed $value
     * @return $this
     */
    public function setFlagData($value)
    {
        return $this->setData('flag_data', Mage::helper('core')->jsonEncode($value));
    }

    /**
     * load self (load by flag code)
     *
     * @return $this
     */
    public function loadSelf()
    {
        if (is_null($this->_flagCode)) {
            Mage::throwException(Mage::helper('core')->__('Please define flag code.'));
        }

        return $this->load($this->_flagCode, 'flag_code');
    }

    public function getFlagCode(): ?string
    {
        $value = $this->getData('flag_code');
        return $value === null ? null : (string) $value;
    }

    public function setFlagCode(?string $value): static
    {
        return $this->setData('flag_code', $value);
    }

    public function getLastUpdate(): ?string
    {
        $value = $this->getData('last_update');
        return $value === null ? null : (string) $value;
    }

    public function setLastUpdate(?string $value): static
    {
        return $this->setData('last_update', $value);
    }

    public function getState(): ?int
    {
        $value = $this->getData('state');
        return $value === null ? null : (int) $value;
    }

    public function setState(?int $value): static
    {
        return $this->setData('state', $value);
    }

}
