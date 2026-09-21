<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

/**
 * Directory country format model
 *
 * @package    Mage_Directory
 *
 * @method Mage_Directory_Model_Resource_Country_Format _getResource()
 * @method Mage_Directory_Model_Resource_Country_Format getResource()
 * @method Mage_Directory_Model_Resource_Country_Format_Collection getCollection()
 */

class Mage_Directory_Model_Country_Format extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/country_format');
    }

    public function getCountryId(): ?string
    {
        $value = $this->getData('country_id');
        return $value === null ? null : (string) $value;
    }

    public function setCountryId(?string $value): static
    {
        return $this->setData('country_id', $value);
    }

    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function setType(?string $value): static
    {
        return $this->setData('type', $value);
    }

    public function getFormat(): ?string
    {
        $value = $this->getData('format');
        return $value === null ? null : (string) $value;
    }

    public function setFormat(?string $value): static
    {
        return $this->setData('format', $value);
    }
}
