<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Entity extends Mage_Eav_Model_Entity_Abstract
{
    public const string DEFAULT_ENTITY_MODEL      = 'eav/entity';
    public const string DEFAULT_ATTRIBUTE_MODEL   = 'eav/entity_attribute';
    public const string DEFAULT_BACKEND_MODEL     = 'eav/entity_attribute_backend_default';
    public const string DEFAULT_FRONTEND_MODEL    = 'eav/entity_attribute_frontend_default';
    public const string DEFAULT_SOURCE_MODEL      = 'eav/entity_attribute_source_config';

    public const string DEFAULT_ENTITY_TABLE      = 'eav/entity';
    public const string DEFAULT_ENTITY_ID_FIELD   = 'entity_id';
    public const string DEFAULT_VALUE_TABLE_PREFIX = 'eav/entity_attribute';

    public function __construct()
    {
        parent::__construct();
        $resource = Mage::getSingleton('core/resource');
        $this->setConnection($resource->getConnection('eav_read'));
    }
}
