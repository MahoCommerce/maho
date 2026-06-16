<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

class Mage_Contacts_IndexController extends Mage_Core_Controller_Front_Action
{
    public const XML_PATH_ENABLED                    = 'contacts/contacts/enabled';
    public const XML_PATH_EMAIL_SENDER               = 'contacts/email/sender_email_identity';
    public const XML_PATH_EMAIL_RECIPIENT            = 'contacts/email/recipient_email';
    public const XML_PATH_EMAIL_TEMPLATE             = 'contacts/email/email_template';
    public const XML_PATH_AUTO_REPLY_ENABLED         = 'contacts/auto_reply/enabled';
    public const XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE  = 'contacts/auto_reply/email_template';

    /**
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::getStoreConfigFlag(self::XML_PATH_ENABLED)) {
            $this->norouteAction();
        }
        return $this;
    }

    #[Maho\Config\Route('/contacts', name: 'contacts.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('contactForm')
            ->setFormAction(Mage::getUrl('*/*/post', ['_secure' => $this->getRequest()->isSecure()]));

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }

    #[Maho\Config\Route('/contacts/post', name: 'contacts.post', methods: ['POST'])]
    public function postAction(): void
    {
        $post = $this->getRequest()->getPost();
        if ($post) {
            $translate = Mage::getSingleton('core/translate');
            /** @var Mage_Core_Model_Translate $translate */
            $translate->setTranslateInline(false);
            try {
                if (!$this->_validateFormKey()) {
                    Mage::throwException($this->__('Invalid Form Key. Please submit your request again.'));
                }

                $postObject = new \Maho\DataObject();
                $postObject->setData($post);

                // check data
                $error = false;

                // Validate name
                if (!Mage::helper('core')->isValidNotBlank(trim($post['name']))) {
                    $error = true;
                }
                // Validate comment
                elseif (!Mage::helper('core')->isValidNotBlank(trim($post['comment']))) {
                    $error = true;
                }
                // Validate email
                elseif (!Mage::helper('core')->isValidEmail(trim($post['email']))) {
                    $error = true;
                }

                if ($error) {
                    Mage::throwException($this->__('Unable to submit your request. Please try again later'));
                }

                // Throttle by submitter email, same key as the API endpoint so a
                // bot rotating between web form and API still hits a unified limit.
                $contactLimit = (int) Mage::getStoreConfig('system/rate_limit/contact');
                if ($contactLimit > 0) {
                    $email = strtolower(trim($post['email']));
                    $storeId = (int) Mage::app()->getStore()->getId();
                    if (Mage::helper('core')->isRateLimitExceeded(false, true, "contact:{$storeId}:{$email}", $contactLimit, 3600)) {
                        Mage::throwException($this->__('Too Soon: You are trying to perform this operation too frequently. Please wait a few seconds and try again.'));
                    }
                }

                // send email
                $mailTemplate = Mage::getModel('core/email_template');
                /** @var Mage_Core_Model_Email_Template $mailTemplate */
                $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND])
                    ->setReplyTo($post['email'])
                    ->sendTransactional(
                        Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE),
                        Mage::getStoreConfig(self::XML_PATH_EMAIL_SENDER),
                        Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT),
                        null,
                        ['data' => $postObject],
                    );

                if (!$mailTemplate->getSentSuccess()) {
                    Mage::throwException($this->__('Unable to submit your request. Please try again later'));
                }

                // send auto reply email to customer
                if (Mage::getStoreConfigFlag(self::XML_PATH_AUTO_REPLY_ENABLED)) {
                    $mailTemplate = Mage::getModel('core/email_template');
                    /** @var Mage_Core_Model_Email_Template $mailTemplate */
                    $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND])
                        ->setReplyTo(Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT))
                        ->sendTransactional(
                            Mage::getStoreConfig(self::XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE),
                            Mage::getStoreConfig(self::XML_PATH_EMAIL_SENDER),
                            $post['email'],
                            null,
                            ['data' => $postObject],
                        );
                }

                $translate->setTranslateInline(true);
                Mage::getSingleton('customer/session')->addSuccess($this->__('Your inquiry was submitted and will be responded to as soon as possible. Thank you for contacting us.'));
                $this->_redirect('*/*/');

                return;
            } catch (Mage_Core_Exception $e) {
                $translate->setTranslateInline(true);
                Mage::logException($e);
                Mage::getSingleton('customer/session')->addError($e->getMessage());
            } catch (Throwable $e) {
                Mage::logException($e);
                Mage::getSingleton('customer/session')->addError($this->__('Unable to submit your request. Please try again later'));
                $this->_redirect('*/*/');
                return;
            }
        } else {
            $this->_redirect('*/*/');
        }
    }
}
