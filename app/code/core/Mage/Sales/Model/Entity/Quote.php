<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Entity_Quote extends Mage_Eav_Model_Entity_Abstract
{
    public function __construct()
    {
        $resource = Mage::getSingleton('core/resource');
        $this->setType('quote')->setConnection(
            $resource->getConnection('sales_read'),
            $resource->getConnection('sales_write'),
        );
    }

    /**
     * Retrieve select object for loading base entity row
     *
     * @param \Maho\DataObject|Mage_Sales_Model_Quote $object
     * @param   int $rowId
     * @return  Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadRowSelect($object, $rowId)
    {
        $select = parent::_getLoadRowSelect($object, $rowId);
        if ($object->getSharedStoreIds()) {
            $select->where('store_id IN (?)', $object->getSharedStoreIds());
        }
        return $select;
    }

    /**
     * Loading quote by customer identifier
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int $customerId
     * @return $this
     */
    public function loadByCustomerId($quote, $customerId)
    {
        $collection = Mage::getResourceModel('sales/quote_collection')
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('customer_id', $customerId)
            ->addAttributeToFilter('is_active', 1);

        if ($quote->getSharedStoreIds()) {
            $collection->addAttributeToFilter('store_id', ['in', $quote->getSharedStoreIds()]);
        }

        $collection->setOrder('updated_at', 'desc')
            ->setPageSize(1)
            ->load();

        if ($collection->getSize()) {
            foreach ($collection as $item) {
                $this->load($quote, $item->getId());
                return $this;
            }
        }
        return $this;
    }

    /**
     * Loading quote by identifier
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int $quoteId
     * @return $this
     */
    public function loadByIdWithoutStore($quote, $quoteId)
    {
        $collection = Mage::getResourceModel('sales/quote_collection')
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('entity_id', $quoteId);

        $collection->setPageSize(1)
            ->load();

        if ($collection->getSize()) {
            foreach ($collection as $item) {
                $this->load($quote, $item->getId());
                return $this;
            }
        }
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     * @throws Exception
     */
    public function getReservedOrderId($quote)
    {
        return Mage::getSingleton('eav/config')->getEntityType(Mage_Sales_Model_Order::ENTITY)->fetchNewIncrementId($quote->getStoreId());
    }
}
