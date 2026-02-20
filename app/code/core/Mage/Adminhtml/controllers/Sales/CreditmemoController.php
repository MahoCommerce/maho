<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Sales_CreditmemoController extends Mage_Adminhtml_Controller_Sales_Creditmemo
{
    /**
     * Export credit memo grid to CSV format
     */
    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_creditmemo_grid');
        $this->_prepareDownloadResponse(...$grid->getCsvFile('creditmemos.csv', -1));
    }

    /**
     * Export credit memo grid to Excel XML format
     */
    public function exportExcelAction(): void
    {
        $grid = $this->getLayout()->createBlock('adminhtml/sales_creditmemo_grid');
        $this->_prepareDownloadResponse(...$grid->getExcelFile('creditmemos.xml', -1));
    }

    /**
     * Index page
     */
    #[\Override]
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))->_title($this->__('Credit Memos'));

        parent::indexAction();
    }
}
