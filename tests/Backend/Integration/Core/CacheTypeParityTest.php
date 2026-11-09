<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Core\Api\CacheTypeProcessor;
use Mage\Core\Api\CacheTypeProvider;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use MahoCLI\Commands\CacheDisable;
use MahoCLI\Commands\CacheEnable;
use MahoCLI\Commands\CacheFlush;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin cache grid, the API and the CLI change the cache types through Mage_Core_Service_Cache (#1348).
 * Each test does the same change through each transport and compares the outcome.
 */

const CACHE_PARITY_TYPE = 'block_html';

/**
 * Post $codes to a mass action of System > Cache Management.
 *
 * @return list<string> the messages of the admin session
 */
function cacheViaAdmin(string $action, array $codes = []): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("http://localhost/admin/cache/$action", 'POST', ['types' => $codes]),
    );
    $controller = new Mage_Adminhtml_CacheController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();

    $messages = [];
    foreach (Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
        $messages[] = $message->getText();
    }
    return $messages;
}

/**
 * Run the cache type operation $operation of the API for $code. ApiExceptionListener turns an exception into the response.
 *
 * @return array{status: int, body: array<string, mixed>}
 */
function cacheViaApi(Operation $operation, string $code = '', ?array $body = null): array
{
    $request = SymfonyRequest::create(
        "/api/rest/v2/cache-types/$code",
        $operation->getMethod() ?? 'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
    );
    $tokenStorage = new TokenStorage();
    $user = new ApiUser('admin_1', ['ROLE_ADMIN'], adminId: 1);
    $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    $container = new Container();
    $container->set('security.token_storage', $tokenStorage);
    $security = new Security($container);

    try {
        $result = new CacheTypeProcessor($security, new CacheTypeProvider($security))
            ->process(null, $operation, ['code' => $code], ['request' => $request]);
        return [
            'status' => 200,
            'body' => $result instanceof Response ? json_decode((string) $result->getContent(), true) : (array) $result,
        ];
    } catch (Throwable $e) {
        $kernel = new class implements HttpKernelInterface {
            #[\Override]
            public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $e);
        new ApiExceptionListener()->onKernelException($event);
        $response = $event->getResponse();
        return ['status' => (int) $response?->getStatusCode(), 'body' => json_decode((string) $response?->getContent(), true)];
    }
}

function cacheViaCli(Command $command): int
{
    new Application()->addCommand($command);
    return new CommandTester($command)->execute([]);
}

/**
 * @return array<string, int> the stored state of each cache type
 */
function cacheStoredOptions(): array
{
    return array_map(intval(...), Mage::getResourceSingleton('core/cache')->getAllOptions() ?: []);
}

/**
 * Store $options and put the same state in memory, as a new request reads it.
 *
 * @param array<string, int> $options
 */
function cacheRestoreOptions(array $options): void
{
    Mage::app()->saveUseCache($options);
    foreach ($options as $code => $enabled) {
        if ($enabled) {
            Mage::app()->getCache()->unbanUse($code);
        } else {
            Mage::app()->getCache()->banUse($code);
        }
    }
}

/**
 * Store $code as enabled or disabled, and keep a copy of the options in the cache, as a running store does.
 */
function cachePrepare(string $code, bool $enabled): void
{
    cacheRestoreOptions([$code => $enabled ? 1 : 0] + cacheStoredOptions());
    Mage::app()->getCache()->save(cacheStoredOptions(), Mage_Core_Model_Cache::OPTIONS_CACHE_ID);
    Mage::app()->getCache()->save('parity', 'cache_parity_entry', ['BLOCK_HTML']);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    $this->cacheOptions = cacheStoredOptions();
});

afterEach(function () {
    cacheRestoreOptions($this->cacheOptions);
});

describe('disable', function () {
    it('stores the type as disabled and removes its data on every transport', function (Closure $disable) {
        cachePrepare(CACHE_PARITY_TYPE, true);

        $disable();

        expect(cacheStoredOptions()[CACHE_PARITY_TYPE])->toBe(0)
            ->and(Mage::app()->useCache(CACHE_PARITY_TYPE))->toBeFalse()
            ->and(Mage::app()->getCache()->load('cache_parity_entry'))->toBeFalse()
            // The next request reads the options from the database, not a stale copy in the cache
            ->and(Mage::app()->getCache()->load(Mage_Core_Model_Cache::OPTIONS_CACHE_ID))->toBeFalse();
    })->with([
        'admin' => fn() => expect(cacheViaAdmin('massDisable', [CACHE_PARITY_TYPE]))->toBe(['1 cache type(s) disabled.']),
        'api' => fn() => expect(cacheViaApi(new Put(), CACHE_PARITY_TYPE, ['enabled' => false])['body']['enabled'])->toBeFalse(),
        'cli' => fn() => expect(cacheViaCli(new CacheDisable()))->toBe(Command::SUCCESS),
    ]);
});

describe('enable', function () {
    it('stores the type as enabled on every transport', function (Closure $enable) {
        cachePrepare(CACHE_PARITY_TYPE, false);

        $enable();

        expect(cacheStoredOptions()[CACHE_PARITY_TYPE])->toBe(1)
            ->and(Mage::app()->useCache(CACHE_PARITY_TYPE))->toBeTrue()
            ->and(Mage::app()->getCache()->load(Mage_Core_Model_Cache::OPTIONS_CACHE_ID))->toBeFalse();
    })->with([
        'admin' => fn() => expect(cacheViaAdmin('massEnable', [CACHE_PARITY_TYPE]))->toBe(['1 cache type(s) enabled.']),
        'api' => fn() => expect(cacheViaApi(new Put(), CACHE_PARITY_TYPE, ['enabled' => true])['body']['enabled'])->toBeTrue(),
        'cli' => fn() => expect(cacheViaCli(new CacheEnable()))->toBe(Command::SUCCESS),
    ]);

    it('keeps the types that the request turned off in memory', function () {
        cachePrepare(CACHE_PARITY_TYPE, true);
        cacheRestoreOptions(['layout' => 0] + cacheStoredOptions());
        Mage::app()->getCache()->banUse(CACHE_PARITY_TYPE);

        expect(cacheViaAdmin('massEnable', ['layout']))->toBe(['1 cache type(s) enabled.'])
            ->and(cacheStoredOptions()['layout'])->toBe(1)
            ->and(cacheStoredOptions()[CACHE_PARITY_TYPE])->toBe(1);
    });
});

describe('refresh', function () {
    it('removes the data and clears the invalidated state on the admin and the API', function (Closure $refresh) {
        cachePrepare(CACHE_PARITY_TYPE, true);
        Mage::app()->getCache()->invalidateType(CACHE_PARITY_TYPE);

        $refresh();

        expect(Mage::getService('core/cache')->isInvalidated(CACHE_PARITY_TYPE))->toBeFalse()
            ->and(Mage::app()->getCache()->load('cache_parity_entry'))->toBeFalse();
    })->with([
        'admin' => fn() => expect(cacheViaAdmin('massRefresh', [CACHE_PARITY_TYPE]))->toBe(['1 cache type(s) refreshed.']),
        'api' => fn() => expect(cacheViaApi(new Post(name: 'cache_type_refresh'), CACHE_PARITY_TYPE)['body']['invalidated'])->toBeFalse(),
    ]);
});

describe('unknown cache type', function () {
    it('refuses the same code with the same message on the admin and the API', function (string $action, Operation $operation, ?array $body) {
        $before = cacheStoredOptions();

        $admin = cacheViaAdmin($action, ['no_such_type']);
        $api = cacheViaApi($operation, 'no_such_type', $body);

        expect($api['status'])->toBe(404)
            ->and($admin)->toBe([$api['body']['message']])
            ->and($admin)->toBe(["Cache type 'no_such_type' not found"])
            ->and(cacheStoredOptions())->toBe($before);
    })->with([
        'enable' => ['massEnable', new Put(), ['enabled' => true]],
        'disable' => ['massDisable', new Put(), ['enabled' => false]],
        'refresh' => ['massRefresh', new Post(name: 'cache_type_refresh'), null],
    ]);
});

describe('flush', function () {
    it('removes the data of every type on every transport', function (Closure $flush) {
        cachePrepare(CACHE_PARITY_TYPE, true);

        $flush();

        expect(Mage::app()->getCache()->load('cache_parity_entry'))->toBeFalse();
    })->with([
        'admin' => fn() => expect(cacheViaAdmin('flushAll'))->toBe(['The cache storage has been flushed.']),
        'api' => fn() => expect(cacheViaApi(new Post(name: 'cache_type_flush_all'))['body']['success'])->toBeTrue(),
        'cli' => fn() => expect(cacheViaCli(new CacheFlush()))->toBe(Command::SUCCESS),
    ]);
});

describe('flush system', function () {
    it('cleans the cache before the schema step of migrate, and runs no script on an up-to-date store', function () {
        cachePrepare(CACHE_PARITY_TYPE, true);
        $cleanedFirst = null;

        $scripts = Mage::getService('core/cache')->flushSystem(function () use (&$cleanedFirst): void {
            $cleanedFirst = Mage::app()->getCache()->load('cache_parity_entry') === false;
        });

        expect($cleanedFirst)->toBeTrue()
            ->and($scripts)->toBe(['schema' => 0, 'data' => 0]);
    });

    it('runs the same flush from the admin button', function () {
        cachePrepare(CACHE_PARITY_TYPE, true);

        expect(cacheViaAdmin('flushSystem'))->toBe(['The Maho cache has been flushed and updates applied.'])
            ->and(Mage::app()->getCache()->load('cache_parity_entry'))->toBeFalse();
    });
});
