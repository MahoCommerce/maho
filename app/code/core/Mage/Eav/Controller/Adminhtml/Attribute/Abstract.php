<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract attribute controller
 */
abstract class Mage_Eav_Controller_Adminhtml_Attribute_Abstract extends Mage_Adminhtml_Controller_Action
{
    protected string $entityTypeCode;
    protected Mage_Eav_Model_Entity_Type $entityType;

    #[\Override]
    public function preDispatch()
    {
        $this->entityType = Mage::getSingleton('eav/config')->getEntityType($this->entityTypeCode);
        Mage::register('entity_type', $this->entityType, true);

        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }

    #[\Override]
    public function addActionLayoutHandles()
    {
        parent::addActionLayoutHandles();
        $this->getLayout()->getUpdate()
            ->removeHandle(strtolower($this->getFullActionName()))
            ->addHandle(strtolower('adminhtml_eav_attribute_' . $this->getRequest()->getActionName()))
            ->addHandle(strtolower($this->getFullActionName()));
        return $this;
    }

    protected function _initAction()
    {
        return $this->loadLayout();
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $id = $this->getRequest()->getParam('attribute_id');

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getModel($this->entityType->getAttributeModel());

        if ($id) {
            if ($websiteId = $this->getRequest()->getParam('website')) {
                $attribute->setWebsite($websiteId);
            }
            $attribute->load($id);

            if (!$attribute->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $this->__('This attribute no longer exists')
                );
                $this->_redirect('*/*/');
                return;
            }

            // Entity type check
            if ($attribute->getEntityTypeId() != $this->entityType->getEntityTypeId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $this->__('This attribute cannot be edited.')
                );
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $attribute->setEntityTypeId($this->entityType->getEntityTypeId());
        }

        // Restore entered data if an error was thrown during save
        $data = Mage::getSingleton('adminhtml/session')->getAttributeData(true);

        if (!empty($data)) {
            // If website specified, prefix relevant fields in saved data
            if ($attribute->getWebsite() && (int)$attribute->getWebsite()->getId()) {
                foreach ($attribute->getResource()->getScopeFields($attribute) as $field) {
                    if (array_key_exists($field, $data)) {
                        $data['scope_' . $field] = $data[$field];
                        unset($data[$field]);
                    }
                }
            }
            $attribute->addData($data);
        }

        Mage::register('entity_attribute', $attribute);

        $this->_initAction();

        if ($id) {
            $this->_title($attribute->getName());
            $this->_addBreadcrumb(
                $this->__('Edit Attribute'),
                $this->__('Edit Attribute')
            );
        } else {
            $this->_title($this->__('New Attribute'));
            $this->_addBreadcrumb(
                $this->__('New Attribute'),
                $this->__('New Attribute')
            );
        }

        // Add website switcher if editing existing attribute and we have a scope table
        if (!Mage::app()->isSingleStoreMode()) {
            if ($id && $attribute->getResource()->hasScopeTable()) {
                $this->getLayout()->getBlock('left')->insert(
                    $this->getLayout()->createBlock('adminhtml/website_switcher')
                        ->setDefaultWebsiteName($this->__('Default Values'))
                );
            }
        }

        $this->renderLayout();
    }

    public function validateAction()
    {
        $attributeId   = $this->getRequest()->getParam('attribute_id');
        $attributeCode = $this->getRequest()->getParam('attribute_code');

        $response = new Varien_Object();
        $response->setError(false);

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getModel($this->entityType->getAttributeModel());
        $attribute->loadByCode($this->entityType->getEntityTypeId(), $attributeCode);

        if ($attribute->getId() && !$attributeId) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Attribute with the same code already exists')
            );
            $this->_initLayoutMessages('adminhtml/session');
            $response->setError(true);
            $response->setMessage($this->getLayout()->getMessagesBlock()->getGroupedHtml());
        }

        $this->getResponse()->setBody($response->toJson());
    }

    /**
     * Filter post data
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        if ($data) {
            $data['frontend_label'] = (array) $data['frontend_label'];
            foreach ($data['frontend_label'] as & $value) {
                if ($value) {
                    $value = Mage::helper('eav')->stripTags($value);
                }
            }
            if (!empty($data['option']) && !empty($data['option']['value']) && is_array($data['option']['value'])) {
                foreach ($data['option']['value'] as $key => $values) {
                    foreach ($values as $storeId => $storeLabel) {
                        $data['option']['value'][$key][$storeId] = Mage::helper('eav')->stripTags($storeLabel);
                    }
                }
            }
        }
        return $data;
    }

    public function saveAction()
    {
        $id = $this->getRequest()->getParam('attribute_id');
        $data = $this->_filterPostData($this->getRequest()->getPost());
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getModel($this->entityType->getAttributeModel());

        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');

        // Validate frontend_input
        if (isset($data['frontend_input'])) {
            $allowedTypes = array_column($helper->getInputTypes($this->entityTypeCode), 'value');
            if (!in_array($data['frontend_input'], $allowedTypes)) {
                $session->addError(
                    $this->__('Input type "%s" not found in the input types list.', $data['frontend_input'])
                );
                $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
                return;
            }
        }

        if ($id) {
            if ($websiteId = $this->getRequest()->getParam('website')) {
                $attribute->setWebsite($websiteId);
            }
            $attribute->load($id);
            $inputType = $attribute->getFrontendInput();

            if (!$attribute->getId()) {
                $session->addError(
                    $this->__('This Attribute no longer exists')
                );
                $this->_redirect('*/*/');
                return;
            }

            // Entity type check
            if ($attribute->getEntityTypeId() != $this->entityType->getEntityTypeId()) {
                $session->addError(
                    $this->__('This attribute cannot be updated.')
                );
                $session->setAttributeData($data);
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $inputType = $data['frontend_input'];
            $data['entity_type_id'] = $this->entityType->getEntityTypeId();
            $data['is_user_defined'] = 1;
        }

        $defaultValueField = $helper->getAttributeDefaultValueField($this->entityTypeCode, $inputType);
        if ($defaultValueField) {
            $data['default_value'] = $data[$defaultValueField];
        }

        if ($attribute->getWebsite() && (int)$attribute->getWebsite()->getId()) {
            // Check "Use Default Value" checkboxes values
            if ($useDefaults = $this->getRequest()->getPost('use_default')) {
                foreach ($useDefaults as $field) {
                    $data[$field] = null;
                }
                if (in_array($defaultValueField, $useDefaults)) {
                    $data['default_value'] = null;
                }
            }
            // Prefix relevant fields in POST data
            foreach ($attribute->getResource()->getScopeFields($attribute) as $field) {
                if (array_key_exists($field, $data)) {
                    $data['scope_' . $field] = $data[$field];
                    unset($data[$field]);
                }
            }
        } else {
            // Check for no forms selected and set to empty array
            if ($attribute->getResource()->hasFormTable()) {
                if (!isset($data['used_in_forms'])) {
                    $data['used_in_forms'] = [];
                }
            }
        }

        $attribute->addData($data);

        Mage::dispatchEvent(
            "adminhtml_{$this->entityTypeCode}_attribute_edit_prepare_save",
            ['object' => $attribute, 'request' => $this->getRequest()]
        );

        try {
            $attribute->save();
            $session->addSuccess(
                $this->__('The attribute has been saved.')
            );

            // Clear translation cache because attribute labels are stored in translation
            Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);

            $session->setAttributeData(false);
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['attribute_id' => $attribute->getId(),'_current' => true]);
            } else {
                $this->_redirect('*/*/', []);
            }
        } catch (Exception $e) {
            $session->addError($e->getMessage());
            $session->setAttributeData($data);
            $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
        }
    }

    public function deleteAction()
    {
        $id = $this->getRequest()->getParam('attribute_id');
        if (!$id) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Unable to find an attribute to delete.')
            );
            $this->_redirect('*/*/');
            return;
        }

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = Mage::getModel($this->entityType->getAttributeModel());

        // Entity type check
        $attribute->load($id);
        if ($attribute->getEntityTypeId() != $this->entityType->getEntityTypeId() || !$attribute->getIsUserDefined()) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('This attribute cannot be deleted.')
            );
            $this->_redirect('*/*/');
            return;
        }

        try {
            $attribute->delete();
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('The attribute has been deleted.')
            );
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['attribute_id' => $this->getRequest()->getParam('attribute_id')]);
        }
    }
}
