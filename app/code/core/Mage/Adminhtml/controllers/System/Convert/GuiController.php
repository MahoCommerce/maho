<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_System_Convert_GuiController extends Mage_Adminhtml_System_Convert_ProfileController
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'admin/system/convert/gui';

    /**
     * Profiles list action
     */
    #[\Override]
    #[Maho\Config\Route('/admin/system_convert_gui/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
             ->_title($this->__('Import and Export'))
             ->_title($this->__('Profiles'));

        if ($this->getRequest()->getQuery('ajax')) {
            $this->_forward('grid');
            return;
        }
        $this->loadLayout();

        /**
         * Set active menu item
         */
        $this->_setActiveMenu('system/convert/gui');

        /**
         * Append profiles block to content
         */
        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/system_convert_gui', 'convert_profile'),
        );

        /**
         * Add breadcrumb item
         */
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Import/Export'), Mage::helper('adminhtml')->__('Import/Export'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Profiles'), Mage::helper('adminhtml')->__('Profiles'));

        $this->renderLayout();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/system_convert_gui/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/system_convert_gui_grid')->toHtml());
    }

    /**
     * Profile edit action
     */
    #[\Override]
    #[Maho\Config\Route('/admin/system_convert_gui/edit')]
    public function editAction(): void
    {
        $this->_initProfile();
        $this->loadLayout();

        $profile = Mage::registry('current_convert_profile');

        // set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getConvertProfileData(true);

        if (!empty($data)) {
            $profile->addData($data);
        }

        $this->_title($profile->getId() ? $profile->getName() : $this->__('New Profile'));

        $this->_setActiveMenu('system/convert/gui');

        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/system_convert_gui_edit'),
        );

        /**
         * Append edit tabs to left block
         */
        $this->_addLeft($this->getLayout()->createBlock('adminhtml/system_convert_gui_edit_tabs'));

        $this->renderLayout();
    }

    /**
     * Run a profile of the profile wizard.
     *
     * Without the Advanced Profiles permission, the profile runs the actions XML that the wizard
     * makes from its fields, not the stored XML. A profile that was saved without the wizard
     * fields, or before the wizard escaped them, can store XML that the wizard does not make.
     */
    #[\Override]
    #[Maho\Config\Route('/admin/system_convert_gui/run')]
    public function runAction(): void
    {
        if (!$this->_initProfile()) {
            return;
        }

        /** @var Mage_Dataflow_Model_Profile $profile */
        $profile = Mage::registry('current_convert_profile');
        if ((int) $profile->getId() !== 0
            && !Mage::getSingleton('admin/session')->isAllowed(Mage_Adminhtml_System_Convert_ProfileController::ADMIN_RESOURCE)
        ) {
            try {
                if (!is_array($profile->getGuiData()) || !$profile->getGuiData()) {
                    Mage::throwException($this->__('Invalid profile data.'));
                }

                $profile->_parseGuiData();
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', ['id' => $profile->getId()]);
                return;
            }
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Save a profile of the profile wizard.
     *
     * The wizard makes the actions XML from its fields, so the wizard fields are required and a
     * posted actions_xml is removed. The saved profile is the one that _isAllowed() checked, not
     * a profile_id of the POST body.
     */
    #[\Override]
    #[Maho\Config\Route('/admin/system_convert_gui/save')]
    public function saveAction(): void
    {
        $request = $this->getRequest();
        $post = $request->getPost();
        if (is_array($post) && $post !== []) {
            if (!is_array($post['gui_data'] ?? null)
                || !is_string($post['entity_type'] ?? null)
                || !isset(Mage_Dataflow_Model_Profile::GUI_ADAPTERS[$post['entity_type']])
            ) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('Invalid profile data.'));
                $this->_redirect('*/*');
                return;
            }

            // _initProfile() loads this ID, and addData() copies a posted profile_id onto the profile
            $request->setParam('profile_id', (int) $request->getParam('profile_id'));
            unset($post['actions_xml'], $post['profile_id']);
            $request->setPost($post);
        }

        parent::saveAction();
    }

    /**
     * Allow the Profiles permission only for the profiles of the profile wizard and their batches.
     *
     * An Advanced Profile has no entity type. It and its batches need the Advanced Profiles
     * permission, which their own controller checks.
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        if (!parent::_isAllowed()) {
            return false;
        }

        $request = $this->getRequest();
        $action = strtolower($request->getActionName());
        if ($action === 'batchrun' || $action === 'batchfinish') {
            $batchId = (int) ($action === 'batchrun' ? $request->getPost('batch_id') : $request->getParam('id'));
            return $batchId === 0 || $this->_isGuiBatch($this->_loadBatch($batchId));
        }

        foreach (['id', 'profile_id'] as $param) {
            $profileId = (int) $request->getParam($param);
            if ($profileId !== 0 && $this->_getGuiEntityType($profileId) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the entity type of a profile of the profile wizard, or null for an Advanced Profile or a missing profile.
     */
    protected function _getGuiEntityType(int $profileId): ?string
    {
        $entityType = Mage::getModel('dataflow/profile')->load($profileId)->getEntityType();
        return is_string($entityType) && isset(Mage_Dataflow_Model_Profile::GUI_ADAPTERS[$entityType]) ? $entityType : null;
    }

    /**
     * Return true when a profile of the profile wizard that still exists started the batch, with the adapter of the wizard.
     */
    protected function _isGuiBatch(Mage_Dataflow_Model_Batch $batch): bool
    {
        $entityType = $this->_getGuiEntityType((int) $batch->getProfileId());
        $adapter = $batch->getAdapter();
        return $entityType !== null
            && ($adapter === null || $adapter === Mage_Dataflow_Model_Profile::GUI_ADAPTERS[$entityType]);
    }

    protected function _loadBatch(int $batchId): Mage_Dataflow_Model_Batch
    {
        return Mage::getModel('dataflow/batch')->load($batchId);
    }

    #[Maho\Config\Route('/admin/system_convert_gui/upload')]
    public function uploadAction(): void
    {
        $this->_initProfile();
    }

    #[Maho\Config\Route('/admin/system_convert_gui/uploadPost')]
    public function uploadPostAction(): void
    {
        $this->_initProfile();
    }

    #[Maho\Config\Route('/admin/system_convert_gui/download')]
    public function downloadAction(): void
    {
        $filename = $this->getRequest()->getParam('filename');
        if (!$filename || str_contains($filename, '..') || $filename[0] === '.') {
            return;
        }
        $this->_initProfile();
    }
}
