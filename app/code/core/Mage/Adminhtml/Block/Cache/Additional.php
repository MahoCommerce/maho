<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Cache_Additional extends Mage_Adminhtml_Block_Template
{
    public function getCleanImagesUrl()
    {
        return $this->getUrl('*/*/cleanImages');
    }

    public function getCleanSwatchesUrl()
    {
        return $this->getUrl('*/*/cleanSwatches');
    }

    public function getCleanMediaUrl()
    {
        return $this->getUrl('*/*/cleanMedia');
    }
}
