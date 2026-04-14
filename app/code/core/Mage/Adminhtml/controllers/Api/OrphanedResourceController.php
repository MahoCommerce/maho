<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
use Maho\Config\Route;

class Mage_Adminhtml_Api_OrphanedResourceController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/api/orphaned_resources';

    /**
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('system/api/orphaned_resources')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('Web Services'), $this->__('Web Services'))
            ->_addBreadcrumb($this->__('Orphaned Resources'), $this->__('Orphaned API Role Resources'));
        return $this;
    }

    /**
     * Index action
     */
    #[Route('/admin/api_orphanedresource/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Web Services'))
            ->_title($this->__('Orphaned API Role Resources'));

        /** @var Mage_Adminhtml_Block_Api_OrphanedResource $block */
        $block = $this->getLayout()->createBlock('adminhtml/api_orphanedResource');
        $this->_initAction()
            ->_addContent($block)
            ->renderLayout();
    }

    /**
     * Mass delete action
     */
    #[Route('/admin/api_orphanedresource/massDelete')]
    public function massDeleteAction(): void
    {
        $resourceIds = $this->getRequest()->getParam('resource_id');
        try {
            $deletedRows = Mage::getResourceSingleton('api/rules')->deleteOrphanedResources($resourceIds);
            $this->_getSession()->addSuccess($this->__('Total of %d record(s) have been deleted.', $deletedRows));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $error = Mage::getIsDeveloperMode()
                ? $e->getMessage()
                : $this->__('An error occurred while deleting record(s).');
            $this->_getSession()->addError($error);
            Mage::logException($e);
        }

        $this->_redirect('*/*/');
    }

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('massDelete');
        return parent::preDispatch();
    }
}
