<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Adminhtml_Cataloglinkrule_RuleController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cataloglinkrule/rules';

    public function indexAction(): void
    {
        $this->loadLayout()
            ->_setActiveMenu('cataloglinkrule/rules')
            ->_title(Mage::helper('cataloglinkrule')->__('Catalog'))
            ->_title(Mage::helper('cataloglinkrule')->__('Product Relationship Rules'))
            ->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $id = $this->getRequest()->getUserParam('id');
        $model = Mage::getModel('cataloglinkrule/rule');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('cataloglinkrule')->__('This rule no longer exists.')
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        // Register the current rule for use in blocks
        Mage::register('current_linkrule', $model);

        $this->loadLayout()
            ->_setActiveMenu('cataloglinkrule/rules')
            ->_title(Mage::helper('cataloglinkrule')->__('Catalog'))
            ->_title(Mage::helper('cataloglinkrule')->__('Product Relationship Rules'))
            ->_title($id ? $model->getName() : Mage::helper('cataloglinkrule')->__('New Rule'))
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getUserParam('rule')) {
            try {
                $id = $this->getRequest()->getUserParam('id');
                $model = Mage::getModel('cataloglinkrule/rule');

                if ($id) {
                    $model->load($id);
                }

                $model->loadPost($data);
                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cataloglinkrule')->__('The rule has been saved.')
                );

                if ($this->getRequest()->getUserParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                    return;
                }

                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::logException($e);

                if ($id = $this->getRequest()->getUserParam('id')) {
                    $this->_redirect('*/*/edit', ['id' => $id]);
                } else {
                    $this->_redirect('*/*/new');
                }
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getUserParam('id')) {
            try {
                $model = Mage::getModel('cataloglinkrule/rule')->load($id);
                $model->delete();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cataloglinkrule')->__('The rule has been deleted.')
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::logException($e);
            }
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $ruleIds = $this->getRequest()->getUserParam('rule_ids');
        if (!is_array($ruleIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('cataloglinkrule')->__('Please select rule(s).')
            );
        } else {
            try {
                foreach ($ruleIds as $ruleId) {
                    $model = Mage::getModel('cataloglinkrule/rule')->load($ruleId);
                    $model->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cataloglinkrule')->__('Total of %d record(s) were deleted.', count($ruleIds))
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::logException($e);
            }
        }
        $this->_redirect('*/*/index');
    }

    public function massStatusAction(): void
    {
        $ruleIds = $this->getRequest()->getUserParam('rule_ids');
        $status = (int) $this->getRequest()->getUserParam('status');

        if (!is_array($ruleIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('cataloglinkrule')->__('Please select rule(s).')
            );
        } else {
            try {
                foreach ($ruleIds as $ruleId) {
                    $model = Mage::getModel('cataloglinkrule/rule')->load($ruleId);
                    $model->setIsActive($status)->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cataloglinkrule')->__('Total of %d record(s) were updated.', count($ruleIds))
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::logException($e);
            }
        }
        $this->_redirect('*/*/index');
    }

    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getUserParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getUserParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('cataloglinkrule/rule'))
            ->setPrefix('conditions');

        if (!empty($typeArr[1]) && $model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getUserParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }

    public function newActionHtmlAction(): void
    {
        $id = $this->getRequest()->getUserParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getUserParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('cataloglinkrule/rule'))
            ->setPrefix('actions');

        if (!empty($typeArr[1]) && $model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getUserParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }

    #[\Override]
    public function preDispatch(): bool|Mage_Core_Controller_Varien_Action
    {
        $this->_setForcedFormKeyActions(['delete', 'massDelete', 'massStatus']);
        return parent::preDispatch();
    }
}
