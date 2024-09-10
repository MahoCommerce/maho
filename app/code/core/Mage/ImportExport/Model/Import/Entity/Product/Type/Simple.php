<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Import entity simple product type
 *
 * @category   Mage
 * @package    Mage_ImportExport
 */
class Mage_ImportExport_Model_Import_Entity_Product_Type_Simple extends Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract
{
    /**
     * Attributes' codes which will be allowed anyway, independently from its visibility property.
     *
     * @var array
     */
    protected $_forcedAttributesCodes = [
        'related_tgtr_position_behavior', 'related_tgtr_position_limit',
        'upsell_tgtr_position_behavior', 'upsell_tgtr_position_limit'
    ];
}
