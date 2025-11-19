<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_SalesRule_Model_Rule_Action_Collection
 *
 * @package    Mage_SalesRule
 *
 * @method $this setType(string $value)
 */
class Mage_SalesRule_Model_Rule_Action_Collection extends Mage_Rule_Model_Action_Collection
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('salesrule/rule_action_collection');
    }

    /**
     * @return array
     */
    #[\Override]
    public function getNewChildSelectOptions()
    {
        $actions = parent::getNewChildSelectOptions();
        $actions = array_merge_recursive($actions, [
            ['value' => 'salesrule/rule_action_product', 'label' => Mage::helper('salesrule')->__('Update the Product')],
        ]);
        return $actions;
    }
}
