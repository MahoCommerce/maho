<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Promo_CatalogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'promo/catalog';

    /** The link of a notice from the message queue has no secret key: the grid only reads. */
    #[\Override]
    protected $_publicActions = ['index'];

    /**
     * Dirty rules notice message
     *
     * @var string
     */
    protected $_dirtyRulesNoticeMessage;

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('promo/catalog')
            ->_addBreadcrumb(
                Mage::helper('catalogrule')->__('Promotions'),
                Mage::helper('catalogrule')->__('Promotions'),
            );
        return $this;
    }

    #[Maho\Config\Route('/admin/promo_catalog/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Promotions'))->_title($this->__('Catalog Price Rules'));

        if (Mage::getModel('catalogrule/flag')->loadSelf()->hasChangesToApply()) {
            Mage::getSingleton('adminhtml/session')->addNotice(
                \Maho\Queue\QueueManager::dbTransport()->inFlightRowExists(Mage_CatalogRule_Model_ApplyAllMessage::DEDUPE_KEY)
                    ? Mage::helper('catalogrule')->__('The rules will be applied in the background. A message in the notifications tells you when they are applied.')
                    : $this->getDirtyRulesNoticeMessage(),
            );
        }

        $this->_initAction()
            ->_addBreadcrumb(
                Mage::helper('catalogrule')->__('Catalog'),
                Mage::helper('catalogrule')->__('Catalog'),
            )
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/promo_catalog/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/promo_catalog/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Promotions'))->_title($this->__('Catalog Price Rules'));

        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('catalogrule/rule');

        if ($id) {
            $model->load($id);
            if (!$model->getRuleId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('catalogrule')->__('This rule no longer exists.'),
                );
                $this->_redirect('*/*');
                return;
            }
        }

        $this->_title($model->getRuleId() ? $model->getName() : $this->__('New Rule'));

        // set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getPageData(true);
        if (!empty($data)) {
            $model->addData($data);
        }
        $model->getConditions()->setJsFormObject('rule_conditions_fieldset');

        Mage::register('current_promo_catalog_rule', $model);

        $this->_initAction()->getLayout()->getBlock('promo_catalog_edit')
             ->setData('action', $this->getUrl('*/promo_catalog/save'));

        $breadcrumb = $id
            ? Mage::helper('catalogrule')->__('Edit Rule')
            : Mage::helper('catalogrule')->__('New Rule');
        $this->_addBreadcrumb($breadcrumb, $breadcrumb)->renderLayout();
    }

    #[Maho\Config\Route('/admin/promo_catalog/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }
        $session = $this->_getSession();
        $id = (int) $this->getRequest()->getParam('rule_id');
        /** @var Mage_CatalogRule_Service_Rule $service */
        $service = Mage::getService('catalogrule/rule');
        try {
            Mage::dispatchEvent('adminhtml_controller_catalogrule_prepare_save', ['request' => $this->getRequest()]);
            $data = $this->getRequest()->getPost();
            $data = $this->_filterDates($data, ['from_date', 'to_date']);
            /** @var Mage_CatalogRule_Model_Rule $model */
            $model = $id ? $service->getById($id) : Mage::getModel('catalogrule/rule');

            $data['conditions'] = $data['rule']['conditions'];
            $autoApply = !empty($data['auto_apply']);
            unset($data['rule'], $data['auto_apply']);
            $model->loadPost($data);

            $session->setPageData($model->getData());
            $service->save($model);
            $session->addSuccess(Mage::helper('catalogrule')->__('The rule has been saved.'));
            $session->setPageData(false);
            if ($autoApply) {
                $this->getRequest()->setParam('rule_id', $model->getId());
                $this->_forward('applyRules');
            } elseif ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/');
            }
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
            $session->setPageData($data);
            $this->_redirect('*/*/edit', ['id' => $id ?: null]);
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addError(Mage::helper('catalogrule')->__('An error occurred while saving the rule data. Please review the log and try again.'));
            Mage::logException($e);
            $session->setPageData($data);
            $this->_redirect('*/*/edit', ['id' => $id ?: null]);
            return;
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/promo_catalog/delete')]
    public function deleteAction(): void
    {
        $session = $this->_getSession();
        /** @var Mage_CatalogRule_Service_Rule $service */
        $service = Mage::getService('catalogrule/rule');
        try {
            $service->delete($service->getById((int) $this->getRequest()->getParam('id')));
            $session->addSuccess(Mage::helper('catalogrule')->__('The rule has been deleted.'));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addError(Mage::helper('catalogrule')->__('An error occurred while deleting the rule. Please review the log and try again.'));
            Mage::logException($e);
            $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
            return;
        }
        $this->_redirect('*/*/');
    }

    #[Maho\Config\Route('/admin/promo_catalog/newConditionHtml')]
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('catalogrule/rule'))
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

    #[Maho\Config\Route('/admin/promo_catalog/newActionHtml')]
    public function newActionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('catalogrule/rule'))
            ->setPrefix('actions');
        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Action_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }

    /**
     * Queue the application of all active catalog price rules
     */
    #[Maho\Config\Route('/admin/promo_catalog/applyRules')]
    public function applyRulesAction(): void
    {
        try {
            /** @var Mage_CatalogRule_Service_Rule $service */
            $service = Mage::getService('catalogrule/rule');
            $service->queueApply(Mage::helper('adminnotification')->getCurrentAdminUserId());
            $this->_getSession()->addSuccess(Mage::helper('catalogrule')->__('The rules will be applied in the background. A message in the notifications tells you when they are applied.'));
        } catch (Exception $e) {
            $this->_getSession()->addError(Mage::helper('catalogrule')->__('Unable to apply rules.'));
            Mage::logException($e);
        }
        $this->_redirect('*/*');
    }

    /**
     * Set dirty rules notice message
     *
     * @param string $dirtyRulesNoticeMessage
     */
    public function setDirtyRulesNoticeMessage($dirtyRulesNoticeMessage)
    {
        $this->_dirtyRulesNoticeMessage = $dirtyRulesNoticeMessage;
    }

    /**
     * Get dirty rules notice message
     *
     * @return string
     */
    public function getDirtyRulesNoticeMessage()
    {
        $defaultMessage = Mage::helper('catalogrule')->__('There are rules that have been changed but were not applied. Please, click Apply Rules in order to see immediate effect in the catalog.');
        return $this->_dirtyRulesNoticeMessage ?: $defaultMessage;
    }
}
