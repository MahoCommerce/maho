<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Default-deny listener for API Platform operations.
 *
 * If an API Platform operation does not declare an explicit `security` attribute,
 * this listener requires the request to be fully authenticated. This prevents
 * third-party modules from accidentally exposing public endpoints by forgetting
 * to add a security attribute.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class DefaultDenyListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_api_operation')) {
            return;
        }

        $operation = $request->attributes->get('_api_operation');
        if (!$operation instanceof HttpOperation) {
            return;
        }

        $security = $operation->getSecurity();
        if ($security !== null && $security !== '') {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null || !$token->getUser()) {
            $event->setResponse(new JsonResponse([
                'error' => 'unauthorized',
                'message' => 'Authentication required',
            ], 401));
        }
    }
}
