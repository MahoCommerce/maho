<?php

/**
 * Streaming endpoints of the admin assistant, authenticated by the admin session cookie.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Chat;

use Mage_Admin_Model_User;
use Maho\ApiPlatform\Security\SameOriginGuard;
use Maho\ApiPlatform\Service\StoreContext;
use Maho_Ai_Model_Chat_SseWriter;
use Maho_Ai_Model_Conversation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lives in the API kernel because the tool calls need its security token and request. The
 * `api_admin` firewall authenticates the admin cookie; this class adds what the firewall does
 * not know: the module toggle, the `system/ai/chat` ACL, the admin form key and a
 * same-origin check against cross-site requests.
 */
#[AsController]
final class ChatController
{
    public function __construct(
        private readonly ChatService $service,
        private readonly RequestStack $requestStack,
    ) {}

    #[Route('/api/admin/ai/chat', name: 'api_admin_ai_chat', methods: ['POST'])]
    public function send(Request $request): Response
    {
        $input = $this->guard($request);
        $admin = $this->admin();

        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            throw new BadRequestHttpException('The message is empty.');
        }
        if (mb_strlen($message) > 20000) {
            throw new BadRequestHttpException('The message is too long.');
        }

        $context = $this->context($input);
        $conversation = $this->conversation($input, $admin, $context, create: true);

        return $this->stream($request, $conversation, function (Maho_Ai_Model_Chat_SseWriter $sse) use ($conversation, $admin, $message, $context): void {
            $this->service->startTurn($conversation, $admin, $message, $context, $sse);
        });
    }

    #[Route('/api/admin/ai/chat/confirm', name: 'api_admin_ai_chat_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $input = $this->guard($request);
        $admin = $this->admin();
        $context = $this->context($input);
        $conversation = $this->conversation($input, $admin, $context, create: false);

        $decisions = [];
        foreach ((array) ($input['decisions'] ?? []) as $id => $approved) {
            $decisions[(string) $id] = $approved === true || $approved === 1 || $approved === '1' || $approved === 'true';
        }

        return $this->stream($request, $conversation, function (Maho_Ai_Model_Chat_SseWriter $sse) use ($conversation, $admin, $decisions, $context): void {
            $this->service->resumeTurn($conversation, $admin, $decisions, $context, $sse);
        });
    }

    /**
     * @return array<string, mixed> the decoded JSON body
     */
    private function guard(Request $request): array
    {
        if (!\Mage::helper('ai')->isChatEnabled()) {
            throw new NotFoundHttpException('The admin assistant is disabled.');
        }
        SameOriginGuard::assert($request);
        if (!\Mage::helper('ai')->isChatAllowed()) {
            throw new AccessDeniedHttpException('Your admin role does not grant access to the assistant.');
        }

        try {
            $input = (array) \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]');
        } catch (\JsonException) {
            throw new BadRequestHttpException('The request body is not valid JSON.');
        }

        if (!\Mage::getSingleton('core/session')->validateFormKey((string) ($input['form_key'] ?? ''))) {
            throw new AccessDeniedHttpException('Invalid form key.');
        }

        // The storefront's store cookie would make this request run in that store view; an
        // admin request runs in the admin scope, as every admin controller does.
        \Mage::app()->setCurrentStore(\Mage_Core_Model_Store::ADMIN_CODE);
        StoreContext::ensureStore();

        return $input;
    }

    private function admin(): Mage_Admin_Model_User
    {
        $admin = \Mage::getSingleton('admin/session')->getUser();
        if (!$admin instanceof Mage_Admin_Model_User || !$admin->getId()) {
            throw new AccessDeniedHttpException('Admin session required.');
        }

        return $admin;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{route: string, entity_type: string, entity_id: int|string|null, entity_label: string, store: string, screen: string, editor_guide: string}
     */
    private function context(array $input): array
    {
        $raw = is_array($input['context'] ?? null) ? $input['context'] : [];
        $id = $raw['entity_id'] ?? null;

        return [
            'route' => mb_substr(trim((string) ($raw['route'] ?? '')), 0, 128),
            'entity_type' => mb_substr(trim((string) ($raw['entity_type'] ?? '')), 0, 32),
            'entity_id' => is_scalar($id) && (string) $id !== '' ? (ctype_digit((string) $id) ? (int) $id : mb_substr((string) $id, 0, 64)) : null,
            'entity_label' => mb_substr(trim((string) ($raw['entity_label'] ?? '')), 0, 200),
            'store' => mb_substr(trim((string) ($raw['store'] ?? '')), 0, 32),
            'screen' => mb_substr(trim((string) preg_replace('/[^\P{C}\n]+/u', ' ', (string) ($raw['screen'] ?? ''))), 0, 4000),
            'editor_guide' => mb_substr(trim((string) preg_replace('/[^\P{C}\n]+/u', ' ', (string) ($raw['editor_guide'] ?? ''))), 0, 40000),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array{route: string, entity_type: string, entity_id: int|string|null, entity_label: string, store: string, screen: string, editor_guide: string} $context
     */
    private function conversation(array $input, Mage_Admin_Model_User $admin, array $context, bool $create): Maho_Ai_Model_Conversation
    {
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = \Mage::getModel('ai/conversation');
        $id = (int) ($input['conversation_id'] ?? 0);
        if ($id > 0) {
            $conversation->load($id);
            if (!$conversation->isOwnedBy((int) $admin->getId())) {
                throw new NotFoundHttpException('Conversation not found.');
            }
            return $conversation;
        }
        if (!$create) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $conversation->setAdminUserId((int) $admin->getId());
        $conversation->setStoreId((int) ($_SERVER['MAHO_STORE_ID'] ?? 0));
        $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ACTIVE);
        $conversation->setContextRoute($context['route'] !== '' ? $context['route'] : null);
        $conversation->setContextEntityType($context['entity_type'] !== '' ? $context['entity_type'] : null);
        $conversation->setContextEntityId(is_int($context['entity_id']) ? $context['entity_id'] : null);
        $conversation->save();

        return $conversation;
    }

    /**
     * @param \Closure(Maho_Ai_Model_Chat_SseWriter): void $turn
     */
    private function stream(Request $request, Maho_Ai_Model_Conversation $conversation, \Closure $turn): StreamedResponse
    {
        if (!$conversation->acquireLock()) {
            throw new ConflictHttpException('The assistant is still answering in this conversation.');
        }

        return new StreamedResponse(function () use ($request, $conversation, $turn): void {
            // The kernel popped the request when it returned this response, and the MCP
            // handler reads the current request to build each tool call.
            $this->requestStack->push($request);
            $sse = new Maho_Ai_Model_Chat_SseWriter();
            try {
                $sse->open();
                $turn($sse);
            } finally {
                $conversation->releaseLock();
                $this->requestStack->pop();
            }
        }, Response::HTTP_OK, Maho_Ai_Model_Chat_SseWriter::headers());
    }
}
