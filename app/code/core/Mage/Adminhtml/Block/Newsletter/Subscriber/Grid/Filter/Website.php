<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Newsletter_Subscriber_Grid_Filter_Website extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    /**
     * @var Mage_Core_Model_Resource_Website_Collection|null
     */
    protected $_websiteCollection = null;

    /**
     * @return array[]
     */
    #[\Override]
    protected function _getOptions()
    {
        $result = $this->getCollection()->toOptionArray();
        array_unshift($result, ['label' => null, 'value' => null]);
        return $result;
    }

    /**
     * @return Mage_Core_Model_Resource_Website_Collection
     * @throws Mage_Core_Exception
     */
    public function getCollection()
    {
        if (is_null($this->_websiteCollection)) {
            $this->_websiteCollection = Mage::getResourceModel('core/website_collection')
                ->load();
        }

        Mage::register('website_collection', $this->_websiteCollection);

        return $this->_websiteCollection;
    }

    /**
     * @return array|null
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function getCondition()
    {
        $id = $this->getValue();
        if (!$id) {
            return null;
        }

        $website = Mage::app()->getWebsite($id);

        return ['in' => $website->getStoresIds(true)];
    }
}
