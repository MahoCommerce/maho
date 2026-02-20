<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
        try {
            $id = $this->getRequest()->getParam('id');
            $typeParam = $this->getRequest()->getParam('type');
            $form = $this->getRequest()->getParam('form');

            $typeArr = explode('|', str_replace('-', '/', $typeParam));
            $type = $typeArr[0];

            // Validate the type parameter
            if (empty($type) || !preg_match('/^[a-zA-Z_\/]+$/', $type)) {
                throw new Exception('Invalid type parameter: ' . $type);
            }

            $model = Mage::getModel($type);
            if (!$model) {
                throw new Exception('Model not found: ' . $type);
            }

            $model->setId($id)
                ->setType($type)
                ->setRule(Mage::getModel('customersegmentation/segment'))
                ->setPrefix('conditions');

            if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
                if (!empty($typeArr[1])) {
                    $model->setAttribute($typeArr[1]);
                }
                $model->setJsFormObject($form ?: 'rule_conditions_fieldset');
                $html = '<li>' . $model->asHtmlRecursive() . '</li>';
            } else {
                $html = '';
            }

            $this->getResponse()->setBody($html);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()
                ->clearHeaders()
                ->setBodyJson([
                    'error' => true,
                    'message' => $e->getMessage(),
                ]);
        }
    }

    public function customersTabAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if (!$segmentId) {
            $this->getResponse()->setBody('');
            return;
        }

        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        if (!$segment->getId()) {
            $this->getResponse()->setBody('');
            return;
        }

        Mage::register('current_customer_segment', $segment);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('customersegmentation/adminhtml_segment_edit_tab_customers')
                ->toHtml(),
        );
    }

    public function customersGridAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if (!$segmentId) {
            $this->getResponse()->setBody('');
            return;
        }

        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        if (!$segment->getId()) {
            $this->getResponse()->setBody('');
            return;
        }

        Mage::register('current_customer_segment', $segment);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('customersegmentation/adminhtml_segment_edit_tab_customers')
                ->toHtml(),
        );
    }

    /**
     * Email Sequences Grid AJAX action (deprecated - use Enter/Exit specific actions)
     */
    public function sequencesGridAction(): void
    {
        $this->_forward('sequencesGridEnter');
    }

    /**
     * Email Sequences Enter Grid AJAX action
     */
    public function sequencesGridEnterAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if (!$segmentId) {
            $this->getResponse()->setBody('');
            return;
        }

        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        if (!$segment->getId()) {
            $this->getResponse()->setBody('');
            return;
        }

        Mage::register('current_customer_segment', $segment);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('customersegmentation/adminhtml_segment_edit_tab_emailSequencesEnter')
                ->toHtml(),
        );
    }

    /**
     * Email Sequences Exit Grid AJAX action
     */
    public function sequencesGridExitAction(): void
    {
        $segmentId = $this->getRequest()->getParam('id');
        if (!$segmentId) {
            $this->getResponse()->setBody('');
            return;
        }

        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        if (!$segment->getId()) {
            $this->getResponse()->setBody('');
            return;
        }

        Mage::register('current_customer_segment', $segment);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('customersegmentation/adminhtml_segment_edit_tab_emailSequencesExit')
                ->toHtml(),
        );
    }

    /**
     * New email sequence action
     */
    public function newSequenceAction(): void
    {
        $this->_forward('editSequence');
    }

    /**
     * Edit email sequence action
     */
    public function editSequenceAction(): void
    {
        $sequenceId = $this->getRequest()->getParam('id');
        $segmentId = $this->getRequest()->getParam('segment_id');
        $triggerEvent = $this->getRequest()->getParam('trigger_event');

        $sequence = Mage::getModel('customersegmentation/emailSequence');
        $segment = Mage::getModel('customersegmentation/segment');

        if ($sequenceId) {
            $sequence->load($sequenceId);
            if (!$sequence->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('customersegmentation')->__('This sequence no longer exists.'),
                );
                $this->_redirect('*/*/edit', ['id' => $segmentId, 'tab' => 'email_sequences_enter']);
                return;
            }
            $segmentId = $sequence->getSegmentId();
            $triggerEvent = $sequence->getTriggerEvent();
        }

        if ($segmentId) {
            $segment->load($segmentId);
            if (!$segment->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('customersegmentation')->__('Invalid segment.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        // Set default values for new sequence
        if (!$sequence->getId()) {
            // Validate trigger_event
            if (!$triggerEvent || !in_array($triggerEvent, [
                Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER,
                Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT,
            ])) {
                $triggerEvent = Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER;
            }

            $sequence->setSegmentId($segmentId);
            $sequence->setTriggerEvent($triggerEvent);
            $sequence->setIsActive(true);
            $sequence->setDelayMinutes(0);
            $sequence->setCouponExpiresDays(30);

            // Get next step number for this trigger type
            $resource = Mage::getResourceSingleton('customersegmentation/emailSequence');
            $nextStep = $resource->getNextStepNumber((int) $segmentId, $triggerEvent);
            $sequence->setStepNumber($nextStep);
        }

        $this->_title($this->__('Customers'))
             ->_title($this->__('Customer Segments'))
             ->_title($segment->getName())
             ->_title($sequence->getId() ? $this->__('Edit Sequence') : $this->__('New Sequence'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $sequence->setData($data);
        }

        Mage::register('current_email_sequence', $sequence);
        Mage::register('current_customer_segment', $segment);

        $this->loadLayout();
        $this->_setActiveMenu('customer/customersegmentation');
        $this->renderLayout();
    }

    /**
     * Save email sequence action
     */
    public function saveSequenceAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $sequence = Mage::getModel('customersegmentation/emailSequence');
            $sequenceId = $this->getRequest()->getParam('id');

            if ($sequenceId) {
                $sequence->load($sequenceId);
                if (!$sequence->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('customersegmentation')->__('This sequence no longer exists.'),
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            try {
                // Use addData to preserve the sequence_id when editing
                $sequence->addData($data);
                $sequence->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('customersegmentation')->__('The email sequence has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/editSequence', ['id' => $sequence->getId()]);
                    return;
                }

                // Redirect to appropriate tab based on trigger event
                $tab = $sequence->getTriggerEvent() === Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT
                    ? 'email_sequences_exit'
                    : 'email_sequences_enter';

                $this->_redirect('*/*/edit', [
                    'id' => $sequence->getSegmentId(),
                    'tab' => $tab,
                ]);
                return;

            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/editSequence', ['id' => $sequenceId]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Delete email sequence action
     */
    public function deleteSequenceAction(): void
    {
        $sequenceId = $this->getRequest()->getParam('id');
        $segmentId = $this->getRequest()->getParam('segment_id');

        if (!$sequenceId || !$segmentId) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('customersegmentation')->__('Invalid request.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        try {
            $sequence = Mage::getModel('customersegmentation/emailSequence');
            $sequence->load($sequenceId);

            if (!$sequence->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('customersegmentation')->__('Sequence not found.'),
                );
                $this->_redirect('*/*/edit', ['id' => $segmentId, 'tab' => 'email_sequences']);
                return;
            }

            $sequence->delete();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('customersegmentation')->__('The email sequence has been deleted.'),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/edit', ['id' => $segmentId, 'tab' => 'email_sequences']);
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
            'savesequence' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/email_sequences/save'),
            'deletesequence' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/email_sequences/delete'),
            'editsequence', 'newsequence', 'sequencesgrid', 'sequencesgridenter', 'sequencesgridexit' => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/email_sequences/manage'),
            default => Mage::getSingleton('admin/session')->isAllowed('customer/customersegmentation/manage'),
        };
    }
}
