<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer attribute model
 *
 * @category   Mage
 * @package    Mage_Customer
 *
 * @method Mage_Customer_Model_Resource_Attribute _getResource()
 * @method Mage_Customer_Model_Resource_Attribute getResource()
 * @method Mage_Customer_Model_Resource_Attribute_Collection getCollection()
 *
 * @method $this setScopeIsVisible(string $value)
 * @method $this setScopeIsRequired(string $value)
 * @method int getScopeMultilineCount()
 * @method $this setScopeMultilineCount(int $value)
 */
class Mage_Customer_Model_Attribute extends Mage_Eav_Model_Attribute
{
    /**
     * Name of the module
     */
    public const MODULE_NAME = 'Mage_Customer';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'customer_entity_attribute';

    /**
     * Prefix of model events object
     *
     * @var string
     */
    protected $_eventObject = 'attribute';

    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/attribute');
    }

    /**
     * Save additional data
     *
     * @inheritDoc
     */
    #[\Override]
    protected function _afterSave()
    {
        $websiteId = (int)$this->getWebsite()->getId();
        $dataFieldPrefix = $websiteId ? 'scope_' : '';

        // See Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Customer
        if ($this->getData($dataFieldPrefix . 'is_required') !== $this->getOrigData($dataFieldPrefix . 'is_required')
            || $this->getData($dataFieldPrefix . 'is_visible') !== $this->getOrigData($dataFieldPrefix . 'is_visible')
        ) {
            $code = $this->getAttributeCode();
            if (in_array($code, ['prefix', 'middlename', 'lastname'])) {
                // Note, with EAV editor it is possible to have different values for customer vs customer address
                // TODO: sync value with customer_address
            }
            if (in_array($code, ['prefix', 'middlename', 'lastname', 'dob', 'taxvat', 'gender'])) {
                // TODO: sync these values back to core_config, e.g. set 'customer/address/prefix_show' to '', 'opt', or 'req'
            }
        }
        return parent::_afterSave();
    }
}
