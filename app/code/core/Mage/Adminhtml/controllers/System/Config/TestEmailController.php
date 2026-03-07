<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Adminhtml_System_Config_TestEmailController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/config';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('send');
        return parent::preDispatch();
    }

    /**
     * Send test email action
     */
    public function sendAction(): void
    {
        $recipient = $this->getRequest()->getParam('recipient');

        if (!Mage::helper('core')->isValidEmail($recipient)) {
            $this->getResponse()->setBodyJson([
                'success' => false,
                'message' => Mage::helper('adminhtml')->__('Invalid email address.'),
            ]);
            return;
        }

        $dsn = Mage::helper('core')->getMailerDsn();
        if (empty($dsn) || $dsn === 'null://null') {
            $this->getResponse()->setBodyJson([
                'success' => false,
                'message' => Mage::helper('adminhtml')->__('Email transport is disabled. Please configure an email transport in Mail Sending Settings.'),
            ]);
            return;
        }

        try {
            $emailTemplate = Mage::getModel('core/email_template');
            $emailTemplate
                ->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'))
                ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
                ->setTemplateType(\Mage_Core_Model_Template::TYPE_TEXT)
                ->setTemplateText(Mage::helper('adminhtml')->__('This is a test email from Maho admin.'))
                ->setTemplateSubject(Mage::helper('adminhtml')->__('Maho Test Email'));

            $result = $emailTemplate->send($recipient, 'Test recipient');

            if ($result) {
                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'message' => Mage::helper('adminhtml')->__('Test email sent successfully to %s.', $recipient),
                ]);
            } else {
                $this->getResponse()->setBodyJson([
                    'success' => false,
                    'message' => Mage::helper('adminhtml')->__('Failed to send test email. Please check your email configuration and error logs.'),
                ]);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson([
                'success' => false,
                'message' => Mage::helper('adminhtml')->__('An error occurred: %s', $e->getMessage()),
            ]);
        }
    }
}
