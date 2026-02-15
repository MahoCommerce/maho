<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Observer
{
    /**
     * Set forced canCreditmemo flag
     *
     * @param \Maho\Event\Observer $observer
     * @return $this
     */
    public function salesOrderBeforeSave($observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        if ($order->getPayment() && $order->getPayment()->getMethodInstance()->getCode() != 'free') {
            return $this;
        }

        if ($order->canUnhold()) {
            return $this;
        }

        if ($order->isCanceled() || $order->getState() === Mage_Sales_Model_Order::STATE_CLOSED) {
            return $this;
        }
        /**
         * Allow forced creditmemo just in case if it wasn't defined before
         */
        if (!$order->hasForcedCanCreditmemo()) {
            $order->setForcedCanCreditmemo(true);
        }
        return $this;
    }

    /**
     * Collect buy request and set it as custom option
     *
     * Also sets the collected information and schedule as informational static options
     *
     * @param \Maho\Event\Observer $observer
     */
    public function prepareProductRecurringProfileOptions($observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getEvent()->getProduct();
        $buyRequest = $observer->getEvent()->getBuyRequest();

        if (!$product->isRecurring()) {
            return;
        }

        $profile = Mage::getModel('payment/recurring_profile')
            ->setLocale(Mage::app()->getLocale())
            ->setStore(Mage::app()->getStore())
            ->importBuyRequest($buyRequest)
            ->importProduct($product);
        if (!$profile) {
            return;
        }

        // add the start datetime as product custom option
        $product->addCustomOption(
            Mage_Payment_Model_Recurring_Profile::PRODUCT_OPTIONS_KEY,
            Mage::helper('core')->jsonEncode(['start_datetime' => $profile->getStartDatetime()]),
        );

        // duplicate as 'additional_options' to render with the product statically
        $infoOptions = [[
            'label' => $profile->getFieldLabel('start_datetime'),
            'value' => $profile->exportStartDatetime(true),
        ]];

        foreach ($profile->exportScheduleInfo() as $info) {
            $infoOptions[] = [
                'label' => $info->getTitle(),
                'value' => $info->getSchedule(),
            ];
        }
        $product->addCustomOption('additional_options', Mage::helper('core')->jsonEncode($infoOptions));
    }

    /**
     * Sets current instructions for bank transfer account
     */
    public function beforeOrderPaymentSave(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        if ($payment->getMethod() === Mage_Payment_Model_Method_Banktransfer::PAYMENT_METHOD_BANKTRANSFER_CODE) {
            $payment->setAdditionalInformation(
                'instructions',
                $payment->getMethodInstance()->setStore($payment->getOrder()->getStoreId())->getInstructions(),
            );
        }
    }

    /**
     * Will veto the unassignment of the order status if it is currently configured in any of the payment method
     * configurations.
     *
     * @param \Maho\Event\Observer $observer
     * @throws Mage_Core_Exception
     */
    public function beforeSalesOrderStatusUnassign($observer)
    {
        $state = $observer->getEvent()->getState();
        if ($state == Mage_Sales_Model_Order::STATE_NEW) {
            /** @var Mage_Sales_Model_Order_Status|false $statusModel */
            $statusModel = $observer->getEvent()->getStatus();
            $status      = $statusModel->getStatus();
            $used        = 0;
            $titles      = [];
            foreach (Mage::app()->getWebsites(true) as $website) {
                $store = current($website->getStores()); // just need one store from each website
                if (!$store) {
                    continue; // no store is associated with the website
                }
                foreach (Mage::helper('payment')->getPaymentMethods($store) as $value) {
                    if (isset($value['order_status']) && $value['order_status'] == $status && $value['active']) {
                        ++$used;

                        // Remember the payment's information
                        $title       = $value['title'];
                        $websiteName = $website->getName();
                        if (array_key_exists($title, $titles)) {
                            $titles[$title][] = $websiteName;
                        } else {
                            $titles[$title]   = [$websiteName];
                        }
                    }
                }
            }
            if ($used > 0) {
                // build the error message, and throw it
                $methods = '';
                $spacer  = '';
                foreach ($titles as $key => $values) {
                    $methods = $methods . $spacer . $key . ' [' . implode(', ', $values) . ']';
                    $spacer = ', ';
                }
                throw new Mage_Core_Exception(Mage::helper('sales')->__(
                    'Status "%s" cannot be unassigned. It is in used in %d payment method configuration(s): %s',
                    $statusModel->getLabel(),
                    $used,
                    $methods,
                ));
            }
        }
    }

    public function encryptionKeyRegenerated(\Maho\Event\Observer $observer): void
    {
        /** @var \Symfony\Component\Console\Output\OutputInterface $output */
        $output = $observer->getEvent()->getOutput();
        $encryptCallback = $observer->getEvent()->getEncryptCallback();
        $decryptCallback = $observer->getEvent()->getDecryptCallback();
        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $output->write('Re-encrypting data on sales_flat_quote_payment table... ');
        $table = Mage::getSingleton('core/resource')->getTableName('sales_flat_quote_payment');

        $select = $readConnection->select()
            ->from($table)
            ->where('cc_number_enc IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['cc_number_enc' => $encryptCallback($decryptCallback($encryptedDataRow['cc_number_enc']))],
                ['payment_id = ?' => $encryptedDataRow['payment_id']],
            );
        }

        $select = $readConnection->select()
            ->from($table)
            ->where('cc_cid_enc IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['cc_cid_enc' => $encryptCallback($decryptCallback($encryptedDataRow['cc_cid_enc']))],
                ['payment_id = ?' => $encryptedDataRow['payment_id']],
            );
        }

        $output->writeln('OK');

        $output->write('Re-encrypting data on sales_flat_order_payment table... ');
        $table = Mage::getSingleton('core/resource')->getTableName('sales_flat_order_payment');

        $select = $readConnection->select()
            ->from($table)
            ->where('cc_number_enc IS NOT NULL');
        $encryptedData = $readConnection->fetchAll($select);
        foreach ($encryptedData as $encryptedDataRow) {
            $writeConnection->update(
                $table,
                ['cc_number_enc' => $encryptCallback($decryptCallback($encryptedDataRow['cc_number_enc']))],
                ['entity_id = ?' => $encryptedDataRow['entity_id']],
            );
        }

        $output->writeln('OK');
    }
}
