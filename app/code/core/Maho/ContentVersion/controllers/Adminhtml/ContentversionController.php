<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Adminhtml_ContentversionController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/contentversion';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['restore']);
        return parent::preDispatch();
    }

    public function restoreAction(): void
    {
        $versionId = (int) $this->getRequest()->getParam('version_id');
        if (!$versionId) {
            $this->_getSession()->addError($this->__('No version specified.'));
            $this->_redirectReferer();
            return;
        }

        try {
            $result = Mage::helper('contentversion')->restoreVersion($versionId);

            $this->_getSession()->addSuccess(
                $this->__('Content has been restored from version. The previous state was saved as a new version.'),
            );

            $this->_redirectToEntity($result['entity_type'], $result['entity_id']);
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectReferer();
        }
    }

    public function previewAction(): void
    {
        $versionId = (int) $this->getRequest()->getParam('version_id');
        if (!$versionId) {
            $this->_getSession()->addError($this->__('No version specified.'));
            $this->_redirectReferer();
            return;
        }

        /** @var Maho_ContentVersion_Model_Version $version */
        $version = Mage::getModel('contentversion/version')->load($versionId);
        if (!$version->getId()) {
            $this->_getSession()->addError($this->__('Version not found.'));
            $this->_redirectReferer();
            return;
        }

        Mage::register('contentversion_preview', $version);

        $this->loadLayout();
        $this->_setActiveMenu('cms');

        /** @var Mage_Adminhtml_Block_Template $block */
        $block = $this->getLayout()->createBlock('adminhtml/template')
            ->setTemplate('contentversion/preview.phtml');
        $this->_addContent($block);

        $this->renderLayout();
    }

    private function _redirectToEntity(string $entityType, int $entityId): void
    {
        match ($entityType) {
            'cms_page' => $this->_redirect('*/cms_page/edit', ['page_id' => $entityId]),
            'cms_block' => $this->_redirect('*/cms_block/edit', ['block_id' => $entityId]),
            'blog_post' => $this->_redirect('*/blog_post/edit', ['id' => $entityId]),
            default => $this->_redirectReferer(),
        };
    }
}
