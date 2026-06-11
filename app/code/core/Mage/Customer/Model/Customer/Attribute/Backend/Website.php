<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Customer_Attribute_Backend_Website extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    #[\Override]
    public function beforeSave($object)
    {
        if ($object->getId()) {
            return $this;
        }
        if (!$object->hasData('website_id')) {
            $object->setData('website_id', Mage::app()->getStore()->getWebsiteId());
        }
        return $this;
    }
}
