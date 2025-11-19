<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
