<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Observer
{
    public function saveOrderAfterSubmit(\Maho\DataObject $observer): void
    {
        $order = $observer->getData('order');
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }

        $method = $payment->getMethod();
        $mahoPpMethods = [
            Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT,
            Maho_Paypal_Model_Config::METHOD_VAULT,
        ];

        if (!in_array($method, $mahoPpMethods)) {
            return;
        }

        $paypalOrderId = $payment->getAdditionalInformation('paypal_order_id');
        if ($paypalOrderId) {
            $payment->setData('paypal_order_id', $paypalOrderId);
            $payment->save();
        }
    }

    public function addDeprecationNotice(\Maho\DataObject $observer): void
    {
        /** @var Maho_Paypal_Model_Config $config */
        $config = Mage::getModel('paypal/config');
        $deprecated = $config->getActiveDeprecatedMethods();

        if ($deprecated === []) {
            return;
        }

        $methods = implode(', ', $deprecated);
        Mage::getSingleton('adminhtml/session')->addNotice(
            Mage::helper('maho_paypal')->__(
                'Legacy PayPal payment methods are active (%s). Consider migrating to the new PayPal integration under System > Configuration > Payment Methods.',
                $methods,
            ),
        );
    }
}
