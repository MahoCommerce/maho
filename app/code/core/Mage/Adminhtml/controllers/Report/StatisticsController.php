<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Report_StatisticsController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'report/statistics';

    /**
     * Admin session model
     *
     * @var null|Mage_Admin_Model_Session
     */
    protected $_adminSession = null;

    #[Maho\Config\Route('/admin/report_statistics/_init')]
    public function _initAction()
    {
        $act = $this->getRequest()->getActionName();
        if (!$act) {
            $act = 'default';
        }

        $this->loadLayout()
            ->_addBreadcrumb(Mage::helper('reports')->__('Reports'), Mage::helper('reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('reports')->__('Statistics'), Mage::helper('reports')->__('Statistics'));
        return $this;
    }

    #[Maho\Config\Route('/admin/report_statistics/_initReport')]
    public function _initReportAction($blocks)
    {
        if (!is_array($blocks)) {
            $blocks = [$blocks];
        }

        $requestData = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('filter', ''));
        $requestData = $this->_filterDates($requestData, ['from', 'to']);
        $requestData['store_ids'] = $this->getRequest()->getParam('store_ids');
        $params = new \Maho\DataObject();

        foreach ($requestData as $key => $value) {
            if (!empty($value)) {
                $params->setData($key, $value);
            }
        }

        foreach ($blocks as $block) {
            if ($block) {
                $block->setPeriodType($params->getData('period_type'));
                $block->setFilterData($params);
            }
        }

        return $this;
    }

    /**
     * Retrieve the report codes specified in the request
     *
     * @return list<string>
     */
    protected function _getReportCodes(): array
    {
        $codes = $this->getRequest()->getParam('code');
        if (!$codes) {
            throw new Exception(Mage::helper('adminhtml')->__('No report code specified.'));
        }

        if (!is_array($codes) && !str_contains($codes, ',')) {
            $codes = [$codes];
        } elseif (!is_array($codes)) {
            $codes = explode(',', $codes);
        }

        return array_values($codes);
    }

    /**
     * Refresh statistics for last 25 hours
     *
     * @return $this
     */
    #[Maho\Config\Route('/admin/report_statistics/refreshRecent')]
    public function refreshRecentAction()
    {
        try {
            Mage::getModel('reports/statistics')->refreshRecent($this->_getReportCodes());
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Recent statistics have been updated.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Unable to refresh recent statistics.'));
            Mage::logException($e);
        }

        if ($this->_getSession()->isFirstPageAfterLogin()) {
            $this->_redirect('*/*');
        } else {
            $this->_redirectReferer('*/*');
        }
        return $this;
    }

    /**
     * Refresh statistics for all period
     *
     * @return $this
     */
    #[Maho\Config\Route('/admin/report_statistics/refreshLifetime')]
    public function refreshLifetimeAction()
    {
        try {
            Mage::getModel('reports/statistics')->refreshLifetime($this->_getReportCodes());
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Lifetime statistics have been updated.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Unable to refresh lifetime statistics.'));
            Mage::logException($e);
        }

        if ($this->_getSession()->isFirstPageAfterLogin()) {
            $this->_redirect('*/*');
        } else {
            $this->_redirectReferer('*/*');
        }

        return $this;
    }

    #[Maho\Config\Route('/admin/report_statistics/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('Refresh Statistics'));

        $this->_initAction()
            ->_setActiveMenu('report/statistics/refreshstatistics')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Refresh Statistics'), Mage::helper('adminhtml')->__('Refresh Statistics'))
            ->renderLayout();
    }

    /**
     * Retrieve admin session model
     *
     * @return Mage_Admin_Model_Session
     */
    #[\Override]
    protected function _getSession()
    {
        $this->_adminSession ??= Mage::getSingleton('admin/session');
        return $this->_adminSession;
    }
}
