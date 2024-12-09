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
 * Abstract attribute set controller
 */
abstract class Mage_Eav_Controller_Adminhtml_Set_Abstract extends Mage_Adminhtml_Controller_Action
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
            ->addHandle(strtolower('adminhtml_eav_set_' . $this->getRequest()->getActionName()))
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

    public function setGridAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    public function addAction()
    {
        $this->_initAction()
            ->_title($this->__('New Set'))
            ->renderLayout();
    }

    public function editAction()
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set')
            ->load($this->getRequest()->getParam('id'));

        if (!$attributeSet->getId()) {
            $this->_redirect('*/*/index');
            return;
        }

        Mage::register('current_attribute_set', $attributeSet);

        $this->_initAction()
            ->_title($attributeSet->getAttributeSetName())
            ->renderLayout();
    }

    public function createFromSkeletonSetAction()
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set');

        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');

        try {
            $data = $this->getRequest()->getPost();

            $attributeSet->setEntityTypeId($this->entityType->getEntityTypeId())
                ->setAttributeSetName($helper->stripTags($data['attribute_set_name']));

            $attributeSet->validate();

            $attributeSet->save()
                ->initFromSkeleton($data['skeleton_set'])
                ->save();

            $this->_redirect('*/*/edit', ['id' => $attributeSet->getId()]);
        } catch (Exception $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['id' => $attributeSet->getId()]);
        }
    }

    public function saveAction()
    {
        if ($this->getRequest()->getPost('skeleton_set')) {
            $this->_forward('createFromSkeletonSet');
            return;
        }

        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set')
            ->load($this->getRequest()->getParam('id'));

        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');

        $hasError = false;
        try {
            if (!$attributeSet->getId()) {
                Mage::throwException(Mage::helper('eav')->__('This attribute set no longer exists.'));
            }
            $data = Mage::helper('core')->jsonDecode($this->getRequest()->getPost('data'));
            $data['attribute_set_name'] = $helper->stripTags($data['attribute_set_name']);

            $attributeSet->organizeData($data)->validate();
            $attributeSet->save();

            $this->_getSession()->addSuccess(Mage::helper('eav')->__('The attribute set has been saved.'));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $hasError = true;
        } catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('eav')->__('An error occurred while saving the attribute set.')
            );
            $hasError = true;
        }

        if ($hasError) {
            $this->_initLayoutMessages('adminhtml/session');
            $response = [
                'error'   => 1,
                'message' => $this->getLayout()->getMessagesBlock()->getGroupedHtml(),
            ];
        } else {
            $response = [
                'error'   => 0,
                'url'     => $this->getUrl('*/*/'),
            ];
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function deleteAction()
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set')
            ->load($this->getRequest()->getParam('id'));

        try {
            $attributeSet->delete();

            $this->_getSession()->addSuccess($this->__('The attribute set has been removed.'));
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('An error occurred while deleting this set.'));
            $this->_redirectReferer();
        }
    }
}
