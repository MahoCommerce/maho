<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

uses(Tests\MahoBackendTestCase::class);

/**
 * Pins the 401/403 semantics of the trait itself: the HTTP layer
 * (ApiExceptionListener) normalizes statuses for anonymous callers, so only
 * an in-process assertion can prove the right exception leaves the source.
 */
function authTraitHarness(?ApiUser $user): object
{
    $storage = new TokenStorage();
    if ($user !== null) {
        $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }

    $container = new class ($storage) implements \Psr\Container\ContainerInterface {
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
    };

    return new class (new Security($container)) {
        use AuthenticationTrait;

        public function __construct(?Security $security)
        {
            $this->security = $security;
        }

        public function callRequireUser(): ApiUser
        {
            return $this->requireUser();
        }

        public function callRequireCustomerId(): int
        {
            return $this->requireCustomerId();
        }
    };
}

describe('requireUser()', function () {
    it('throws 401 when no principal is present', function () {
        expect(fn() => authTraitHarness(null)->callRequireUser())
            ->toThrow(UnauthorizedHttpException::class);
    });

    it('returns the authenticated principal', function () {
        $user = new ApiUser('customer', ['ROLE_CUSTOMER'], 7);

        expect(authTraitHarness($user)->callRequireUser())->toBe($user);
    });
});

describe('requireCustomerId()', function () {
    it('throws 401 for an anonymous caller', function () {
        expect(fn() => authTraitHarness(null)->callRequireCustomerId())
            ->toThrow(UnauthorizedHttpException::class);
    });

    it('throws 403 for an authenticated admin principal', function () {
        $admin = new ApiUser('admin', ['ROLE_ADMIN'], null, 1);

        expect(fn() => authTraitHarness($admin)->callRequireCustomerId())
            ->toThrow(AccessDeniedHttpException::class);
    });

    it('throws 403 for an authenticated service-token principal', function () {
        $service = new ApiUser('service', [], null, null, 1, ['wishlists/read']);

        expect(fn() => authTraitHarness($service)->callRequireCustomerId())
            ->toThrow(AccessDeniedHttpException::class);
    });

    it('returns the customer id for a customer principal', function () {
        $customer = new ApiUser('customer', ['ROLE_CUSTOMER'], 42);

        expect(authTraitHarness($customer)->callRequireCustomerId())->toBe(42);
    });
});
