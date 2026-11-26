<?php

/**
 * The orders of the customer, and the guest orders with the email of the subject.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Orders extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Orders');
    }

    /** The fields of an order that tell the customer what the store keeps. */
    private const array ORDER_FIELDS = [
        'increment_id', 'created_at', 'status', 'state', 'order_currency_code', 'grand_total', 'subtotal',
        'shipping_amount', 'tax_amount', 'discount_amount', 'customer_email', 'customer_prefix', 'customer_firstname',
        'customer_middlename', 'customer_lastname', 'customer_suffix', 'customer_dob', 'customer_taxvat',
        'customer_gender', 'customer_note', 'remote_ip', 'x_forwarded_for', 'shipping_description',
    ];

    /** The fields of an order address. */
    private const array ADDRESS_FIELDS = [
        'prefix', 'firstname', 'middlename', 'lastname', 'suffix', 'company', 'street', 'city', 'region',
        'postcode', 'country_id', 'telephone', 'fax', 'email', 'vat_id',
    ];

    #[\Override]
    public function export(Subject $subject): array
    {
        $orders = Mage::getResourceModel('sales/order_collection');
        if ($subject->isGuest()) {
            $orders->addFieldToFilter('customer_id', ['null' => true])
                ->addFieldToFilter('customer_email', $subject->email);
        } else {
            $orders->addFieldToFilter(
                ['customer_id', 'customer_email'],
                [$subject->customerId, $subject->email],
            );
        }
        $orders->setOrder('created_at', \Maho\Data\Collection::SORT_ORDER_ASC);

        $data = [];
        foreach ($orders as $order) {
            /** @var Mage_Sales_Model_Order $order */
            // An order of another customer with the same email is not data of the subject
            if (!$subject->isGuest() && $order->getCustomerId() && $order->getCustomerId() !== $subject->customerId) {
                continue;
            }
            $row = array_intersect_key($order->getData(), array_flip(self::ORDER_FIELDS));
            $row['payment_method'] = $order->getPayment()?->getMethod();
            foreach (['billing_address' => $order->getBillingAddress(), 'shipping_address' => $order->getShippingAddress()] as $key => $address) {
                $row[$key] = $address ? array_intersect_key($address->getData(), array_flip(self::ADDRESS_FIELDS)) : null;
            }
            $row['items'] = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $row['items'][] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty_ordered' => (float) $item->getQtyOrdered(),
                    'price' => (float) $item->getPrice(),
                    'row_total' => (float) $item->getRowTotal(),
                ];
            }
            $data[] = $this->clean($row);
        }
        return $data;
    }
}
