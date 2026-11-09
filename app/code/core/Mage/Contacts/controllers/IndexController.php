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
    public const XML_PATH_ENABLED                    = Mage_Contacts_Service_Message::XML_PATH_ENABLED;
    public const XML_PATH_EMAIL_SENDER               = Mage_Contacts_Service_Message::XML_PATH_EMAIL_SENDER;
    public const XML_PATH_EMAIL_RECIPIENT            = Mage_Contacts_Service_Message::XML_PATH_EMAIL_RECIPIENT;
    public const XML_PATH_EMAIL_TEMPLATE             = Mage_Contacts_Service_Message::XML_PATH_EMAIL_TEMPLATE;
    public const XML_PATH_AUTO_REPLY_ENABLED         = Mage_Contacts_Service_Message::XML_PATH_AUTO_REPLY_ENABLED;
    public const XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE  = Mage_Contacts_Service_Message::XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE;
    public const XML_PATH_HONEYPOT_ENABLED           = Mage_Contacts_Service_Message::XML_PATH_HONEYPOT_ENABLED;
    public const XML_PATH_IP_RATE_LIMIT              = Mage_Contacts_Service_Message::XML_PATH_IP_RATE_LIMIT;
    public const XML_PATH_RECIPIENT_RATE_LIMIT       = Mage_Contacts_Service_Message::XML_PATH_RECIPIENT_RATE_LIMIT;

    /**
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->messageService()->isEnabled(Mage_Contacts_Model_Channel::Web)) {
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
        if (!$post) {
            $this->_redirect('*/*/');
            return;
        }

        $session = Mage::getSingleton('customer/session');
        try {
            $this->messageService()->send(
                $post,
                Mage_Contacts_Model_Channel::Web,
                (int) Mage::app()->getStore()->getId(),
            );
            $session->addSuccess($this->__('Your inquiry was submitted and will be responded to as soon as possible. Thank you for contacting us.'));
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('Unable to submit your request. Please try again later'));
        }
        $this->_redirect('*/*/');
    }

    protected function messageService(): Mage_Contacts_Service_Message
    {
        return Mage::getService('contacts/message');
    }
}
