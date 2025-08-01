<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Items_Grid extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    /**
     * Flag to check can items be move to customer storage
     *
     * @var bool
     */
    protected $_moveToCustomerStorage = true;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_search_grid');
    }

    /**
     * Returns the items
     *
     * @return array
     */
    public function getItems()
    {
        $items = $this->getParentBlock()->getItems();
        $oldSuperMode = $this->getQuote()->getIsSuperMode();
        $this->getQuote()->setIsSuperMode(false);
        foreach ($items as $item) {
            // To dispatch inventory event sales_quote_item_qty_set_after, set item qty
            $item->setQty($item->getQty());
            $stockItem = $item->getProduct()->getStockItem();
            if ($stockItem instanceof Mage_CatalogInventory_Model_Stock_Item) {
                // This check has been performed properly in Inventory observer, so it has no sense
                /*
                $check = $stockItem->checkQuoteItemQty($item->getQty(), $item->getQty(), $item->getQty());
                $item->setMessage($check->getMessage());
                $item->setHasError($check->getHasError());
                */
                if ($item->getProduct()->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
                    $item->setMessage(Mage::helper('adminhtml')->__('This product is currently disabled.'));
                    $item->setHasError(true);
                }
            }
        }
        $this->getQuote()->setIsSuperMode($oldSuperMode);
        return $items;
    }

    /**
     * Returns the item's calculation price
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return float
     */
    public function getItemEditablePrice($item)
    {
        return $item->getCalculationPrice() * 1;
    }

    /**
     * Returns the item's original editable price
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return float
     */
    public function getOriginalEditablePrice($item)
    {
        if ($item->hasOriginalCustomPrice()) {
            $result = $item->getOriginalCustomPrice() * 1;
        } elseif ($item->hasCustomPrice()) {
            $result = $item->getCustomPrice() * 1;
        } elseif (Mage::helper('tax')->priceIncludesTax($this->getStore())) {
            $result = $item->getPriceInclTax() * 1;
        } else {
            $result = $item->getOriginalPrice() * 1;
        }
        return $result;
    }

    /**
     * Returns the item's original price
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return double
     */
    public function getItemOrigPrice($item)
    {
        return $this->convertPrice($item->getPrice());
    }

    /**
     * Returns whether the item's gift message is available
     *
     * @param null|Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    public function isGiftMessagesAvailable($item = null)
    {
        if (!$this->isModuleOutputEnabled('Mage_GiftMessage')) {
            return false;
        }
        /** @var Mage_GiftMessage_Helper_Message $helper */
        $helper = $this->helper('giftmessage/message');

        if (is_null($item)) {
            return $helper->getIsMessagesAvailable($helper::TYPE_ITEMS, $this->getQuote(), $this->getStore());
        }

        return $helper->getIsMessagesAvailable($helper::TYPE_ITEM, $item, $this->getStore());
    }

    /**
     * Returns whether the item is allowed for the gift message
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    public function isAllowedForGiftMessage($item)
    {
        if (!$this->isModuleOutputEnabled('Mage_GiftMessage')) {
            return false;
        }
        return Mage::getSingleton('adminhtml/giftmessage_save')->getIsAllowedQuoteItem($item);
    }

    /**
     * Check if we need display grid totals include tax
     *
     * @return bool
     */
    public function displayTotalsIncludeTax()
    {
        return Mage::getSingleton('tax/config')->displayCartSubtotalInclTax($this->getStore())
            || Mage::getSingleton('tax/config')->displayCartSubtotalBoth($this->getStore());
    }

    /**
     * Returns the subtotal
     *
     * @return float
     */
    public function getSubtotal()
    {
        $address = $this->getQuoteAddress();
        if ($this->displayTotalsIncludeTax()) {
            if ($address->getSubtotalInclTax()) {
                return $address->getSubtotalInclTax();
            }
            return $address->getSubtotal() + $address->getTaxAmount();
        }

        return $address->getSubtotal();
    }

    /**
     * Returns the subtotal with any discount removed
     *
     * @return float
     */
    public function getSubtotalWithDiscount()
    {
        $address = $this->getQuoteAddress();
        if ($this->displayTotalsIncludeTax()) {
            return $address->getSubtotal() + $address->getTaxAmount()
                    + $address->getHiddenTaxAmount() + $this->getDiscountAmount();
        }

        return $address->getSubtotal() + $this->getDiscountAmount();
    }

    /**
     * Return whether the catalog prices include tax
     *
     * @return bool
     */
    public function getIsPriceInclTax()
    {
        return Mage::getSingleton('tax/config')->priceIncludesTax($this->getStore());
    }

    /**
     * Returns the discount amount
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->getQuote()->getShippingAddress()->getDiscountAmount();
    }

    /**
     * Retrieve quote address
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getQuoteAddress()
    {
        if ($this->getQuote()->isVirtual()) {
            return $this->getQuote()->getBillingAddress();
        }

        return $this->getQuote()->getShippingAddress();
    }

    /**
     * Define if specified item has already applied custom price
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    public function usedCustomPriceForItem($item)
    {
        return $item->hasCustomPrice();
    }

    /**
     * Define if custom price can be applied for specified item
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    public function canApplyCustomPrice($item)
    {
        return !$item->isChildrenCalculated();
    }

    /**
     * Returns the string that contains the 'quantity' title
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public function getQtyTitle($item)
    {
        $prices = $item->getProduct()->getTierPrice();
        if ($prices) {
            $info = [];
            foreach ($prices as $data) {
                $qty    = $data['price_qty'] * 1;
                $price  = $this->convertPrice($data['price']);
                $info[] = $this->helper('sales')->__('Buy %s for price %s', $qty, $price);
            }
            return implode(', ', $info);
        }

        return $this->helper('sales')->__('Item ordered qty');
    }

    /**
     * Returns the HTML string for the tiered pricing
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public function getTierHtml($item)
    {
        $html = '';
        $prices = $item->getProduct()->getTierPrice();
        if ($prices) {
            $info = [];
            foreach ($prices as $data) {
                $qty    = $data['price_qty'] * 1;
                $price  = $this->convertPrice($data['price']);
                $info[] = $this->helper('sales')->__('%s for %s', $qty, $price);
            }
            $html = implode('<br/>', $info);
        }
        return $html;
    }

    /**
     * Get Custom Options of item
     *
     * @return string
     */
    public function getCustomOptions(Mage_Sales_Model_Quote_Item $item)
    {
        $optionStr = '';
        $this->_moveToCustomerStorage = true;
        $optionIds = $item->getOptionByCode('option_ids');
        if ($optionIds) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                $option = $item->getProduct()->getOptionById($optionId);
                if ($option) {
                    $optionValue = $item->getOptionByCode('option_' . $option->getId())->getValue();

                    $optionStr .= $option->getTitle() . ':';

                    $quoteItemOption = $item->getOptionByCode('option_' . $option->getId());
                    $group = $option->groupFactory($option->getType())
                        ->setOption($option)
                        ->setQuoteItemOption($quoteItemOption);

                    $optionStr .= $group->getEditableOptionValue($quoteItemOption->getValue());
                    $optionStr .= "\n";
                }
            }
        }
        return $optionStr;
    }

    /**
     * Get flag for rights to move items to customer storage
     *
     * @return bool
     */
    public function getMoveToCustomerStorage()
    {
        return $this->_moveToCustomerStorage;
    }

    /**
     * Returns the item's subtotal that includes tax
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public function displaySubtotalInclTax($item)
    {
        if ($item->getTaxBeforeDiscount()) {
            $tax = $item->getTaxBeforeDiscount();
        } else {
            $tax = $item->getTaxAmount() ?: 0;
        }
        return $this->formatPrice($item->getRowTotal() + $tax);
    }

    /**
     * Returns the item's original price that includes tax
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return double
     */
    public function displayOriginalPriceInclTax($item)
    {
        $tax = 0;
        if ($item->getTaxPercent()) {
            $tax = $item->getPrice() * ($item->getTaxPercent() / 100);
        }
        return $this->convertPrice($item->getPrice() + ($tax / $item->getQty()));
    }

    /**
     * Returns the item's row total with any discount and also with any tax
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public function displayRowTotalWithDiscountInclTax($item)
    {
        $tax = ($item->getTaxAmount() ?: 0);
        return $this->formatPrice($item->getRowTotal() - $item->getDiscountAmount() + $tax);
    }

    /**
     * Returns the text for the custom price (whether it includes or excludes tax)
     *
     * @return string
     */
    public function getInclExclTaxMessage()
    {
        if (Mage::helper('tax')->priceIncludesTax($this->getStore())) {
            return Mage::helper('sales')->__('* Enter custom price including tax');
        }

        return Mage::helper('sales')->__('* Enter custom price excluding tax');
    }

    /**
     * Returns the store
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getStore()
    {
        return $this->getQuote()->getStore();
    }

    /**
     * Return html button which calls configure window
     *
     * @param  Mage_Sales_Model_Quote_Item $item
     * @return string
     */
    public function getConfigureButtonHtml($item)
    {
        $product = $item->getProduct();

        $options = ['label' => Mage::helper('sales')->__('Configure')];
        if ($product->canConfigure()) {
            $options['onclick'] = sprintf('order.showQuoteItemConfiguration(%s)', $item->getId());
        } else {
            $options['class'] = ' disabled';
            $options['title'] = Mage::helper('sales')->__('This product does not have any configurable options');
        }

        return $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData($options)
            ->toHtml();
    }

    /**
     * Get order item extra info block
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return Mage_Core_Block_Abstract
     */
    public function getItemExtraInfo($item)
    {
        return $this->getLayout()
            ->getBlock('order_item_extra_info')
            ->setItem($item);
    }

    /**
     * Returns whether moving to wishlist is allowed for this item
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @return bool
     */
    public function isMoveToWishlistAllowed($item)
    {
        return Mage::helper('wishlist')->isAllow() && $item->getProduct()->isVisibleInSiteVisibility();
    }

    /**
     * Retrieve collection of customer wishlists
     *
     * @return Mage_Wishlist_Model_Resource_Wishlist_Collection
     * @throws Mage_Core_Exception
     */
    public function getCustomerWishlists()
    {
        return Mage::getModel('wishlist/wishlist')->getCollection()
            ->filterByCustomerId($this->getCustomerId());
    }
}
