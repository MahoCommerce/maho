<?php

/**
 * Base class of the resource models of the blog entities.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

abstract class Maho_Blog_Model_Resource_Abstract extends Mage_Eav_Model_Entity_Abstract
{
    /**
     * The value tables have a unique key on (entity_id, attribute_id, store_id). Without store_id
     * in the row, SQLite and PostgreSQL do not find that key, so the update of a value fails.
     */
    #[\Override]
    protected function _saveAttribute($object, $attribute, $value)
    {
        parent::_saveAttribute($object, $attribute, $value);

        $table = $attribute->getBackend()->getTable();
        $row = array_key_last($this->_attributeValuesToSave[$table]);
        $this->_attributeValuesToSave[$table][$row]['store_id'] = Mage_Core_Model_App::ADMIN_STORE_ID;

        return $this;
    }
}
