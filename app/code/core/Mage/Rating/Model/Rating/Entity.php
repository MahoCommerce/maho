<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

/**
 * @method Mage_Rating_Model_Resource_Rating_Entity _getResource()
 * @method Mage_Rating_Model_Resource_Rating_Entity getResource()
 */
class Mage_Rating_Model_Rating_Entity extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('rating/rating_entity');
    }

    /**
     * @param string $entityCode
     * @return string
     */
    public function getIdByCode($entityCode)
    {
        return $this->_getResource()->getIdByCode($entityCode);
    }

    public function getEntityCode(): ?string
    {
        $value = $this->getData('entity_code');
        return $value === null ? null : (string) $value;
    }

    public function setEntityCode(?string $value): static
    {
        return $this->setData('entity_code', $value);
    }

}
