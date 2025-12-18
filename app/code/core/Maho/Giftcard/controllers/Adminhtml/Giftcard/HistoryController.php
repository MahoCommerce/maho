<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Adminhtml_Giftcard_HistoryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'customer/giftcard/history';

    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('customer/giftcard/history')
            ->_addBreadcrumb(
                'Customers',
                'Customers',
            )
            ->_addBreadcrumb(
                'Gift Card History',
                'Gift Card History',
            );

        return $this;
    }

    /**
     * Index action - history grid
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->_title(Mage::helper('giftcard')->__('Gift Card History'));
        $this->renderLayout();
    }

    /**
     * Grid action for AJAX
     */
    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/giftcard/history');
    }
}
