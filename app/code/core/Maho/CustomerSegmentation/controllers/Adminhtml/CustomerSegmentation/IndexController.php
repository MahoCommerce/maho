<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Adminhtml_CustomerSegmentation_IndexController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('customer/customersegmentation')
            ->_addBreadcrumb(
                Mage::helper('customersegmentation')->__('Customer'),
                Mage::helper('customersegmentation')->__('Customer'),
            )
            ->_addBreadcrumb(
                Mage::helper('customersegmentation')->__('Customer Segments'),
                Mage::helper('customersegmentation')->__('Customer Segments'),
            );
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Customers'))
             ->_title($this->__('Customer Segments'));

        $this->_initAction()
            ->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        $segment = Mage::getModel('customersegmentation/segment');

        if ($segmentId) {
            $segment->load($segmentId);
            if (!$segment->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('customersegmentation')->__('This segment no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($this->__('Customers'))
             ->_title($this->__('Customer Segments'));

        if ($segment->getId()) {
            $this->_title($segment->getName());
        } else {
            $this->_title($this->__('New Segment'));
        }

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $segment->setData($data);
        }

        Mage::register('current_customer_segment', $segment);

        $this->_initAction()
            ->_addBreadcrumb(
                $segmentId ? Mage::helper('customersegmentation')->__('Edit Segment')
                           : Mage::helper('customersegmentation')->__('New Segment'),
                $segmentId ? Mage::helper('customersegmentation')->__('Edit Segment')
                           : Mage::helper('customersegmentation')->__('New Segment'),
            )
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $segment = Mage::getModel('customersegmentation/segment');
            $segmentId = $this->getRequest()->getParam('id');

            if ($segmentId) {
                $segment->load($segmentId);
                if (!$segment->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('customersegmentation')->__('This segment no longer exists.'),
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            try {
                // Process conditions
                if (isset($data['rule']['conditions'])) {
                    $data['conditions'] = $data['rule']['conditions'];
                }
                unset($data['rule']);

                $segment->loadPost($data);
                $segment->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('customersegmentation')->__('The segment has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $segment->getId()]);
                    return;
                }
                $this->_redirect('*/*/');
                return;

            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $segmentId]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if ($segmentId) {
            try {
                $segment = Mage::getModel('customersegmentation/segment');
                $segment->load($segmentId);
                $segment->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('customersegmentation')->__('The segment has been deleted.'),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $segmentIds = $this->getRequest()->getParam('segment');
        if (!is_array($segmentIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('customersegmentation')->__('Please select segment(s).'),
            );
        } else {
            try {
                $collection = Mage::getModel('customersegmentation/segment')->getCollection()
                    ->addFieldToFilter('segment_id', ['in' => $segmentIds]);

                foreach ($collection as $segment) {
                    $segment->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('customersegmentation')->__(
                        'Total of %d record(s) were deleted.',
                        count($segmentIds),
                    ),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function refreshAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if ($segmentId) {
            try {
                $segment = Mage::getModel('customersegmentation/segment');
                $segment->load($segmentId);
                if ($segment->getId()) {
                    $segment->refreshCustomers();
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('customersegmentation')->__('The segment has been refreshed.'),
                    );
                } else {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('customersegmentation')->__('Unable to find a segment to refresh.'),
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    public function massStatusAction(): void
    {
        $segmentIds = $this->getRequest()->getParam('segment');
        if (!is_array($segmentIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('customersegmentation')->__('Please select segment(s).'),
            );
        } else {
            try {
                $status = (int) $this->getRequest()->getParam('status');
                foreach ($segmentIds as $segmentId) {
                    $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
                    $segment->setIsActive($status)->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('customersegmentation')->__(
                        'Total of %d record(s) were updated.',
                        count($segmentIds),
                    ),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeParam = $this->getRequest()->getParam('type');
        $typeArr = explode('|', str_replace('-', '/', $typeParam));
        $type = $typeArr[0];

        $model = Mage::getModel($type);
        if (!$model) {
            $this->getResponse()->setBody('<!-- Model not found: ' . $type . ' -->');
            return;
        }

        $model->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('customersegmentation/segment'))
            ->setPrefix('conditions');

        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }

        $this->getResponse()->setBody($html);
    }

    public function newConditionAttributeAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $attribute = $this->getRequest()->getParam('attribute');
        $type = $this->getRequest()->getParam('type');

        $model = Mage::getModel($type);
        if (!$model) {
            $this->getResponse()->setBody('');
            return;
        }

        $model->setId($id)
            ->setType($type)
            ->setAttribute($attribute)
            ->setRule(Mage::getModel('customersegmentation/segment'))
            ->setPrefix('conditions');

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->getValueElementHtml();
        } else {
            $html = '';
        }

        $this->getResponse()->setBody($html);
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'save' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/save'),
            'delete', 'massdelete' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/delete'),
            'massstatus' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/save'),
            'refresh' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/refresh'),
            default => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/manage'),
        };
    }
}
