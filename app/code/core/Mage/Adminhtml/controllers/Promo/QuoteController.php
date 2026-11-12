<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Promo_QuoteController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'promo/quote';

    protected function _initRule()
    {
        $this->_title($this->__('Promotions'))->_title($this->__('Shopping Cart Price Rules'));

        Mage::register('current_promo_quote_rule', Mage::getModel('salesrule/rule'));
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id && $this->getRequest()->getParam('rule_id')) {
            $id = (int) $this->getRequest()->getParam('rule_id');
        }

        if ($id) {
            Mage::registry('current_promo_quote_rule')->load($id);
        }
    }

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('promo/quote')
            ->_addBreadcrumb(Mage::helper('salesrule')->__('Promotions'), Mage::helper('salesrule')->__('Promotions'))
        ;
        return $this;
    }

    #[Maho\Config\Route('/admin/promo_quote/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Promotions'))->_title($this->__('Shopping Cart Price Rules'));

        $this->_initAction()
            ->_addBreadcrumb(Mage::helper('salesrule')->__('Catalog'), Mage::helper('salesrule')->__('Catalog'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/promo_quote/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/promo_quote/edit')]
    public function editAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('salesrule/rule');

        if ($id) {
            $model->load($id);
            if (!$model->getRuleId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('salesrule')->__('This rule no longer exists.'),
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
        $model->getActions()->setJsFormObject('rule_actions_fieldset');

        Mage::register('current_promo_quote_rule', $model);

        $this->_initAction()->getLayout()->getBlock('promo_quote_edit')
             ->setData('action', $this->getUrl('*/*/save'));

        $this
            ->_addBreadcrumb(
                $id ? Mage::helper('salesrule')->__('Edit Rule')
                    : Mage::helper('salesrule')->__('New Rule'),
                $id ? Mage::helper('salesrule')->__('Edit Rule')
                : Mage::helper('salesrule')->__('New Rule'),
            )
            ->renderLayout();
    }

    /**
     * Promo quote save action
     */
    #[Maho\Config\Route('/admin/promo_quote/save')]
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }
        $session = $this->_getSession();
        $id = (int) $this->getRequest()->getParam('rule_id');
        /** @var Mage_SalesRule_Service_Rule $service */
        $service = Mage::getService('salesrule/rule');
        try {
            Mage::dispatchEvent('adminhtml_controller_salesrule_prepare_save', ['request' => $this->getRequest()]);
            $data = $this->getRequest()->getPost();
            $data = $this->_filterDates($data, ['from_date', 'to_date']);
            /** @var Mage_SalesRule_Model_Rule $model */
            $model = $id ? $service->getById($id) : Mage::getModel('salesrule/rule');

            foreach (['conditions', 'actions'] as $tree) {
                if (isset($data['rule'][$tree])) {
                    $data[$tree] = $data['rule'][$tree];
                }
            }
            unset($data['times_used'], $data['rule']);
            $model->loadPost($data);
            $model->setUseAutoGeneration(!empty($data['use_auto_generation']));

            $session->setPageData($model->getData());
            $service->save($model);
            $session->addSuccess(Mage::helper('salesrule')->__('The rule has been saved.'));
            $session->setPageData(false);
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $model->getId()]);
            } else {
                $this->_redirect('*/*/');
            }
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
            $session->setPageData($data);
            $this->_redirect('*/*/edit', ['id' => $id ?: null]);
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            if ($id) {
                $this->_redirect('*/*/edit', ['id' => $id]);
            } else {
                $this->_redirect('*/*/new');
            }
        } catch (Exception $e) {
            $session->addError(Mage::helper('catalogrule')->__('An error occurred while saving the rule data. Please review the log and try again.'));
            Mage::logException($e);
            $session->setPageData($data);
            $this->_redirect('*/*/edit', ['id' => $id ?: null]);
        }
    }

    #[Maho\Config\Route('/admin/promo_quote/delete')]
    public function deleteAction(): void
    {
        $session = $this->_getSession();
        /** @var Mage_SalesRule_Service_Rule $service */
        $service = Mage::getService('salesrule/rule');
        try {
            $service->delete($service->getById((int) $this->getRequest()->getParam('id')));
            $session->addSuccess(Mage::helper('salesrule')->__('The rule has been deleted.'));
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

    /**
     * New condition HTML action
     *
     * @throws Mage_Core_Exception
     */
    #[Maho\Config\Route('/admin/promo_quote/newConditionHtml')]
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        if (!$this->_validateRequestParams([$id, $type])) {
            if ($this->getRequest()->getQuery('id')) {
                $this->getRequest()->setQuery('id', '');
            }
            Mage::throwException(Mage::helper('adminhtml')->__('An error occurred while adding condition.'));
        }

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('salesrule/rule'))
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

    #[Maho\Config\Route('/admin/promo_quote/newActionHtml')]
    public function newActionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('salesrule/rule'))
            ->setPrefix('actions');
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

    #[Maho\Config\Route('/admin/promo_quote/applyRules')]
    public function applyRulesAction(): void
    {
        $this->_initAction();
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/promo_quote/grid')]
    public function gridAction(): void
    {
        $this->_initRule()->loadLayout()->renderLayout();
    }

    /**
     * Coupon codes grid
     */
    #[Maho\Config\Route('/admin/promo_quote/couponsGrid')]
    public function couponsGridAction(): void
    {
        $this->_initRule();
        $this->loadLayout()->renderLayout();
    }

    /**
     * Export coupon codes as excel xml file
     */
    #[Maho\Config\Route('/admin/promo_quote/exportCouponsXml')]
    public function exportCouponsXmlAction(): void
    {
        $this->_initRule();
        $rule = Mage::registry('current_promo_quote_rule');
        if ($rule->getId()) {
            $grid = $this->getLayout()->createBlock('adminhtml/promo_quote_edit_tab_coupons_grid');
            $this->_prepareDownloadResponse(...$grid->getExcelFile('coupon_codes.xml', -1));
        } else {
            $this->_redirect('*/*/detail', ['_current' => true]);
            return;
        }
    }

    /**
     * Export coupon codes as CSV file
     */
    #[Maho\Config\Route('/admin/promo_quote/exportCouponsCsv')]
    public function exportCouponsCsvAction(): void
    {
        $this->_initRule();
        $rule = Mage::registry('current_promo_quote_rule');
        if ($rule->getId()) {
            $grid = $this->getLayout()->createBlock('adminhtml/promo_quote_edit_tab_coupons_grid');
            $this->_prepareDownloadResponse(...$grid->getCsvFile('coupon_codes.csv', -1));
        } else {
            $this->_redirect('*/*/detail', ['_current' => true]);
            return;
        }
    }

    /**
     * Coupons mass delete action
     */
    #[Maho\Config\Route('/admin/promo_quote/couponsMassDelete')]
    public function couponsMassDeleteAction(): void
    {
        $this->_initRule();
        $rule = Mage::registry('current_promo_quote_rule');
        if (!$rule->getId()) {
            $this->_forward('noRoute');
            return;
        }

        $codesIds = $this->getRequest()->getParam('ids');
        if (is_array($codesIds)) {
            /** @var Mage_SalesRule_Service_Rule $service */
            $service = Mage::getService('salesrule/rule');
            $service->deleteCoupons($rule, array_map(intval(...), $codesIds));
        }
    }

    /**
     * Generate Coupons action
     */
    #[Maho\Config\Route('/admin/promo_quote/generate')]
    public function generateAction(): void
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noRoute');
            return;
        }
        $result = [];
        $this->_initRule();

        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::registry('current_promo_quote_rule');

        if (!$rule->getId()) {
            $result['error'] = Mage::helper('salesrule')->__('Rule is not defined');
        } else {
            try {
                $request = $this->getRequest();
                $number = fn(string $field): ?int => ($request->getParam($field) ?? '') === '' ? null : (int) $request->getParam($field);
                $text = fn(string $field): ?string => ($request->getParam($field) ?? '') === '' ? null : (string) $request->getParam($field);
                // The tab generates when the form has auto generation on, before its save too, with the uses of the form
                if ($request->getParam('use_auto_generation')) {
                    $rule->setUseAutoGeneration();
                }
                $rule->setUsesPerCoupon((int) $request->getParam('uses_per_coupon', $rule->getUsesPerCoupon()))
                    ->setUsesPerCustomer((int) $request->getParam('uses_per_customer', $rule->getUsesPerCustomer()));
                /** @var Mage_SalesRule_Service_Rule $service */
                $service = Mage::getService('salesrule/rule');
                $generated = $service->generateCoupons(
                    $rule,
                    (int) $request->getParam('qty', 0),
                    $number('length'),
                    $text('format'),
                    $text('prefix'),
                    $text('suffix'),
                    $number('dash'),
                );
                $this->_getSession()->addSuccess(Mage::helper('salesrule')->__('%s Coupon(s) have been generated', $generated));
                $this->_initLayoutMessages('adminhtml/session');
                $result['messages'] = $this->getLayout()->getMessagesBlock()->getGroupedHtml();
            } catch (Mage_Core_Exception $e) {
                $result['error'] = $e->getMessage();
            } catch (Exception $e) {
                $result['error'] = Mage::helper('salesrule')->__('An error occurred while generating coupons. Please review the log and try again.');
                Mage::logException($e);
            }
        }
        $this->getResponse()->setBodyJson($result);
    }

    /**
     * Chooser source action
     */
    #[Maho\Config\Route('/admin/promo_quote/chooser')]
    public function chooserAction(): void
    {
        $uniqId = $this->getRequest()->getParam('uniq_id');
        $chooserBlock = $this->getLayout()->createBlock('adminhtml/promo_widget_chooser', '', [
            'id' => $uniqId,
        ]);
        $this->getResponse()->setBody($chooserBlock->toHtml());
    }
}
