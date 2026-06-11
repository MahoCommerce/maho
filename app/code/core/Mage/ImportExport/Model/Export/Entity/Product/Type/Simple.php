<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

class Mage_ImportExport_Model_Export_Entity_Product_Type_Simple extends Mage_ImportExport_Model_Export_Entity_Product_Type_Abstract
{
    /**
     * Overridden attributes parameters.
     *
     * @var array
     */
    protected $_attributeOverrides = [
        'has_options'      => ['source_model' => 'eav/entity_attribute_source_boolean'],
        'required_options' => ['source_model' => 'eav/entity_attribute_source_boolean'],
        'created_at'       => ['backend_type' => 'datetime'],
        'updated_at'       => ['backend_type' => 'datetime'],
    ];

    /**
     * Array of attributes codes which are disabled for export.
     *
     * @var array
     */
    protected $_disabledAttrs = [
        'old_id',
        'recurring_profile',
        'is_recurring',
        'tier_price',
        'group_price',
        'category_ids',
    ];
}
