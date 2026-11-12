<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Helper_Guest extends Mage_Core_Helper_Data
{
    #[\Override]
    protected $_moduleName = 'Mage_Sales';

    /**
     * Cookie params
     */
    protected $_cookieName  = 'guest-view';
    protected $_lifeTime    = 600;

    /**
     * Try to load valid order by $_POST or $_COOKIE
     *
     * @return bool|null
     */
    public function loadValidOrder()
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('sales/order/history'));
            return false;
        }

        $post = Mage::app()->getRequest()->getPost();
        /** @var Mage_Core_Model_Cookie $cookieModel */
        $cookieModel = Mage::getSingleton('core/cookie');
        $cookie = (string) $cookieModel->get($this->_cookieName);
        if (empty($post) && $cookie === '') {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('sales/guest/form'));
            return false;
        }

        $service = Mage::getService('sales/order');
        try {
            if (isset($post['oar_order_id'], $post['oar_type'])) {
                $type = (string) $post['oar_type'];
                $order = $service->findForGuest(
                    (string) $post['oar_order_id'],
                    (string) ($post['oar_billing_lastname'] ?? ''),
                    $type === 'email' ? (string) ($post['oar_email'] ?? '') : '',
                    $type === 'zip' ? (string) ($post['oar_zip'] ?? '') : '',
                );
                $cookieModel->set($this->_cookieName, base64_encode($order->getProtectCode() . ':' . $order->getIncrementId()), $this->_lifeTime, '/');
            } else {
                [$protectCode, $incrementId] = array_pad(explode(':', (string) base64_decode($cookie)), 2, '');
                $order = $service->findForGuestByProtectCode($incrementId, $protectCode);
                $cookieModel->renew($this->_cookieName, $this->_lifeTime, '/');
            }
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('sales/guest/form'));
            return false;
        }

        Mage::register('current_order', $order);
        return true;
    }

    /**
     * Get Breadcrumbs for current controller action
     *
     * @param  Mage_Core_Controller_Front_Action $controller
     */
    public function getBreadcrumbs($controller)
    {
        /** @var Mage_Page_Block_Html_Breadcrumbs $breadcrumbs */
        $breadcrumbs = $controller->getLayout()->getBlock('breadcrumbs');
        $breadcrumbs->addCrumb(
            'home',
            [
                'label' => $this->__('Home'),
                'title' => $this->__('Go to Home Page'),
                'link'  => Mage::getBaseUrl(),
            ],
        );
        $breadcrumbs->addCrumb(
            'cms_page',
            [
                'label' => $this->__('Order Information'),
                'title' => $this->__('Order Information'),
            ],
        );
    }

    /**
     * Load the order that the cookie $cookie of the guest order form names.
     *
     * @param string|null $cookie
     * @return null|Mage_Sales_Model_Order
     */
    #[\Deprecated('No core code calls it. Use Mage_Sales_Service_Order::findForGuestByProtectCode().', since: '27.1')]
    protected function _loadOrderByCookie($cookie = null)
    {
        [$protectCode, $incrementId] = array_pad(explode(':', (string) base64_decode((string) $cookie)), 2, '');
        $order = $protectCode !== '' && $incrementId !== ''
            ? Mage::getService('sales/order')->findByIncrementId($incrementId)
            : null;

        return $order && hash_equals((string) $order->getProtectCode(), $protectCode) ? $order : null;
    }

    /**
     * Getter for $this->_cookieName
     *
     * @return string
     */
    public function getCookieName()
    {
        return $this->_cookieName;
    }
}
