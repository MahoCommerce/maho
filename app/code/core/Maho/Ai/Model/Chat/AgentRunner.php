<?php

/**
 * Runs one turn of the assistant without a browser, as the administrator who asked for it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\AdminSessionAuthenticator;
use Symfony\Component\HttpFoundation\Request;

/**
 * A worker has no browser and no admin cookie. The runner logs the administrator into the
 * admin session, sets the admin bridge the API authenticator trusts, and sends an internal
 * request through the API kernel, so the turn runs with the same tools, the same ACL and the
 * same store context as a turn from the panel. The request carries an attribute that no HTTP
 * client can set; the controller serves the route only with it.
 */
class Maho_Ai_Model_Chat_AgentRunner
{
    public const ATTRIBUTE = 'maho_ai_background';

    public const STATE_COMPLETE = 'complete';
    public const STATE_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATE_ERROR = 'error';

    /**
     * Store the instruction as the first message of the conversation and queue the run. The
     * instruction is in the history from the start, so a lost queue message loses nothing.
     *
     * @param array<string, mixed> $context
     */
    public static function queue(
        Maho_Ai_Model_Conversation $conversation,
        int $adminUserId,
        string $instruction,
        Maho_Ai_Model_Chat_RunMode $mode,
        array $context = [],
    ): Maho_Ai_Model_Task {
        $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_RUNNING)->save();
        $conversation->addMessage(['role' => Maho_Ai_Model_Conversation_Message::ROLE_USER, 'content' => $instruction]);

        /** @var Maho_Ai_Model_Task $task */
        $task = Mage::getModel('ai/task');
        $task->setData([
            'consumer' => Maho_Ai_Model_Conversation::USAGE_CONSUMER,
            'action' => $mode->value,
            'task_type' => Maho_Ai_Model_Task::TYPE_AGENT,
            'status' => Maho_Ai_Model_Task::STATUS_PENDING,
            'priority' => Maho_Ai_Model_Task::PRIORITY_BACKGROUND,
            'messages' => Mage::helper('core')->jsonEncode([['role' => 'user', 'content' => $instruction]]),
            'context' => Mage::helper('core')->jsonEncode(['mode' => $mode->value] + $context),
            // A run can write: a second attempt would repeat what the first one did.
            'max_retries' => 0,
            'admin_user_id' => $adminUserId,
            'store_id' => (int) $conversation->getStoreId(),
        ]);
        $task->setConversationId((int) $conversation->getId());
        $task->save();
        $task->queue();

        return $task;
    }

    /**
     * @return array{state: string, error: string}
     */
    public function run(Maho_Ai_Model_Task $task): array
    {
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load((int) $task->getConversationId());
        /** @var Mage_Admin_Model_User $admin */
        $admin = Mage::getModel('admin/user')->load((int) $task->getData('admin_user_id'));
        if (!$conversation->getId()) {
            return ['state' => self::STATE_ERROR, 'error' => 'The conversation of the run is gone.'];
        }
        if (!$admin->getId() || !$admin->getIsActive()) {
            $this->settle($conversation);
            return ['state' => self::STATE_ERROR, 'error' => 'The administrator of the run is gone or inactive.'];
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
            (string) Mage::helper('core')->jsonEncode(['conversation_id' => (int) $conversation->getId(), 'task_id' => (int) $task->getId()]),
        );
        $request->attributes->set(self::ATTRIBUTE, true);

        $kernel = new \Maho\ApiPlatform\Kernel('prod', false);
        try {
            $response = $kernel->handle($request);
            $body = (string) $response->getContent();
            $kernel->terminate($request, $response);
            if ($response->getStatusCode() >= 400) {
                return ['state' => self::STATE_ERROR, 'error' => sprintf('HTTP %d %s', $response->getStatusCode(), mb_substr($body, 0, 500))];
            }
            $outcome = (array) Mage::helper('core')->jsonDecode($body);

            return ['state' => (string) ($outcome['state'] ?? self::STATE_ERROR), 'error' => (string) ($outcome['error'] ?? '')];
        } finally {
            $this->settle($conversation);
            $session->setUser(null);
            $session->setAcl(null);
            unset($_SERVER['MAHO_ADMIN_USER_ID'], $_SERVER['MAHO_ADMIN_USERNAME'], $_SERVER['MAHO_IS_ADMIN'], $_SERVER['MAHO_API_BRIDGE_TOKEN']);
        }
    }

    /** The conversation is open again for the panel, whatever the outcome of the run. */
    private function settle(Maho_Ai_Model_Conversation $conversation): void
    {
        $conversation->load((int) $conversation->getId());
        if ($conversation->getStatus() === Maho_Ai_Model_Conversation::STATUS_RUNNING) {
            $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ACTIVE)->save();
        }
    }
}
