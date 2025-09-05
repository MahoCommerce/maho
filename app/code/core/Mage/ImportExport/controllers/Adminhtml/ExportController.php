<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Adminhtml_ExportController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/convert/export';

    /**
     * Custom constructor.
     */
    #[\Override]
    protected function _construct()
    {
        // Define module dependent translate
        $this->setUsedModuleName('Mage_ImportExport');
    }

    /**
     * Initialize layout.
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this
            ->_title($this->__('Import/Export'))
            ->loadLayout()
            ->_setActiveMenu('system/convert/export');

        return $this;
    }

    /**
     * Load data with filter applying and create file for download.
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    public function exportAction()
    {
        // DEBUG: Log all request parameters
        Mage::log('=== EXPORT ACTION DEBUG ===', Zend_Log::DEBUG, 'export_debug.log');
        Mage::log('POST params: ' . print_r($this->getRequest()->getPost(), true), Zend_Log::DEBUG, 'export_debug.log');
        Mage::log('GET params: ' . print_r($this->getRequest()->getQuery(), true), Zend_Log::DEBUG, 'export_debug.log');
        Mage::log('All params: ' . print_r($this->getRequest()->getParams(), true), Zend_Log::DEBUG, 'export_debug.log');
        Mage::log('FILTER_ELEMENT_GROUP constant: ' . Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP, Zend_Log::DEBUG, 'export_debug.log');
        Mage::log('Has FILTER_ELEMENT_GROUP in POST: ' . ($this->getRequest()->getPost(Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP) ? 'YES' : 'NO'), Zend_Log::DEBUG, 'export_debug.log');

        if ($this->getRequest()->getPost(Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP)) {
            Mage::log('✓ FILTER_ELEMENT_GROUP found, proceeding with export', Zend_Log::DEBUG, 'export_debug.log');
            try {
                /** @var Mage_ImportExport_Model_Export $model */
                $model = Mage::getModel('importexport/export');
                $model->setData($this->getRequest()->getParams());

                Mage::log('About to call exportFile()', Zend_Log::DEBUG, 'export_debug.log');
                $result         = $model->exportFile();
                Mage::log('exportFile() completed successfully', Zend_Log::DEBUG, 'export_debug.log');
                Mage::log('Result: ' . print_r($result, true), Zend_Log::DEBUG, 'export_debug.log');

                // Handle different result types correctly
                if (isset($result['type']) && $result['type'] === 'string') {
                    // For string content, pass the content directly
                    $content = $result['value'];
                } else {
                    // For file-based exports, use the full result array
                    $result['type'] = 'filename';
                    $content = $result;
                }

                Mage::log('About to prepare download response with content type: ' . (is_array($content) ? 'array' : 'string'), Zend_Log::DEBUG, 'export_debug.log');
                return $this->_prepareDownloadResponse(
                    $model->getFileName(),
                    $content,
                    $model->getContentType(),
                );
            } catch (Mage_Core_Exception $e) {
                Mage::log('Mage_Core_Exception caught: ' . $e->getMessage(), Zend_Log::ERR, 'export_debug.log');
                Mage::log('Exception trace: ' . $e->getTraceAsString(), Zend_Log::ERR, 'export_debug.log');
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::log('General Exception caught: ' . $e->getMessage(), Zend_Log::ERR, 'export_debug.log');
                Mage::log('Exception trace: ' . $e->getTraceAsString(), Zend_Log::ERR, 'export_debug.log');
                Mage::logException($e);
                $this->_getSession()->addError($this->__('No valid data sent'));
            }
        } else {
            Mage::log('✗ FILTER_ELEMENT_GROUP NOT found, showing error', Zend_Log::DEBUG, 'export_debug.log');
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        return $this->_redirect('*/*/index');
    }

    /**
     * Index action.
     */
    public function indexAction(): void
    {
        $this->_initAction()
            ->_title($this->__('Export'))
            ->_addBreadcrumb($this->__('Export'), $this->__('Export'));

        $this->renderLayout();
    }

    /**
     * Get grid-filter of entity attributes action.
     */
    public function getFilterAction()
    {
        $data = $this->getRequest()->getParams();
        if ($this->getRequest()->isXmlHttpRequest() && $data) {
            try {
                $this->loadLayout();

                /** @var Mage_ImportExport_Block_Adminhtml_Export_Filter $attrFilterBlock */
                $attrFilterBlock = $this->getLayout()->getBlock('export.filter');
                /** @var Mage_ImportExport_Model_Export $export */
                $export = Mage::getModel('importexport/export');

                $export->filterAttributeCollection(
                    $attrFilterBlock->prepareCollection(
                        $export->setData($data)->getEntityAttributeCollection(),
                    ),
                );
                return $this->renderLayout();
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        } else {
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        $this->_redirect('*/*/index');
    }
}
