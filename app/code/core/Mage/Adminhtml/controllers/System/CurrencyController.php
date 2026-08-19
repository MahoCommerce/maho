<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_System_CurrencyController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/currency/rates';

    /**
     * Init currency by currency code from request
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _initCurrency()
    {
        $code = $this->getRequest()->getParam('currency');
        $currency = Mage::getModel('directory/currency')
            ->load($code);

        Mage::register('currency', $currency);
        return $this;
    }

    /**
     * Currency management main page
     */
    #[Maho\Config\Route('/admin/system_currency/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Manage Currency Rates'));

        $this->loadLayout();
        $this->_setActiveMenu('system/currency/rates');
        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_currency'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_currency/fetchRates')]
    public function fetchRatesAction(): void
    {
        try {
            $service = $this->getRequest()->getParam('rate_services');
            $this->_getSession()->setCurrencyRateService($service);
            if (!$service) {
                throw new Exception(Mage::helper('adminhtml')->__('Invalid Import Service Specified'));
            }
            $serviceModel = Mage::getConfig()->getNode('global/currency/import/services/' . $service . '/model');
            if (!$serviceModel) {
                Mage::throwException(Mage::helper('adminhtml')->__('Invalid Import Service Specified'));
            }
            $importModel = Mage::getModel((string) $serviceModel);
            if (!$importModel instanceof Mage_Directory_Model_Currency_Import_Abstract) {
                Mage::throwException(Mage::helper('adminhtml')->__('Unable to initialize import model'));
            }
            $rates = $importModel->fetchRates();
            $errors = $importModel->getMessages();
            if (count($errors)) {
                foreach ($errors as $error) {
                    Mage::getSingleton('adminhtml/session')->addWarning($error);
                }
                Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('adminhtml')->__('All possible rates were fetched, please click on "Save" to apply'));
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('All rates were fetched, please click on "Save" to apply'));
            }

            Mage::getSingleton('adminhtml/session')->setRates($rates);
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/system_currency/saveRates')]
    public function saveRatesAction(): void
    {
        $data = $this->getRequest()->getParam('rate');
        if (is_array($data)) {
            try {
                foreach ($data as $currencyCode => $rate) {
                    foreach ($rate as $currencyTo => $value) {
                        // Tested before getNumber() flattens an empty cell and a typo to the
                        // same zero; the comma swap lets a locale-spelled "0,00" read as blank
                        $blank = Mage_Directory_Model_Resource_Currency::isBlankRate(
                            str_replace(',', '.', (string) $value),
                        );

                        $value = abs((float) Mage::app()->getLocale()->getNumber($value));
                        $data[$currencyCode][$currencyTo] = $value;
                        if (!$blank && !Mage_Directory_Model_Resource_Currency::isStorableRate($value)) {
                            Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('adminhtml')->__('Invalid input data for %s => %s rate', $currencyCode, $currencyTo));
                        }
                    }
                }

                Mage::getModel('directory/currency')->saveRates($data);
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('All valid rates have been saved.'));
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/');
    }
}
