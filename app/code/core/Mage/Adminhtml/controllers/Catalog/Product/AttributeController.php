<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product attribute controller
 */
class Mage_Adminhtml_Catalog_Product_AttributeController extends Mage_Eav_Controller_Adminhtml_Attribute_Abstract
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/attributes';

    /**
     * List of tags from setting
     */
    public const XML_PATH_ALLOWED_TAGS = 'system/catalog/frontend/allowed_html_tags_list';

    #[\Override]
    protected function _construct()
    {
        $this->entityTypeCode = Mage_Catalog_Model_Product::ENTITY;
    }

    #[\Override]
    protected function _initAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Attributes'))
             ->_title($this->__('Manage Attributes'));

        if ($this->getRequest()->getParam('popup')) {
            $this->loadLayout('popup');
        } else {
            $this->loadLayout()
                 ->_setActiveMenu('catalog/attributes/attributes')
                 ->_addBreadcrumb(Mage::helper('catalog')->__('Catalog'), Mage::helper('catalog')->__('Catalog'))
                 ->_addBreadcrumb(
                     Mage::helper('catalog')->__('Manage Product Attributes'),
                     Mage::helper('catalog')->__('Manage Product Attributes')
                 );
        }
        return $this;
    }

    /**
     * Get list of allowed text formatted as array
     *
     * @return array
     */
    protected function _getAllowedTags()
    {
        return explode(',', Mage::getStoreConfig(self::XML_PATH_ALLOWED_TAGS));
    }

    #[\Override]
    protected function _filterPostData($data)
    {
        if ($data) {
            if (!isset($data['is_configurable'])) {
                $data['is_configurable'] = 0;
            }
            if (!isset($data['is_filterable'])) {
                $data['is_filterable'] = 0;
            }
            if (!isset($data['is_filterable_in_search'])) {
                $data['is_filterable_in_search'] = 0;
            }
            if (!isset($data['apply_to'])) {
                $data['apply_to'] = [];
            }
            $data['frontend_label'] = (array) $data['frontend_label'];
            foreach ($data['frontend_label'] as & $value) {
                if ($value) {
                    $value = Mage::helper('catalog')->stripTags($value);
                }
            }
            if (!empty($data['option']) && !empty($data['option']['value']) && is_array($data['option']['value'])) {
                $allowedTags = !empty($data['is_html_allowed_on_front']) ? $this->_getAllowedTags() : [];
                foreach ($data['option']['value'] as $key => $values) {
                    foreach ($values as $storeId => $storeLabel) {
                        $data['option']['value'][$key][$storeId] = Mage::helper('catalog')->stripTags($storeLabel, $allowedTags);
                    }
                }
            }
        }
        return $data;
    }

    #[\Override]
    public function saveAction()
    {
        $request = $this->getRequest();

        // For creating product attribute on product page we need specify attribute set and group
        if ($request->getParam('set') && $request->getParam('group')) {
            $request->setPost('attribute_set_id', $request->getParam('set'));
            $request->setPost('attribute_group_id', $request->getParam('group'));
        }

        parent::saveAction();
    }
}
