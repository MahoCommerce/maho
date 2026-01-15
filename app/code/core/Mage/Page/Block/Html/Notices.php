<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Page_Block_Html_Notices extends Mage_Core_Block_Template
{
    /**
     * Check if demo store notice should be displayed
     */
    public function displayDemoNotice(): bool
    {
        return (bool) Mage::getStoreConfig('design/head/demonotice');
    }

    /**
     * Get demo store notice content based on configuration mode
     */
    public function getDemoNoticeContent(): string
    {
        $mode = Mage::getStoreConfig('design/head/demonotice');

        if ($mode === Mage_Page_Model_Source_Demonotice::MODE_CMS_BLOCK) {
            $blockId = Mage::getStoreConfig('design/head/demonotice_cms_block');
            if ($blockId) {
                return $this->getLayout()
                    ->createBlock('cms/block')
                    ->setBlockId($blockId)
                    ->toHtml();
            }
        }

        $customText = Mage::getStoreConfig('design/head/demonotice_text');
        if ($customText) {
            return $this->escapeHtml($customText);
        }

        return $this->__('This is a demo store. Any orders placed through this store will not be honored or fulfilled.');
    }

    /**
     * Get Link to cookie restriction privacy policy page
     */
    public function getPrivacyPolicyLink(): string
    {
        return Mage::getUrl('privacy-policy-cookie-restriction-mode');
    }
}
