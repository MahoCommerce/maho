<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Tax_RateController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Show Main Grid
     */
    #[Maho\Config\Route('/admin/tax_rate/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Add $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_add', 'tax_rate_toolbar');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'))
            ->_addContent(
                $block
                    ->assign('createUrl', $this->getUrl('*/tax_rate/add'))
                    ->assign('header', Mage::helper('tax')->__('Manage Tax Rates')),
            )
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rate_grid', 'tax_rate_grid'))
            ->renderLayout();
    }

    /**
     * Show Add Form
     */
    #[Maho\Config\Route('/admin/tax_rate/add')]
    public function addAction(): void
    {
        $rateModel = Mage::getSingleton('tax/calculation_rate')->load(null);

        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $this->_title($this->__('New Rate'));

        $rateModel->setData(Mage::getSingleton('adminhtml/session')->getFormData(true));

        if ($rateModel->getZipIsRange() && !$rateModel->hasTaxPostcode()) {
            $rateModel->setTaxPostcode($rateModel->getZipFrom() . '-' . $rateModel->getZipTo());
        }

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Save $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_save');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'), $this->getUrl('*/tax_rate'))
            ->_addBreadcrumb(Mage::helper('tax')->__('New Tax Rate'), Mage::helper('tax')->__('New Tax Rate'))
            ->_addContent(
                $block
                ->assign('header', Mage::helper('tax')->__('Add New Tax Rate'))
                ->assign('form', $this->getLayout()->createBlock('adminhtml/tax_rate_form')),
            )
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/tax_rate/save')]
    public function saveAction(): void
    {
        $ratePost = $this->getRequest()->getPost();
        if (!$ratePost) {
            $this->getResponse()->setRedirect($this->getUrl('*/tax_rate'));
            return;
        }

        $session = Mage::getSingleton('adminhtml/session');
        try {
            $rateId = (int) $this->getRequest()->getParam('tax_calculation_rate_id');
            $rate = $rateId ? $this->rateService()->getById($rateId) : $this->rateService()->newRate();
            // The model checks the values as the form posts them
            $this->rateService()->save($rate->addData($ratePost));
            $session->addSuccess(Mage::helper('tax')->__('The tax rate has been saved.'));
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('tax')->__('An error occurred while saving this rate.'));
        }

        $session->setFormData($ratePost);
        $this->_redirectReferer();
    }

    /**
     * Show Edit Form
     */
    #[Maho\Config\Route('/admin/tax_rate/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $rateId = (int) $this->getRequest()->getParam('rate');
        $rateModel = Mage::getSingleton('tax/calculation_rate')->load(null);
        $rateModel->setData(Mage::getSingleton('adminhtml/session')->getFormData(true));
        if ($rateModel->getId() != $rateId) {
            $rateModel->load($rateId);
        }

        if (!$rateModel->getId()) {
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        }

        if ($rateModel->getZipIsRange() && !$rateModel->hasTaxPostcode()) {
            $rateModel->setTaxPostcode($rateModel->getZipFrom() . '-' . $rateModel->getZipTo());
        }

        $this->_title(sprintf('%s', $rateModel->getCode()));

        /** @var Mage_Adminhtml_Block_Tax_Rate_Toolbar_Save $block */
        $block = $this->getLayout()->createBlock('adminhtml/tax_rate_toolbar_save');
        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Tax Rates'), Mage::helper('tax')->__('Manage Tax Rates'), $this->getUrl('*/tax_rate'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Edit Tax Rate'), Mage::helper('tax')->__('Edit Tax Rate'))
            ->_addContent(
                $block
                ->assign('header', Mage::helper('tax')->__('Edit Tax Rate'))
                ->assign('form', $this->getLayout()->createBlock('adminhtml/tax_rate_form')),
            )
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/tax_rate/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $this->rateService()->delete($this->rateService()->getById((int) $this->getRequest()->getParam('rate')));
            $session->addSuccess(Mage::helper('tax')->__('The tax rate has been deleted.'));
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('tax')->__('An error occurred while deleting this rate.'));
        }
        $this->_redirectReferer($this->getUrl('*/*/'));
    }

    /**
     * Export rates grid to CSV format
     */
    #[Maho\Config\Route('/admin/tax_rate/exportCsv')]
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/tax_rate_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('rates.csv', -1));
    }

    /**
     * Export rates grid to XML format
     */
    #[Maho\Config\Route('/admin/tax_rate/exportXml')]
    public function exportXmlAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/tax_rate_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('rates.xml', -1));
    }

    /**
     * Initialize action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/tax/rates')
            ->_addBreadcrumb(Mage::helper('tax')->__('Sales'), Mage::helper('tax')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Tax'), Mage::helper('tax')->__('Tax'));
        return $this;
    }

    /**
     * Import and export Page
     */
    #[Maho\Config\Route('/admin/tax_rate/importExport')]
    public function importExportAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Zones and Rates'));

        $this->_title($this->__('Import and Export Tax Rates'));

        $this->loadLayout()
            ->_setActiveMenu('sales/tax/import_export')
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rate_importExport'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/tax_rate/importPost')]
    public function importPostAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $fileName = $_FILES['import_rates_file']['tmp_name'] ?? '';
        if (!$this->getRequest()->isPost() || $fileName === '') {
            $session->addError(Mage::helper('tax')->__('Invalid file upload attempt'));
            $this->_redirect('*/*/importExport');
            return;
        }

        try {
            foreach ($this->rateService()->import(new \Maho\File\Csv()->getData($fileName)) as $error) {
                $session->addError($error);
            }
            $session->addSuccess(Mage::helper('tax')->__('The tax rate has been imported.'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('tax')->__('Invalid file upload attempt'));
        }
        $this->_redirect('*/*/importExport');
    }

    /**
     * export action from import/export tax
     */
    #[Maho\Config\Route('/admin/tax_rate/exportPost')]
    public function exportPostAction(): void
    {
        /** start csv content and set template */
        $headers = new \Maho\DataObject($this->rateService()->getCsvHeaders());
        $template = '"{{code}}","{{country_name}}","{{region_name}}","{{tax_postcode}}","{{rate}}"'
                . ',"{{zip_is_range}}","{{zip_from}}","{{zip_to}}"';
        $content = $headers->toString($template);

        $storeTaxTitleTemplate       = [];
        $taxCalculationRateTitleDict = [];

        foreach (Mage::getModel('core/store')->getCollection()->setLoadDefault(false) as $store) {
            $storeTitle = 'title_' . $store->getId();
            $content   .= ',"' . $store->getCode() . '"';
            $template  .= ',"{{' . $storeTitle . '}}"';
            $storeTaxTitleTemplate[$storeTitle] = null;
        }
        unset($store);

        $content .= "\n";

        foreach (Mage::getModel('tax/calculation_rate_title')->getCollection() as $title) {
            $rateId = $title->getTaxCalculationRateId();

            if (!array_key_exists((string) $rateId, $taxCalculationRateTitleDict)) {
                $taxCalculationRateTitleDict[$rateId] = $storeTaxTitleTemplate;
            }

            $taxCalculationRateTitleDict[$rateId]['title_' . $title->getStoreId()] = $title->getValue();
        }
        unset($title);

        $collection = Mage::getResourceModel('tax/calculation_rate_collection')
            ->joinCountryTable()
            ->joinRegionTable();

        while ($rate = $collection->fetchItem()) {
            if ($rate->getTaxRegionId() == 0) {
                $rate->setRegionName('*');
            }

            if (array_key_exists((string) $rate->getId(), $taxCalculationRateTitleDict)) {
                $rate->addData($taxCalculationRateTitleDict[$rate->getId()]);
            } else {
                $rate->addData($storeTaxTitleTemplate);
            }

            $content .= $rate->toString($template) . "\n";
        }

        $this->_prepareDownloadResponse('tax_rates.csv', $content);
    }

    protected function rateService(): Mage_Tax_Service_Rate
    {
        return Mage::getService('tax/rate');
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'importexport', 'importpost', 'exportpost' => Mage::getSingleton('admin/session')->isAllowed('sales/tax/import_export'),
            default => Mage::getSingleton('admin/session')->isAllowed('sales/tax/rates'),
        };
    }
}
