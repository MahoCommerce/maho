<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_System_Config_TestEmailController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/config/system';

    #[Maho\Config\Route('/admin/system_config_testemail/send')]
    public function sendAction(): void
    {
        $recipient = (string) $this->getRequest()->getParam('recipient');
        try {
            /** @var Mage_Core_Service_Email_Template $service */
            $service = Mage::getService('core/email_template');
            $service->sendTest($recipient);
            $result = ['success' => true, 'message' => $this->__('Test email sent successfully to %s.', $recipient)];
        } catch (Mage_Core_Exception $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Mage::logException($e);
            $result = ['success' => false, 'message' => $this->__('An error occurred: %s', $e->getMessage())];
        }
        $this->getResponse()->setBodyJson($result);
    }
}
