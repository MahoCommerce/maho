<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Index\Api\IndexProcessProcessor;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\Job\StepState;
use MahoCLI\Commands\IndexReindex;
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
 * The admin index grid, the API and the CLI change and rebuild the index processes through
 * Mage_Index_Service_Process (#1348). Each test does the same action through each transport and compares the outcome.
 */

const INDEX_PARITY_CODE = 'catalog_url';

/**
 * Post $params to an action of System > Index Management.
 *
 * @return list<string> the messages of the admin session
 */
function indexViaAdmin(string $action, array $params): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("http://localhost/admin/process/$action", 'POST', $params),
    );
    $controller = new Mage_Index_Adminhtml_ProcessController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();

    $messages = [];
    foreach (Mage::getSingleton('adminhtml/session')->getMessages(true)->getItems() as $message) {
        $messages[] = $message->getText();
    }
    return $messages;
}

/**
 * Run the index process operation $operation of the API. ApiExceptionListener turns an exception into the response.
 *
 * @return array{status: int, body: array<string, mixed>}
 */
function indexViaApi(Operation $operation, int $id, ?array $body = null): array
{
    $request = SymfonyRequest::create(
        "/api/rest/v2/index-processes/$id",
        $operation->getMethod() ?? 'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
    );
    $tokenStorage = new TokenStorage();
    $user = new ApiUser('admin_1', ['ROLE_ADMIN'], adminId: 1);
    $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    $container = new Container();
    $container->set('security.token_storage', $tokenStorage);

    try {
        $result = new IndexProcessProcessor(new Security($container))
            ->process(null, $operation, ['id' => $id], ['request' => $request]);
        return ['status' => 200, 'body' => (array) $result];
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

/**
 * @return array{status: int, output: string}
 */
function indexViaCli(string $code): array
{
    $command = new IndexReindex();
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $status = $tester->execute(['index_code' => $code]);
    return ['status' => $status, 'output' => $tester->getDisplay()];
}

function indexParityProcess(): Mage_Index_Model_Process
{
    return Mage::getModel('index/process')->load(INDEX_PARITY_CODE, 'indexer_code');
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    $this->process = indexParityProcess();
    $this->mode = $this->process->getMode();
});

afterEach(function () {
    $this->process->unlock();
    indexParityProcess()->setMode($this->mode)->save();
});

describe('mode', function () {
    it('stores the new mode on every transport', function (Closure $change) {
        indexParityProcess()->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();

        $change((int) $this->process->getId());

        expect(indexParityProcess()->getMode())->toBe(Mage_Index_Model_Process::MODE_MANUAL);
    })->with([
        'admin edit' => fn() => fn(int $id) => expect(indexViaAdmin('save', ['process' => $id, 'mode' => 'manual']))
            ->toBe(['The index has been saved.']),
        'admin grid' => fn() => fn(int $id) => expect(indexViaAdmin('massChangeMode', ['process' => [$id], 'index_mode' => 'manual']))
            ->toBe(['Total of 1 index(es) have changed index mode.']),
        'api' => fn() => fn(int $id) => expect(indexViaApi(new Put(), $id, ['mode' => 'manual'])['body']['mode'])->toBe('manual'),
    ]);

    it('refuses a mode that does not exist with the same message on the admin and the API', function () {
        $id = (int) $this->process->getId();

        $edit = indexViaAdmin('save', ['process' => $id, 'mode' => 'schedule']);
        $grid = indexViaAdmin('massChangeMode', ['process' => [$id], 'index_mode' => 'schedule']);
        $api = indexViaApi(new Put(), $id, ['mode' => 'schedule']);

        expect($api['status'])->toBe(422)
            ->and($api['body']['details']['errors'])->toBe([['field' => 'mode', 'message' => 'mode must be real_time or manual']])
            ->and($edit)->toBe(['mode must be real_time or manual'])
            ->and($grid)->toBe($edit)
            ->and(indexParityProcess()->getMode())->toBe($this->mode);
    });
});

describe('unknown process', function () {
    it('refuses an unknown process on every transport', function () {
        $api = indexViaApi(new Put(), 999999, ['mode' => 'manual']);
        $cli = indexViaCli('no_such_index');

        expect($api['status'])->toBe(404)
            ->and($api['body']['message'])->toBe('Index process 999999 not found')
            ->and(indexViaAdmin('save', ['process' => 999999, 'mode' => 'manual']))->toBe(['Cannot initialize the indexer process.'])
            ->and($cli['status'])->toBe(Command::FAILURE)
            ->and($cli['output'])->toContain('Index "no_such_index" not found');
    });
});

describe('reindex', function () {
    it('leaves an index that is working now alone on every transport', function () {
        $this->process->lock();
        $message = sprintf('Index "%s" is working now. Try again later.', $this->process->getIndexer()->getName());
        $startedAt = indexParityProcess()->getStartedAt();

        $api = indexViaApi(new Post(name: 'index_process_reindex'), (int) $this->process->getId());
        $cli = indexViaCli(INDEX_PARITY_CODE);

        // The admin runs the reindex in the background through the runner
        $queue = Mage::getService('index/process')->getQueue([(int) $this->process->getId()]);
        /** @var Mage_Index_Model_Progress $progress */
        $progress = Mage::getModel('index/progress');
        $progress->init(Mage::getModel('index/runner')->getSteps($queue));
        Mage::getModel('index/runner')->run($queue, $progress);
        $steps = array_column($progress->toArray()['steps'], 'state', 'code');

        expect($api['status'])->toBe(409)
            ->and($api['body']['message'])->toBe($message)
            ->and($cli['status'])->toBe(Command::FAILURE)
            ->and($cli['output'])->toContain($message)
            ->and($steps[INDEX_PARITY_CODE])->toBe(StepState::Skipped->value)
            ->and(indexParityProcess()->getStartedAt())->toBe($startedAt);
    });
});
