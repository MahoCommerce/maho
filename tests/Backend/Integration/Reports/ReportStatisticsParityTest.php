<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Reports\Api\ReportStatisticsProcessor;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page Reports > Refresh Statistics and the API refresh the report statistics through
 * Mage_Reports_Service_Statistics (#1348). Each test does the same refresh through both transports and compares the outcome.
 */

const STATISTICS_PARITY_CODE = 'coupons';

/**
 * Ask the admin page for a refresh of $codes.
 *
 * @return list<string> the messages of the admin session
 */
function statisticsViaAdmin(string $action, string $codes): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("http://localhost/admin/report_statistics/$action", 'GET', ['code' => $codes]),
    );
    $controller = new Mage_Adminhtml_Report_StatisticsController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();

    $messages = [];
    foreach (Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
        $messages[] = $message->getText();
    }
    return $messages;
}

/**
 * Post $body to POST /reports/statistics/refresh as the admin user $adminId. ApiExceptionListener turns an exception into the response.
 *
 * @return array{status: int, body: array<string, mixed>, retry_after: ?string}
 */
function statisticsViaApi(array $body, int $adminId): array
{
    $request = SymfonyRequest::create(
        '/api/rest/v2/reports/statistics/refresh',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode($body, JSON_THROW_ON_ERROR),
    );
    $tokenStorage = new TokenStorage();
    // The identifier that AdminSessionAuthenticator gives to the admin session
    $user = new ApiUser('admin_' . $adminId, ['ROLE_ADMIN'], adminId: $adminId);
    $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    $container = new Container();
    $container->set('security.token_storage', $tokenStorage);

    try {
        $response = new ReportStatisticsProcessor(new Security($container))
            ->process(null, new Post(name: 'report_statistics_refresh'), [], ['request' => $request]);
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
    }

    return [
        'status' => (int) $response?->getStatusCode(),
        'body' => json_decode((string) $response?->getContent(), true),
        'retry_after' => $response?->headers->get('Retry-After'),
    ];
}

function statisticsTakeQueued(): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('queue/message');
    $dedupeKey = new Mage_Reports_Model_Queue_RefreshStatistics(STATISTICS_PARITY_CODE)->getDedupeKey();

    $count = (int) $adapter->fetchOne($adapter->select()->from($table, ['COUNT(*)'])->where('dedupe_key = ?', $dedupeKey));
    $adapter->delete($table, ['dedupe_key = ?' => $dedupeKey]);
    return $count;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    $this->user = AdminRoleFixture::login(['report/statistics']);
    Mage::app()->getStore()->setConfig(Mage_Reports_Service_Statistics::XML_PATH_RATE_LIMIT, '10');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    statisticsTakeQueued();
});

afterEach(function () {
    statisticsTakeQueued();
    AdminRoleFixture::delete($this->user);
});

it('refreshes the recent statistics on both transports', function () {
    $admin = statisticsViaAdmin('refreshRecent', STATISTICS_PARITY_CODE);
    $api = statisticsViaApi(['mode' => 'recent', 'reports' => [STATISTICS_PARITY_CODE]], (int) $this->user->getId());

    expect($admin)->toBe(['Recent statistics have been updated.'])
        ->and($api['status'])->toBe(200)
        ->and($api['body']['reports'])->toBe([STATISTICS_PARITY_CODE]);
});

it('queues a lifetime refresh once across both transports', function () {
    $admin = statisticsViaAdmin('refreshLifetime', STATISTICS_PARITY_CODE);
    $api = statisticsViaApi(['mode' => 'lifetime', 'reports' => [STATISTICS_PARITY_CODE]], (int) $this->user->getId());

    expect($admin)->toHaveCount(1)
        ->and($api['status'])->toBe(202)
        ->and($api['body']['alreadyQueued'])->toBe([STATISTICS_PARITY_CODE])
        ->and(statisticsTakeQueued())->toBe(1);
});

it('refuses an unknown report code with the same message on both transports', function (string $action, string $mode) {
    $admin = statisticsViaAdmin($action, 'nope');
    $api = statisticsViaApi(['mode' => $mode, 'reports' => ['nope']], (int) $this->user->getId());

    expect($api['status'])->toBe(422)
        ->and($api['body']['details']['errors'][0]['field'])->toBe('reports')
        ->and($admin)->toBe([$api['body']['message']])
        ->and(statisticsTakeQueued())->toBe(0);
})->with([
    'recent' => ['refreshRecent', 'recent'],
    'lifetime' => ['refreshLifetime', 'lifetime'],
]);

it('shares the rate limit of one admin user between both transports', function () {
    Mage::app()->getStore()->setConfig(Mage_Reports_Service_Statistics::XML_PATH_RATE_LIMIT, '1');

    expect(statisticsViaAdmin('refreshLifetime', STATISTICS_PARITY_CODE))->toHaveCount(1);

    $api = statisticsViaApi(['mode' => 'lifetime', 'reports' => [STATISTICS_PARITY_CODE]], (int) $this->user->getId());
    expect($api['status'])->toBe(429)
        ->and($api['retry_after'])->toBe('60')
        ->and($api['body']['message'])->toBe('Too many requests. Please try again later.')
        ->and(statisticsViaAdmin('refreshRecent', STATISTICS_PARITY_CODE))->toBe([$api['body']['message']]);
});
