<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Maho\Job\StepState;

uses(Tests\MahoBackendTestCase::class);

/**
 * Stands in for a real index so run() can be driven without rebuilding anything.
 */
class RunnerFakeProcess extends Mage_Index_Model_Process
{
    public bool $ran = false;

    public bool $locked = false;

    public ?Throwable $failWith = null;

    #[\Override]
    public function isLocked()
    {
        return $this->locked;
    }

    #[\Override]
    public function reindexEverything()
    {
        $this->ran = true;
        if ($this->failWith) {
            throw $this->failWith;
        }
        return $this;
    }
}

function fakeProcess(string $code, ?Throwable $failWith = null): RunnerFakeProcess
{
    $process = new RunnerFakeProcess();
    $process->setData('indexer_code', $code);
    $process->failWith = $failWith;
    return $process;
}

function runnerProgress(array $codes): Mage_Index_Model_Progress
{
    $steps = array_map(fn(string $code): array => ['code' => $code, 'name' => ucfirst($code)], $codes);

    /** @var Mage_Index_Model_Progress $progress */
    $progress = Mage::getModel('index/progress');
    return $progress->init($steps);
}

afterEach(function () {
    foreach (glob(Mage::getBaseDir('var') . DS . Mage_Index_Model_Progress::VAR_SUBDIR . DS . '*.json') ?: [] as $file) {
        @unlink($file);
    }
});

it('queues every visible index when no ids are given', function () {
    $queue = Mage::getModel('index/runner')->buildQueue();

    $visible = 0;
    foreach (Mage::getSingleton('index/indexer')->getProcessesCollection() as $process) {
        if ($process->getIndexer()->isVisible()) {
            $visible++;
        }
    }

    expect($queue)->toHaveCount($visible)->and($visible)->toBeGreaterThan(0);
});

it('puts dependencies before the index that needs them', function () {
    $queue = Mage::getModel('index/runner')->buildQueue();

    $seen = [];
    foreach ($queue as $process) {
        foreach ($process->getDepends() as $code) {
            $dependency = Mage::getSingleton('index/indexer')->getProcessByCode($code);
            if ($dependency && $dependency->getIndexer()->isVisible()) {
                expect($seen)->toContain($code);
            }
        }
        $seen[] = $process->getIndexerCode();
    }
});

it('de-duplicates repeated ids', function () {
    $runner = Mage::getModel('index/runner');
    $first = $runner->buildQueue()[0];

    expect($runner->buildQueue([$first->getId(), $first->getId()]))
        ->toHaveCount(count($runner->buildQueue([$first->getId()])));
});

it('ignores ids that match no index', function () {
    expect(Mage::getModel('index/runner')->buildQueue([999999]))->toBe([]);
});

it('records a failing index and keeps going', function () {
    $alpha = fakeProcess('alpha');
    $beta = fakeProcess('beta', new RuntimeException('boom'));
    $gamma = fakeProcess('gamma');

    $progress = runnerProgress(['alpha', 'beta', 'gamma']);
    Mage::getModel('index/runner')->run([$alpha, $beta, $gamma], $progress);

    $record = $progress->toArray();
    $states = array_column($record['steps'], 'state');

    expect($states)->toBe([StepState::Success->value, StepState::Error->value, StepState::Success->value])
        ->and($record['steps'][1]['message'])->not->toContain('boom')
        ->and($record['finished'])->toBeTrue()
        ->and($gamma->ran)->toBeTrue();
});

it('shows a user-facing message but hides the detail of anything else', function () {
    $userFacing = fakeProcess('alpha', new Mage_Core_Exception('Rebuild the URL rewrites first'));
    $internal = fakeProcess('beta', new RuntimeException('SQLSTATE[42S02]: catalog_product_entity'));

    $progress = runnerProgress(['alpha', 'beta']);
    Mage::getModel('index/runner')->run([$userFacing, $internal], $progress);

    $steps = $progress->toArray()['steps'];

    expect($steps[0]['message'])->toBe('Rebuild the URL rewrites first')
        ->and($steps[1]['message'])->not->toContain('SQLSTATE');
});

it('skips an index that is already running', function () {
    $locked = fakeProcess('alpha');
    $locked->locked = true;

    $progress = runnerProgress(['alpha']);
    Mage::getModel('index/runner')->run([$locked], $progress);

    expect($progress->toArray()['steps'][0]['state'])->toBe(StepState::Skipped->value)
        ->and($locked->ran)->toBeFalse()
        // Otherwise a dependent step further down the queue would rebuild it unreported
        ->and($locked->getData('runed_reindexall'))->toBeTrue();
});

it('queues the objects that resolve dependencies, so a dependency runs once', function () {
    $indexer = Mage::getSingleton('index/indexer');

    foreach (Mage::getModel('index/runner')->buildQueue() as $process) {
        expect($process)->toBe($indexer->getProcessByCode($process->getIndexerCode()));
    }
});

it('reindexes the search index after the stock index', function () {
    $codes = array_map(
        fn(Mage_Index_Model_Process $process): string => $process->getIndexerCode(),
        Mage::getModel('index/runner')->buildQueue(),
    );

    expect(array_search('cataloginventory_stock', $codes, true))
        ->toBeLessThan(array_search('catalogsearch_fulltext', $codes, true));
});

it('puts the processes that depend on each other in one lane, in queue order', function () {
    $stock = fakeProcess('stock')->setData('depends', []);
    $price = fakeProcess('price')->setData('depends', ['stock']);
    $url = fakeProcess('url')->setData('depends', []);
    $search = fakeProcess('search')->setData('depends', ['stock', 'missing']);

    $lanes = Mage::getModel('index/runner')->buildLanes([$stock, $price, $url, $search]);

    expect($lanes)->toBe([[$stock, $price, $search], [$url]]);
});

it('joins two lanes when a process depends on both', function () {
    $a = fakeProcess('a')->setData('depends', []);
    $b = fakeProcess('b')->setData('depends', []);
    $c = fakeProcess('c')->setData('depends', ['a', 'b']);

    expect(Mage::getModel('index/runner')->buildLanes([$a, $b, $c]))->toBe([[$a, $b, $c]]);
});
