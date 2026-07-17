<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Adminhtml_Downloadable_FileController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/products';

    /**
     * Upload file controller action
     */
    #[Maho\Config\Route('/admin/downloadable_file/upload')]
    public function uploadAction(): void
    {
        $type = $this->getRequest()->getParam('type');
        $tmpPath = match ($type) {
            'samples' => Mage_Downloadable_Model_Sample::getBaseTmpPath(),
            'links' => Mage_Downloadable_Model_Link::getBaseTmpPath(),
            'link_samples' => Mage_Downloadable_Model_Link::getBaseSampleTmpPath(),
            default => '',
        };

        try {
            $uploader = Mage::getModel('core/file_uploader', $type);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $result = $uploader->save($tmpPath);

            $this->getResponse()->setBodyJson($result);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
