<?php

/**
 * Drive an admin controller action and an API processor in the same process, and read their outcome.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

final class AdminApiParity
{
    /**
     * Return the security of an admin token. With $allowedStoreIds, the token sees only these store views.
     *
     * @param list<int>|null $allowedStoreIds
     */
    public static function adminSecurity(?array $allowedStoreIds = null): Security
    {
        $user = new ApiUser('admin', ['ROLE_ADMIN'], adminId: 1, allowedStoreIds: $allowedStoreIds);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

        return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
            public function __construct(private TokenStorage $storage) {}

            #[\Override]
            public function get(string $id): mixed
            {
                return $id === 'security.authorization_checker'
                    ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                    : $this->storage;
            }

            #[\Override]
            public function has(string $id): bool
            {
                return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
            }
        });
    }

    /**
     * Return a JSON request for $method $path with $body.
     *
     * @param array<string, mixed> $body
     */
    public static function jsonRequest(string $method, string $path, array $body = []): SymfonyRequest
    {
        return SymfonyRequest::create(
            $path,
            $method,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Run $call, a processor call. ApiExceptionListener turns an exception into the response, as in the API kernel.
     *
     * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
     */
    public static function api(SymfonyRequest $request, callable $call): array
    {
        try {
            return ['status' => 200, 'result' => $call(), 'errors' => [], 'fields' => []];
        } catch (\Throwable $e) {
            $kernel = new class implements HttpKernelInterface {
                #[\Override]
                public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            };
            $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $e);
            new ApiExceptionListener()->onKernelException($event);
            $body = json_decode((string) $event->getResponse()?->getContent(), true);
            $details = $body['details']['errors'] ?? [];

            return [
                'status' => (int) $event->getResponse()?->getStatusCode(),
                'result' => null,
                'errors' => $details !== [] ? array_column($details, 'message') : [(string) ($body['message'] ?? '')],
                'fields' => array_column($details, 'field'),
            ];
        }
    }

    /**
     * Post $fields to the action $action of the admin controller $controllerClass, as the admin form does.
     * Calling the action skips preDispatch(), so the ACL and the form key are not checked.
     *
     * @param class-string<\Mage_Adminhtml_Controller_Action> $controllerClass
     * @param array<string, mixed> $fields
     * @return array{success: list<string>, errors: list<string>}
     */
    public static function admin(string $controllerClass, string $action, array $fields = []): array
    {
        [$route, $controller] = ['adminhtml', strtolower(substr($controllerClass, strlen('Mage_Adminhtml_'), -strlen('Controller')))];
        $request = new \Mage_Core_Controller_Request_Http(
            SymfonyRequest::create("http://localhost/admin/{$controller}/{$action}", 'POST', $fields),
        );
        $request->setRouteName($route)->setControllerName($controller)->setActionName($action)->setDispatched(true);
        \Mage::app()->setRequest($request);
        // A message that an earlier test left in the session is not an outcome of this action
        self::adminMessages();

        try {
            new $controllerClass($request, new \Mage_Core_Controller_Response_Http())->{$action . 'Action'}();
        } catch (\Throwable) {
            // The trailing redirect builds an admin url outside a dispatched request. The messages are the outcome
        }
        return self::adminMessages();
    }

    /**
     * Return the messages that the last admin action left in the admin session, and clear them.
     *
     * @return array{success: list<string>, errors: list<string>}
     */
    public static function adminMessages(): array
    {
        $messages = ['success' => [], 'errors' => []];
        foreach (\Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
            $messages[$message->getType() === \Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
        }
        return $messages;
    }
}
