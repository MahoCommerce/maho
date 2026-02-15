<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Bundle_Model_Sales_Order_Pdf_Items_Abstract extends Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    /**
     * Getting all available children for Invoice, Shipmen or Creditmemo item
     *
     * @param \Maho\DataObject $item
     * @return array
     */
    public function getChilds($item): ?array
    {
        $orderItems = [];
        $itemsArray = [];

        if ($item instanceof Mage_Sales_Model_Order_Invoice_Item) {
            $orderItems = $item->getInvoice()->getAllItems();
        } elseif ($item instanceof Mage_Sales_Model_Order_Shipment_Item) {
            $orderItems = $item->getShipment()->getAllItems();
        } elseif ($item instanceof Mage_Sales_Model_Order_Creditmemo_Item) {
            $orderItems = $item->getCreditmemo()->getAllItems();
        }

        if ($orderItems) {
            foreach ($orderItems as $orderItem) {
                $parentItem = $orderItem->getOrderItem()->getParentItem();
                if ($parentItem) {
                    $itemsArray[$parentItem->getId()][$orderItem->getOrderItemId()] = $orderItem;
                } else {
                    $itemsArray[$orderItem->getOrderItem()->getId()][$orderItem->getOrderItemId()] = $orderItem;
                }
            }
        }

        return $itemsArray[$item->getOrderItem()->getId()] ?? null;
    }

    /**
     * Retrieve is Shipment Separately flag for Item
     *
     * @param \Maho\DataObject $item
     */
    public function isShipmentSeparately($item = null): bool
    {
        if ($item) {
            if ($item->getOrderItem()) {
                $item = $item->getOrderItem();
            }

            $parentItem = $item->getParentItem();
            if ($parentItem) {
                $options = $parentItem->getProductOptions();
                if ($options) {
                    if (isset($options['shipment_type'])
                        && $options['shipment_type'] == Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY
                    ) {
                        return true;
                    }
                    return false;
                }
            } else {
                $options = $item->getProductOptions();
                if ($options) {
                    if (isset($options['shipment_type'])
                        && $options['shipment_type'] == Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY
                    ) {
                        return false;
                    }
                    return true;
                }
            }
        }

        $options = $this->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['shipment_type'])
                && $options['shipment_type'] == Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve is Child Calculated
     *
     * @param \Maho\DataObject $item
     */
    public function isChildCalculated($item = null): bool
    {
        if ($item) {
            if ($item->getOrderItem()) {
                $item = $item->getOrderItem();
            }

            $parentItem = $item->getParentItem();
            if ($parentItem) {
                $options = $parentItem->getProductOptions();
                if ($options) {
                    if (isset($options['product_calculations']) &&
                        $options['product_calculations'] == Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD
                    ) {
                        return true;
                    }
                    return false;
                }
            } else {
                $options = $item->getProductOptions();
                if ($options) {
                    if (isset($options['product_calculations']) &&
                        $options['product_calculations'] == Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD
                    ) {
                        return false;
                    }
                    return true;
                }
            }
        }

        $options = $this->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['product_calculations'])
                && $options['product_calculations'] == Mage_Catalog_Model_Product_Type_Abstract::CALCULATE_CHILD
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve Bundle Options
     *
     * @param \Maho\DataObject $item
     */
    public function getBundleOptions($item = null): array
    {
        $options = $this->getOrderItem()->getProductOptions();
        if (!$options) {
            return $options['bundle_options'] ?? [];
        }

        return $options['bundle_options'] ?? [];
    }

    /**
     * Retrieve Selection attributes
     *
     * @param \Maho\DataObject $item
     */
    public function getSelectionAttributes($item): mixed
    {
        if ($item instanceof Mage_Sales_Model_Order_Item) {
            $options = $item->getProductOptions();
        } else {
            $options = $item->getOrderItem()->getProductOptions();
        }
        if (isset($options['bundle_selection_attributes'])) {
            return Mage::helper('core/string')->unserialize($options['bundle_selection_attributes']);
        }
        return null;
    }

    /**
     * Retrieve Order options
     *
     * @param \Maho\DataObject $item
     */
    public function getOrderOptions($item = null): array
    {
        $result = [];

        $options = $this->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (!empty($options['attributes_info'])) {
                $result = array_merge($options['attributes_info'], $result);
            }
        }
        return $result;
    }

    /**
     * Retrieve Order Item
     *
     * @return Mage_Sales_Model_Order_Item
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function getOrderItem()
    {
        return $this->getItem()->getOrderItem();
    }

    /**
     * Retrieve Value HTML
     *
     * @param Mage_Sales_Model_Order_Invoice_Item $item
     */
    public function getValueHtml($item): string
    {
        $result = strip_tags($item->getName());
        if (!$this->isShipmentSeparately($item)) {
            $attributes = $this->getSelectionAttributes($item);
            if ($attributes) {
                $result =  sprintf('%d', $attributes['qty']) . ' x ' . $result;
            }
        }
        if (!$this->isChildCalculated($item)) {
            $attributes = $this->getSelectionAttributes($item);
            if ($attributes) {
                $result .= ' ' . strip_tags($this->getOrderItem()->getOrder()->formatPrice($attributes['price']));
            }
        }
        return $result;
    }

    /**
     * Can show price info for item
     *
     * @param Mage_Sales_Model_Order_Invoice_Item $item
     */
    public function canShowPriceInfo($item): bool
    {
        if (($item->getOrderItem()->getParentItem() && $this->isChildCalculated())
            || (!$item->getOrderItem()->getParentItem() && !$this->isChildCalculated())
        ) {
            return true;
        }
        return false;
    }
}
