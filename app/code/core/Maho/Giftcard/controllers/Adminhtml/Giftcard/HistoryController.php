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

use Maho\Config\Route;

class Maho_Giftcard_Adminhtml_Giftcard_HistoryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'sales/giftcard/history';

    /**
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/giftcard/history')
            ->_addBreadcrumb(
                'Sales',
                'Sales',
            )
            ->_addBreadcrumb(
                'Gift Card History',
                'Gift Card History',
            );

        return $this;
    }

    #[Route('/admin/giftcard_history/index')]

    public function indexAction(): void
    {
        $this->_initAction();
        $this->_title(Mage::helper('giftcard')->__('Gift Card History'));
        $this->renderLayout();
    }

    #[Route('/admin/giftcard_history/grid')]

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/giftcard/history');
    }
}
