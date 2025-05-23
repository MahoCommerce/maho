<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cms_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_NODE_PAGE_TEMPLATE_FILTER     = 'global/cms/page/tempate_filter';
    public const XML_NODE_BLOCK_TEMPLATE_FILTER    = 'global/cms/block/tempate_filter';
    public const XML_NODE_ALLOWED_STREAM_WRAPPERS  = 'global/cms/allowed_stream_wrappers';
    public const XML_NODE_ALLOWED_MEDIA_EXT_SWF    = 'adminhtml/cms/browser/extensions/media_allowed/swf';

    protected $_moduleName = 'Mage_Cms';

    /**
     * Retrieve Template processor for Page Content
     *
     * @return Mage_Core_Model_Abstract|Varien_Filter_Template
     */
    public function getPageTemplateProcessor()
    {
        $model = (string) Mage::getConfig()->getNode(self::XML_NODE_PAGE_TEMPLATE_FILTER);
        return Mage::getModel($model);
    }

    /**
     * Retrieve Template processor for Block Content
     *
     * @return Mage_Core_Model_Abstract|Varien_Filter_Template
     */
    public function getBlockTemplateProcessor()
    {
        $model = (string) Mage::getConfig()->getNode(self::XML_NODE_BLOCK_TEMPLATE_FILTER);
        return Mage::getModel($model);
    }

    /**
     * Return list with allowed stream wrappers
     *
     * @return array
     */
    public function getAllowedStreamWrappers()
    {
        $allowedStreamWrappers = Mage::getConfig()->getNode(self::XML_NODE_ALLOWED_STREAM_WRAPPERS);
        if ($allowedStreamWrappers instanceof Mage_Core_Model_Config_Element) {
            $allowedStreamWrappers = $allowedStreamWrappers->asArray();
        }

        return is_array($allowedStreamWrappers) ? $allowedStreamWrappers : [];
    }
}
