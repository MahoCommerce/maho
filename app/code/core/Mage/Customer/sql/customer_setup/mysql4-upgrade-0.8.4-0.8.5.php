<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if ($attrId = $this->getAttribute('customer', 'birthdate', 'attribute_id')) {
    $this->getConnection()->delete($this->getTable('eav_attribute'), 'attribute_id=' . $attrId);
}

$this->installEntities();
