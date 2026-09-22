<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Sales implementation of recurring payment profiles
 * Implements saving and manageing profiles
 *
 * @method Mage_Sales_Model_Resource_Recurring_Profile _getResource()
 * @method Mage_Sales_Model_Resource_Recurring_Profile getResource()
 * @method float getBillingAmount()
 * @method float getTrialBillingAmount()
 */
class Mage_Sales_Model_Recurring_Profile extends Mage_Payment_Model_Recurring_Profile
{
    /**
     * Available states
     *
     * @var string
     */
    public const STATE_UNKNOWN   = 'unknown';
    public const STATE_PENDING   = 'pending';
    public const STATE_ACTIVE    = 'active';
    public const STATE_SUSPENDED = 'suspended';
    public const STATE_CANCELED  = 'canceled';
    public const STATE_EXPIRED   = 'expired';

    /**
     * Payment types
     *
     * @var string
     */
    public const PAYMENT_TYPE_REGULAR   = 'regular';
    public const PAYMENT_TYPE_TRIAL     = 'trial';
    public const PAYMENT_TYPE_INITIAL   = 'initial';

    /**
     * Allowed actions matrix
     *
     * @var array
     */
    protected $_workflow = null;

    /**
     * Load order by system increment identifier
     *
     * @param int $internalReferenceId
     * @return $this
     */
    public function loadByInternalReferenceId($internalReferenceId)
    {
        return $this->load($internalReferenceId, 'internal_reference_id');
    }

    /**
     * Submit a recurring profile right after an order is placed
     */
    public function submit()
    {
        $this->_getResource()->beginTransaction();
        try {
            $this->setInternalReferenceId(Mage::helper('core')->uniqHash('temporary-'));
            $this->save();
            $this->setInternalReferenceId(Mage::helper('core')->uniqHash($this->getId() . '-'));
            $this->getMethodInstance()->submitRecurringProfile($this, $this->getQuote()->getPayment());
            $this->save();
            $this->_getResource()->commit();
        } catch (Exception $e) {
            $this->_getResource()->rollBack();
            throw $e;
        }
    }

    /**
     * Activate the suspended profile
     */
    public function activate()
    {
        $this->_checkWorkflow(self::STATE_ACTIVE, false);
        $this->setNewState(self::STATE_ACTIVE);
        $this->getMethodInstance()->updateRecurringProfileStatus($this);
        $this->setState(self::STATE_ACTIVE)
            ->save();
    }

    /**
     * Check whether the workflow allows to activate the profile
     *
     * @return bool
     */
    public function canActivate()
    {
        return $this->_checkWorkflow(self::STATE_ACTIVE);
    }

    /**
     * Suspend active profile
     */
    public function suspend()
    {
        $this->_checkWorkflow(self::STATE_SUSPENDED, false);
        $this->setNewState(self::STATE_SUSPENDED);
        $this->getMethodInstance()->updateRecurringProfileStatus($this);
        $this->setState(self::STATE_SUSPENDED)
            ->save();
    }

    /**
     * Check whether the workflow allows to suspend the profile
     *
     * @return bool
     */
    public function canSuspend()
    {
        return $this->_checkWorkflow(self::STATE_SUSPENDED);
    }

    /**
     * Cancel active or suspended profile
     */
    public function cancel()
    {
        $this->_checkWorkflow(self::STATE_CANCELED, false);
        $this->setNewState(self::STATE_CANCELED);
        $this->getMethodInstance()->updateRecurringProfileStatus($this);
        $this->setState(self::STATE_CANCELED)
            ->save();
    }

    /**
     * Check whether the workflow allows to cancel the profile
     *
     * @return bool
     */
    public function canCancel()
    {
        return $this->_checkWorkflow(self::STATE_CANCELED);
    }

    public function fetchUpdate()
    {
        $result = new \Maho\DataObject();
        $this->getMethodInstance()->getRecurringProfileDetails($this->getReferenceId(), $result);

        if ($result->getIsProfileActive()) {
            $this->setState(self::STATE_ACTIVE);
        } elseif ($result->getIsProfilePending()) {
            $this->setState(self::STATE_PENDING);
        } elseif ($result->getIsProfileCanceled()) {
            $this->setState(self::STATE_CANCELED);
        } elseif ($result->getIsProfileSuspended()) {
            $this->setState(self::STATE_SUSPENDED);
        } elseif ($result->getIsProfileExpired()) {
            $this->setState(self::STATE_EXPIRED);
        }
    }

    /**
     * @return mixed
     */
    public function canFetchUpdate()
    {
        return $this->getMethodInstance()->canGetRecurringProfileDetails();
    }

    /**
     * Initialize new order based on profile data
     *
     * Takes arbitrary number of \Maho\DataObject instances to be treated as items for new order
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        $items = [];
        $itemInfoObjects = func_get_args();

        $billingAmount = 0;
        $shippingAmount = 0;
        $taxAmount = 0;
        $isVirtual = 1;
        $weight = 0;
        foreach ($itemInfoObjects as $itemInfo) {
            $item = $this->_getItem($itemInfo);
            $billingAmount += $item->getPrice();
            $shippingAmount += $item->getShippingAmount();
            $taxAmount += $item->getTaxAmount();
            $weight += $item->getWeight();
            if (!$item->getIsVirtual()) {
                $isVirtual = 0;
            }
            $items[] = $item;
        }
        $grandTotal = $billingAmount + $shippingAmount + $taxAmount;

        $order = Mage::getModel('sales/order');

        $billingAddress = Mage::getModel('sales/order_address')
            ->setData($this->getBillingAddressInfo())
            ->setId(null);

        $shippingInfo = $this->getShippingAddressInfo();
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setData($shippingInfo)
            ->setId(null);

        $payment = Mage::getModel('sales/order_payment')
            ->setMethod($this->getMethodCode());

        $transferDataKays = [
            'store_id',             'store_name',           'customer_id',          'customer_email',
            'customer_firstname',   'customer_lastname',    'customer_middlename',  'customer_prefix',
            'customer_suffix',      'customer_taxvat',      'customer_gender',      'customer_is_guest',
            'customer_note_notify', 'customer_group_id',    'customer_note',        'shipping_method',
            'shipping_description', 'base_currency_code',   'global_currency_code', 'order_currency_code',
            'store_currency_code',  'base_to_global_rate',  'base_to_order_rate',   'store_to_base_rate',
            'store_to_order_rate',
        ];

        $orderInfo = $this->getOrderInfo();
        foreach ($transferDataKays as $key) {
            if (isset($orderInfo[$key])) {
                $order->setData($key, $orderInfo[$key]);
            } elseif (isset($shippingInfo[$key])) {
                $order->setData($key, $shippingInfo[$key]);
            }
        }

        $order->setStoreId($this->getStoreId())
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setBaseToOrderRate($this->getInfoValue('order_info', 'base_to_quote_rate'))
            ->setStoreToOrderRate($this->getInfoValue('order_info', 'store_to_quote_rate'))
            ->setOrderCurrencyCode($this->getInfoValue('order_info', 'quote_currency_code'))
            ->setBaseSubtotal($billingAmount)
            ->setSubtotal($billingAmount)
            ->setBaseShippingAmount($shippingAmount)
            ->setShippingAmount($shippingAmount)
            ->setBaseTaxAmount($taxAmount)
            ->setTaxAmount($taxAmount)
            ->setBaseGrandTotal($grandTotal)
            ->setGrandTotal($grandTotal)
            ->setIsVirtual((bool) $isVirtual)
            ->setWeight($weight)
            ->setTotalQtyOrdered($this->getInfoValue('order_info', 'items_qty'))
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setPayment($payment);

        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    /**
     * Validate states
     *
     * @return bool
     */
    #[\Override]
    public function isValid()
    {
        parent::isValid();

        // state
        if (!in_array($this->getState(), $this->getAllStates(false), true)) {
            $this->_errors['state'][] = Mage::helper('sales')->__('Wrong state: "%s".', $this->getState());
        }

        return empty($this->_errors);
    }

    /**
     * Import quote information to the profile
     *
     * @return $this
     * @throws Exception
     */
    public function importQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->setQuote($quote);

        if ($quote->getPayment() && $quote->getPayment()->getMethod()) {
            $this->setMethodInstance($quote->getPayment()->getMethodInstance());
        }

        $orderInfo = $quote->getData();
        $this->_cleanupArray($orderInfo);
        $this->setOrderInfo($orderInfo);

        $addressInfo = $quote->getBillingAddress()->getData();
        $this->_cleanupArray($addressInfo);
        $this->setBillingAddressInfo($addressInfo);
        if (!$quote->isVirtual()) {
            $addressInfo = $quote->getShippingAddress()->getData();
            $this->_cleanupArray($addressInfo);
            $this->setShippingAddressInfo($addressInfo);
        }

        $this->setCurrencyCode($quote->getBaseCurrencyCode());
        $this->setCustomerId($quote->getCustomerId());
        $this->setStoreId($quote->getStoreId());

        return $this;
    }

    /**
     * Import quote item information to the profile
     *
     * @return $this
     */
    public function importQuoteItem(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        $this->setQuoteItemInfo($item);

        // TODO: make it abstract from amounts
        $this->setBillingAmount($item->getBaseRowTotal())
            ->setTaxAmount($item->getBaseTaxAmount())
            ->setShippingAmount($item->getBaseShippingAmount())
        ;
        if (!$this->getScheduleDescription()) {
            $this->setScheduleDescription($item->getName());
        }

        $orderItemInfo = $item->getData();
        $this->_cleanupArray($orderItemInfo);

        $customOptions = $item->getOptionsByCode();
        if ($customOptions['info_buyRequest']) {
            $orderItemInfo['info_buyRequest'] = $customOptions['info_buyRequest']->getValue();
        }

        $this->setOrderItemInfo($orderItemInfo);

        return $this->_filterValues();
    }

    /**
     * Getter for sales-related field labels
     *
     * @param string $field
     * @return string|null
     */
    #[\Override]
    public function getFieldLabel($field)
    {
        return match ($field) {
            'order_item_id' => Mage::helper('sales')->__('Purchased Item'),
            'state' => Mage::helper('sales')->__('Profile State'),
            'created_at' => Mage::helper('adminhtml')->__('Created At'),
            'updated_at' => Mage::helper('adminhtml')->__('Updated At'),
            default => parent::getFieldLabel($field),
        };
    }

    /**
     * Getter for sales-related field comments
     *
     * @param string $field
     * @return string|null
     */
    #[\Override]
    public function getFieldComment($field)
    {
        return match ($field) {
            'order_item_id' => Mage::helper('sales')->__('Original order item that recurring payment profile correspondss to.'),
            default => parent::getFieldComment($field),
        };
    }

    /**
     * Getter for all available states
     *
     * @param bool $withLabels
     * @return array
     */
    public function getAllStates($withLabels = true)
    {
        $states = [self::STATE_UNKNOWN, self::STATE_PENDING, self::STATE_ACTIVE,
            self::STATE_SUSPENDED, self::STATE_CANCELED, self::STATE_EXPIRED,
        ];
        if ($withLabels) {
            $result = [];
            foreach ($states as $state) {
                $result[$state] = $this->getStateLabel($state);
            }
            return $result;
        }
        return $states;
    }

    /**
     * Get state label based on the code
     *
     * @param string $state
     * @return string
     */
    public function getStateLabel($state)
    {
        return match ($state) {
            self::STATE_UNKNOWN => Mage::helper('sales')->__('Not Initialized'),
            self::STATE_PENDING => Mage::helper('sales')->__('Pending'),
            self::STATE_ACTIVE => Mage::helper('sales')->__('Active'),
            self::STATE_SUSPENDED => Mage::helper('sales')->__('Suspended'),
            self::STATE_CANCELED => Mage::helper('sales')->__('Canceled'),
            self::STATE_EXPIRED => Mage::helper('sales')->__('Expired'),
            default => $state,
        };
    }

    /**
     * Render state as label
     *
     * @param string $key
     * @return mixed
     */
    #[\Override]
    public function renderData($key)
    {
        $value = $this->_getData($key);
        return match ($key) {
            'state' => $this->getStateLabel($value),
            default => parent::renderData($key),
        };
    }

    /**
     * Getter for additional information value
     * It is assumed that the specified additional info is an object or associative array
     *
     * @param string $infoKey
     * @param string $infoValueKey
     * @return mixed|null
     */
    public function getInfoValue($infoKey, $infoValueKey)
    {
        $info = $this->getData($infoKey);
        if (!$info) {
            return null;
        }
        if (!is_object($info)) {
            if (is_array($info) && isset($info[$infoValueKey])) {
                return $info[$infoValueKey];
            }
        } else {
            if ($info instanceof \Maho\DataObject) {
                return $info->getDataUsingMethod($infoValueKey);
            }
            if (isset($info->$infoValueKey)) {
                return $info->$infoValueKey;
            }
        }
        return null;
    }

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/recurring_profile');
    }

    /**
     * Automatically set "unknown" state if not defined
     */
    #[\Override]
    protected function _filterValues()
    {
        $result = parent::_filterValues();

        if (!$this->getState()) {
            $this->setState(self::STATE_UNKNOWN);
        }

        return $result;
    }

    /**
     * Initialize the workflow reference
     */
    protected function _initWorkflow()
    {
        if ($this->_workflow === null) {
            $this->_workflow = [
                'unknown'   => ['pending', 'active', 'suspended', 'canceled'],
                'pending'   => ['active', 'canceled'],
                'active'    => ['suspended', 'canceled'],
                'suspended' => ['active', 'canceled'],
                'canceled'  => [],
                'expired'   => [],
            ];
        }
    }

    /**
     * Check whether profile can be changed to specified state
     *
     * @param string $againstState
     * @param bool $soft
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _checkWorkflow($againstState, $soft = true)
    {
        $this->_initWorkflow();
        $state = $this->getState();
        $result = (!empty($this->_workflow[$state])) && in_array($againstState, $this->_workflow[$state]);
        if (!$soft && !$result) {
            Mage::throwException(
                Mage::helper('sales')->__('This profile state cannot be changed to "%s".', $againstState),
            );
        }
        return $result;
    }

    /**
     * Return recurring profile child orders Ids
     *
     * @return array
     */
    public function getChildOrderIds()
    {
        $ids = $this->_getResource()->getChildOrderIds($this);
        if (empty($ids)) {
            $ids[] = '-1';
        }
        return $ids;
    }

    /**
     * Add order relation to recurring profile
     *
     * @param int $orderId
     * @return $this
     */
    public function addOrderRelation($orderId)
    {
        $this->getResource()->addOrderRelation($this->getId(), $orderId);
        return $this;
    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     *
     * @param \Maho\DataObject $itemInfo
     * @return Mage_Sales_Model_Order_Item|void
     */
    protected function _getItem($itemInfo)
    {
        $paymentType = $itemInfo->getPaymentType();
        if (!$paymentType) {
            throw new Exception('Recurring profile payment type is not specified.');
        }

        switch ($paymentType) {
            case self::PAYMENT_TYPE_REGULAR:
                return $this->_getRegularItem($itemInfo);
            case self::PAYMENT_TYPE_TRIAL:
                return $this->_getTrialItem($itemInfo);
            case self::PAYMENT_TYPE_INITIAL:
                return $this->_getInitialItem($itemInfo);
            default:
                new Exception("Invalid recurring profile payment type '{$paymentType}'.");
        }
    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     * for regular payment
     *
     * @param \Maho\DataObject $itemInfo
     * @return Mage_Sales_Model_Order_Item
     */
    protected function _getRegularItem($itemInfo)
    {
        $price = $itemInfo->getPrice() ?: $this->getBillingAmount();
        $shippingAmount = $itemInfo->getShippingAmount() ?: $this->getShippingAmount();
        $taxAmount = $itemInfo->getTaxAmount() ?: $this->getTaxAmount();

        return Mage::getModel('sales/order_item')
            ->setData($this->getOrderItemInfo())
            ->setQtyOrdered($this->getInfoValue('order_item_info', 'qty'))
            ->setBaseOriginalPrice($this->getInfoValue('order_item_info', 'price'))
            ->setPrice($price)
            ->setBasePrice($price)
            ->setRowTotal($price)
            ->setBaseRowTotal($price)
            ->setTaxAmount($taxAmount)
            ->setShippingAmount($shippingAmount)
            ->setId(null);
    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     * for trial payment
     *
     * @param \Maho\DataObject $itemInfo
     * @return Mage_Sales_Model_Order_Item
     */
    protected function _getTrialItem($itemInfo)
    {
        $item = $this->_getRegularItem($itemInfo);

        $item->setName(
            Mage::helper('sales')->__('Trial ') . $item->getName(),
        );

        $option = [
            'label' => Mage::helper('sales')->__('Payment type'),
            'value' => Mage::helper('sales')->__('Trial period payment'),
        ];

        $this->_addAdditionalOptionToItem($item, $option);

        return $item;
    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     * for initial payment
     *
     * @param \Maho\DataObject $itemInfo
     * @return Mage_Sales_Model_Order_Item
     */
    protected function _getInitialItem($itemInfo)
    {
        $price = $itemInfo->getPrice() ?: $this->getInitAmount();
        $shippingAmount = $itemInfo->getShippingAmount() ?: 0;
        $taxAmount = $itemInfo->getTaxAmount() ?: 0;
        $item = Mage::getModel('sales/order_item')
            ->setStoreId($this->getStoreId())
            ->setProductType(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
            ->setIsVirtual()
            ->setSku('initial_fee')
            ->setName(Mage::helper('sales')->__('Recurring Profile Initial Fee'))
            ->setDescription('')
            ->setWeight(0)
            ->setQtyOrdered(1)
            ->setPrice($price)
            ->setOriginalPrice($price)
            ->setBasePrice($price)
            ->setBaseOriginalPrice($price)
            ->setRowTotal($price)
            ->setBaseRowTotal($price)
            ->setTaxAmount($taxAmount)
            ->setShippingAmount($shippingAmount);

        $option = [
            'label' => Mage::helper('sales')->__('Payment type'),
            'value' => Mage::helper('sales')->__('Initial period payment'),
        ];

        $this->_addAdditionalOptionToItem($item, $option);
        return $item;
    }

    /**
     * Add additional options suboption into itev
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $option
     */
    protected function _addAdditionalOptionToItem($item, $option)
    {
        $options = $item->getProductOptions();
        $additionalOptions = $item->getProductOptionByCode('additional_options');
        if (is_array($additionalOptions)) {
            $additionalOptions[] = $option;
        } else {
            $additionalOptions = [$option];
        }
        $options['additional_options'] = $additionalOptions;
        $item->setProductOptions($options);
    }

    /**
     * Recursively cleanup array from objects
     *
     * @param array $array
     */
    private function _cleanupArray(&$array): void
    {
        if (!$array) {
            return;
        }
        foreach ($array as $key => $value) {
            if (is_object($value)) {
                unset($array[$key]);
            } elseif (is_array($value)) {
                $this->_cleanupArray($array[$key]);
            }
        }
    }

    public function getAdditionalInfo(): ?string
    {
        $value = $this->getData('additional_info');
        return $value === null ? null : (string) $value;
    }

    public function setAdditionalInfo(?string $value): static
    {
        return $this->setData('additional_info', $value);
    }

    public function getBillFailedLater(): ?bool
    {
        $value = $this->getData('bill_failed_later');
        return $value === null ? null : (bool) $value;
    }

    public function setBillFailedLater(?bool $value = true): static
    {
        return $this->setData('bill_failed_later', $value);
    }

    public function getBillingAddressInfo(): array|string|null
    {
        return $this->getData('billing_address_info');
    }

    public function setBillingAddressInfo(array|string|null $value): static
    {
        return $this->setData('billing_address_info', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getInitAmount(): ?float
    {
        $value = $this->getData('init_amount');
        return $value === null ? null : (float) $value;
    }

    public function setInitAmount(?float $value): static
    {
        return $this->setData('init_amount', $value);
    }

    public function getInitMayFail(): ?bool
    {
        $value = $this->getData('init_may_fail');
        return $value === null ? null : (bool) $value;
    }

    public function setInitMayFail(?bool $value = true): static
    {
        return $this->setData('init_may_fail', $value);
    }

    public function setNewState(?string $value): static
    {
        return $this->setData('new_state', $value);
    }

    public function getOrderInfo(): array|string|null
    {
        return $this->getData('order_info');
    }

    public function setOrderInfo(array|string|null $value): static
    {
        return $this->setData('order_info', $value);
    }

    public function getOrderItemInfo(): array|string|null
    {
        return $this->getData('order_item_info');
    }

    public function setOrderItemInfo(array|string|null $value): static
    {
        return $this->setData('order_item_info', $value);
    }

    public function getPeriodMaxCycles(): ?int
    {
        $value = $this->getData('period_max_cycles');
        return $value === null ? null : (int) $value;
    }

    public function setPeriodMaxCycles(?int $value): static
    {
        return $this->setData('period_max_cycles', $value);
    }

    public function getProfileVendorInfo(): ?string
    {
        $value = $this->getData('profile_vendor_info');
        return $value === null ? null : (string) $value;
    }

    public function setProfileVendorInfo(?string $value): static
    {
        return $this->setData('profile_vendor_info', $value);
    }

    public function getQuote(): ?Mage_Sales_Model_Quote
    {
        return $this->getData('quote');
    }

    public function getReferenceId(): ?string
    {
        $value = $this->getData('reference_id');
        return $value === null ? null : (string) $value;
    }

    public function setReferenceId(?string $value): static
    {
        return $this->setData('reference_id', $value);
    }

    public function getShippingAddressInfo(): array|string|null
    {
        return $this->getData('shipping_address_info');
    }

    public function setShippingAddressInfo(array|string|null $value): static
    {
        return $this->setData('shipping_address_info', $value);
    }

    public function getShippingAmount(): ?float
    {
        $value = $this->getData('shipping_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingAmount(?float $value): static
    {
        return $this->setData('shipping_amount', $value);
    }

    public function getState(): ?string
    {
        $value = $this->getData('state');
        return $value === null ? null : (string) $value;
    }

    public function setState(?string $value): static
    {
        return $this->setData('state', $value);
    }

    public function getSubscriberName(): ?string
    {
        $value = $this->getData('subscriber_name');
        return $value === null ? null : (string) $value;
    }

    public function setSubscriberName(?string $value): static
    {
        return $this->setData('subscriber_name', $value);
    }

    public function getSuspensionThreshold(): ?int
    {
        $value = $this->getData('suspension_threshold');
        return $value === null ? null : (int) $value;
    }

    public function setSuspensionThreshold(?int $value): static
    {
        return $this->setData('suspension_threshold', $value);
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
