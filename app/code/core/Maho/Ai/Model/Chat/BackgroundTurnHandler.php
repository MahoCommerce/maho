<?php

/**
 * Runs one background turn of the assistant in a queue worker, as the administrator who asked for it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\AdminSessionAuthenticator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The worker has no browser and no admin cookie. The handler logs the administrator into
 * the admin session, sets the admin bridge the API authenticator trusts, and sends an
 * internal request through the API kernel, so the turn runs with the same tools, the same
 * ACL and the same store context as a turn from the panel. The request carries an attribute
 * that no HTTP client can set; the controller serves the route only with it.
 */
class Maho_Ai_Model_Chat_BackgroundTurnHandler
{
    public const ATTRIBUTE = 'maho_ai_background';

    #[Maho\Config\MessageHandler]
    public function __invoke(Maho_Ai_Model_Chat_BackgroundTurn $message): void
    {
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load($message->conversationId);
        /** @var Mage_Admin_Model_User $admin */
        $admin = Mage::getModel('admin/user')->load($message->adminUserId);
        if (!$conversation->getId() || !$admin->getId() || !$admin->getIsActive()) {
            return;
        }

        Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_ADMINHTML, Mage_Core_Model_App_Area::PART_EVENTS);
        $session = Mage::getSingleton('admin/session');
        $session->setUser($admin);
        $session->refreshAcl($admin);
        $_SERVER['MAHO_ADMIN_USER_ID'] = (string) $admin->getId();
        $_SERVER['MAHO_ADMIN_USERNAME'] = (string) $admin->getUsername();
        $_SERVER['MAHO_IS_ADMIN'] = '1';
        $_SERVER['MAHO_API_BRIDGE_TOKEN'] = AdminSessionAuthenticator::generateBridgeToken((string) $admin->getId());

        $request = Request::create(
            '/api/admin/ai/chat/background',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) Mage::helper('core')->jsonEncode(['conversation_id' => (int) $conversation->getId(), 'instruction' => $message->instruction]),
        );
        $request->attributes->set(self::ATTRIBUTE, true);

        $kernel = new \Maho\ApiPlatform\Kernel('prod', false);
        try {
            $response = $kernel->handle($request);
            if ($response->getStatusCode() >= 400) {
                Mage::log(sprintf('Background assistant turn %d failed: HTTP %d %s', $conversation->getId(), $response->getStatusCode(), (string) $response->getContent()), Mage::LOG_ERROR);
            }
            $kernel->terminate($request, $response);
        } finally {
            $conversation->load($conversation->getId());
            if ($conversation->getStatus() === Maho_Ai_Model_Conversation::STATUS_RUNNING) {
                $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ACTIVE)->save();
            }
            $session->setUser(null);
            $session->setAcl(null);
            unset($_SERVER['MAHO_ADMIN_USER_ID'], $_SERVER['MAHO_ADMIN_USERNAME'], $_SERVER['MAHO_IS_ADMIN'], $_SERVER['MAHO_API_BRIDGE_TOKEN']);
        }
    }
}
