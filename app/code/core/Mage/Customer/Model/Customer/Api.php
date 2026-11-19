<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Customer_Model_Customer_Api extends Mage_Customer_Model_Api_Resource
{
    protected $_mapAttributes = [
        'customer_id' => 'entity_id',
    ];
    /**
     * Prepare data to insert/update.
     * Creating array for stdClass Object
     *
     * @param array $data
     * @return array
     */
    protected function _prepareData($data)
    {
        foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
            if (isset($data[$attributeAlias])) {
                $data[$attributeCode] = $data[$attributeAlias];
                unset($data[$attributeAlias]);
            }
        }
        return $data;
    }

    /**
     * Create new customer
     *
     * @param array $customerData
     * @return int
     */
    public function create($customerData)
    {
        $customer = Mage::getModel('customer/customer');
        $customerData = $this->_prepareData($customerData);
        if (is_array($customerData) && array_key_exists('confirmation', $customerData)) {
            $customerData['confirmation'] = $this->_prepareConfirmationForWrite($customer, $customerData['confirmation']);
        }

        try {
            $customer
                ->setData($customerData)
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        return $customer->getId();
    }

    /**
     * Retrieve customer data
     *
     * @param int $customerId
     * @param array $attributes
     * @return array
     */
    public function info($customerId, $attributes = null)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }

        if (!is_null($attributes) && !is_array($attributes)) {
            $attributes = [$attributes];
        }

        $result = [];

        foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
            $result[$attributeAlias] = $customer->getData($attributeCode);
        }

        foreach ($this->_getReadableAttributeCodes($customer, $attributes) as $attributeCode) {
            $result[$attributeCode] = $this->_prepareAttributeValueForRead($attributeCode, $customer->getData($attributeCode));
        }

        return $result;
    }

    /**
     * Retrieve customers data
     *
     * @param  object|array $filters
     * @return array
     */
    public function items($filters)
    {
        $collection = Mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*');
        /** @var Mage_Api_Helper_Data $apiHelper */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_mapAttributes);
        try {
            foreach ($filters as $field => $value) {
                if (!$this->_isAllowedFilter($collection->getEntity(), $field, $value)) {
                    Mage::throwException(Mage::helper('customer')->__('Filtering by "%s" is not allowed.', $field));
                }
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        $result = [];
        /** @var Mage_Customer_Model_Customer $customer */
        foreach ($collection as $customer) {
            $data = $customer->toArray();
            $row  = [];
            foreach ($this->_mapAttributes as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = $data[$attributeCode] ?? null;
            }
            foreach ($this->_getReadableAttributeCodes($customer) as $attributeCode) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $this->_prepareAttributeValueForRead($attributeCode, $data[$attributeCode]);
                }
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Return the codes of the attributes that info() and items() can return.
     *
     * The credentials are removed after the optional filter, so a filter that names one cannot add it back. They are
     * not in $_ignoredAttributeCodes because update() also reads that list.
     *
     * @param array<int|string>|null $filter
     * @return string[]
     */
    protected function _getReadableAttributeCodes(Mage_Customer_Model_Customer $customer, ?array $filter = null): array
    {
        return array_values(array_diff(
            array_keys($this->getAllowedAttributes($customer, $filter)),
            Mage::helper('customer')->getApiSecretAttributeCodes(),
        ));
    }

    /**
     * Prepare an attribute value for the API output. For the confirmation key, return only whether the confirmation
     * is pending, as the xsd:boolean "confirmation" element of the SOAP v2 and WS-I customer entity says.
     */
    protected function _prepareAttributeValueForRead(string $attributeCode, mixed $value): mixed
    {
        if ($attributeCode === 'confirmation') {
            return Mage::helper('customer')->prepareApiConfirmationForRead($value);
        }

        return $value;
    }

    /**
     * Prepare a confirmation value that create() or update() writes. A read returns a boolean, so a client that
     * writes back what it read must not save that boolean as the key, and a client can never choose the key.
     */
    protected function _prepareConfirmationForWrite(Mage_Customer_Model_Customer $customer, mixed $value): ?string
    {
        return Mage::helper('customer')->prepareApiConfirmationForWrite($customer, $value);
    }

    /**
     * Check whether a filter can apply to the customer collection.
     *
     * The entity resolves the field as the collection filter does, so an attribute ID, or a code that matches only in
     * the collation of the database, is also refused.
     */
    protected function _isAllowedFilter(Mage_Eav_Model_Entity_Abstract $entity, int|string $field, mixed $condition): bool
    {
        $codes = [strtolower(trim((string) $field))];
        $attribute = $entity->getAttribute($field);
        if ($attribute) {
            $codes[] = strtolower((string) $attribute->getAttributeCode());
        }

        return Mage::helper('customer')->isApiFilterAllowed($codes, $condition);
    }

    /**
     * Update customer data
     *
     * @param int $customerId
     * @param array $customerData
     * @return bool
     */
    public function update($customerId, $customerData)
    {
        $customerData = $this->_prepareData($customerData);

        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }

        foreach (array_keys($this->getAllowedAttributes($customer)) as $attributeCode) {
            if (isset($customerData[$attributeCode])) {
                $value = $customerData[$attributeCode];
                if ($attributeCode === 'confirmation') {
                    $value = $this->_prepareConfirmationForWrite($customer, $value);
                }
                $customer->setData($attributeCode, $value);
            }
        }

        $customer->save();
        return true;
    }

    /**
     * Delete customer
     *
     * @param int $customerId
     * @return bool
     */
    public function delete($customerId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }

        try {
            $customer->delete();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('not_deleted', $e->getMessage());
        }

        return true;
    }
}
