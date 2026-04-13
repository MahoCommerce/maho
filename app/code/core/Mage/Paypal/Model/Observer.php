<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_Observer
{
    /**
     * Goes to reports.paypal.com and fetches Settlement reports.
     */
    #[Maho\Config\CronJob('paypal_fetch_settlement_reports', configPath: 'crontab/jobs/paypal_fetch_settlement_reports/schedule/cron_expr')]
    public function fetchReports()
    {
        try {
            $reports = Mage::getModel('paypal/report_settlement');
            /** @var Mage_Paypal_Model_Report_Settlement $reports */
            $credentials = $reports->getSftpCredentials(true);
            foreach ($credentials as $config) {
                try {
                    $reports->fetchAndSave($config);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Save order into registry to use it in the overloaded controller.
     *
     * @return $this
     */
    #[Maho\Config\Observer('checkout_submit_all_after', area: 'frontend', id: 'hss_save_order_after_submit')]
    public function saveOrderAfterSubmit(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getData('order');
        Mage::register('hss_order', $order, true);

        return $this;
    }

    /**
     * Set data for response of frontend saveOrder action
     *
     * @return $this
     */
    #[Maho\Config\Observer('controller_action_postdispatch_checkout_onepage_saveorder', area: 'frontend', id: 'hss_save_order_onepage')]
    public function setResponseAfterSaveOrder(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::registry('hss_order');

        if ($order && $order->getId()) {
            $payment = $order->getPayment();
            if ($payment && in_array($payment->getMethod(), Mage::helper('paypal/hss')->getHssMethods())) {
                /** @var Mage_Core_Controller_Varien_Action $controller */
                $controller = $observer->getEvent()->getData('controller_action');
                $result = Mage::helper('core')->jsonDecode(
                    $controller->getResponse()->getBody('default'),
                    true,
                );

                if (empty($result['error'])) {
                    $controller->loadLayout('checkout_onepage_review');
                    $html = $controller->getLayout()->getBlock('paypal.iframe')->toHtml();
                    $result['update_section'] = [
                        'name' => 'paypaliframe',
                        'html' => $html,
                    ];
                    $result['redirect'] = false;
                    $result['success'] = false;
                    $controller->getResponse()->clearHeader('Location');
                    $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                }
            }
        }

        return $this;
    }

    /**
     * Load country dependent PayPal solutions system configuration
     */
    #[Maho\Config\Observer('adminhtml_init_system_config', area: 'admin')]
    public function loadCountryDependentSolutionsConfig(\Maho\Event\Observer $observer)
    {
        $countryCode = Mage::helper('paypal')->getConfigurationCountryCode();
        $paymentGroups   = $observer->getEvent()->getConfig()->getNode('sections/payment/groups');
        $paymentsConfigs = $paymentGroups->xpath('paypal_payments/*/backend_config/' . $countryCode);
        if ($paymentsConfigs) {
            foreach ($paymentsConfigs as $config) {
                $parent = $config->getParent()->getParent();
                $parent->extend($config, true);
            }
        }

        $payments = $paymentGroups->xpath('paypal_payments/*');
        foreach ($payments as $payment) {
            if ((int) $payment->include) {
                $fields = $paymentGroups->xpath((string) $payment->group . '/fields');
                if (isset($fields[0])) {
                    $fields[0]->appendChild($payment, true);
                }
            }
        }
    }

    /**
     * Update transaction with HTML representation of txn_id
     */
    #[Maho\Config\Observer('sales_html_txn_id')]
    public function observeHtmlTransactionId(\Maho\Event\Observer $observer)
    {
        /** @var \Maho\DataObject $transaction */
        $transaction = $observer->getEvent()->getTransaction();
        $transaction->setHtmlTxnId(Mage::helper('paypal')->getHtmlTransactionId(
            $observer->getEvent()->getPayment()->getMethodInstance()->getCode(),
            $transaction->getTxnId(),
        ));
    }
}
