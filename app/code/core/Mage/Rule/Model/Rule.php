<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract Rule entity data model
 *
 * @category   Mage
 * @package    Mage_Rule
 * @deprecated since 1.7.0.0 use Mage_Rule_Model_Abstract instead
 */
class Mage_Rule_Model_Rule extends Mage_Rule_Model_Abstract
{
    /**
     * Getter for rule combine conditions instance
     *
     * @return Mage_Rule_Model_Condition_Combine
     */
    #[\Override]
    public function getConditionsInstance()
    {
        return Mage::getModel('rule/condition_combine');
    }

    /**
     * Getter for rule actions collection instance
     *
     * @return Mage_Rule_Model_Action_Collection
     */
    #[\Override]
    public function getActionsInstance()
    {
        return Mage::getModel('rule/action_collection');
    }
}
