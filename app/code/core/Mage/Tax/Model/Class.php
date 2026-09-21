<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Resource_Class _getResource()
 * @method Mage_Tax_Model_Resource_Class getResource()
 * @method Mage_Tax_Model_Resource_Class_Collection getCollection()
 */

class Mage_Tax_Model_Class extends Mage_Core_Model_Abstract
{
    public const TAX_CLASS_TYPE_CUSTOMER   = 'CUSTOMER';
    public const TAX_CLASS_TYPE_PRODUCT    = 'PRODUCT';

    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/class');
    }

    public function getClassName(): ?string
    {
        $value = $this->getData('class_name');
        return $value === null ? null : (string) $value;
    }

    public function setClassName(?string $value): static
    {
        return $this->setData('class_name', $value);
    }

    public function getClassType(): ?string
    {
        $value = $this->getData('class_type');
        return $value === null ? null : (string) $value;
    }

    public function setClassType(?string $value): static
    {
        return $this->setData('class_type', $value);
    }
}
