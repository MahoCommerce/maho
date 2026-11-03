<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Drive the admin controllers and the API classes of one flow in the same process, so that a test compares
 * the outcome of each transport (#1348).
 */
final class TransportParity
{
    /**
     * A Security that holds an admin API token, for the constructor of a provider or a processor.
     */
    public static function adminSecurity(): Security
    {
        $storage = new TokenStorage();
        $user = new ApiUser('admin', ['ROLE_ADMIN'], null, 1);
        $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

        return new Security(new class ($storage) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly TokenStorage $storage) {}

            #[\Override]
            public function get(string $id): mixed
            {
                return $this->storage;
            }

            #[\Override]
            public function has(string $id): bool
            {
                return $id === 'security.token_storage';
            }
        });
    }

    /**
     * Run $call, an API provider or processor call. ApiExceptionListener turns an exception into the response.
     *
     * @param callable(): mixed $call
     * @return array{status: int, message: string, fields: list<string>, result: mixed}
     */
    public static function api(callable $call): array
    {
        try {
            return ['status' => 200, 'message' => '', 'fields' => [], 'result' => $call()];
        } catch (\Throwable $e) {
            $kernel = new class implements HttpKernelInterface {
                #[\Override]
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            };
            $event = new ExceptionEvent($kernel, Request::create('/api/rest/v2/'), HttpKernelInterface::MAIN_REQUEST, $e);
            new ApiExceptionListener()->onKernelException($event);
            $body = json_decode((string) $event->getResponse()?->getContent(), true);

            return [
                'status' => (int) $event->getResponse()?->getStatusCode(),
                'message' => (string) ($body['message'] ?? ''),
                'fields' => array_column($body['details']['errors'] ?? [], 'field'),
                'result' => null,
            ];
        }
    }

    /**
     * Call $action of an admin controller with $params as the request parameters.
     *
     * @param class-string<\Mage_Adminhtml_Controller_Action> $controllerClass
     * @param array<string, mixed> $params
     * @return array{success: list<string>, errors: list<string>}
     */
    public static function admin(string $controllerClass, string $action, array $params): array
    {
        $request = new \Mage_Core_Controller_Request_Http();
        $request->setPost($params);
        foreach ($params as $name => $value) {
            $request->setParam($name, $value);
        }

        new $controllerClass($request, new \Mage_Core_Controller_Response_Http())->{$action . 'Action'}();

        $messages = ['success' => [], 'errors' => []];
        foreach (\Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
            $messages[$message->getType() === \Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
        }
        return $messages;
    }
}
