<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Maho\Job\StepState;

uses(Tests\MahoBackendTestCase::class);

function makeProgress(array $steps = []): Mage_Index_Model_Progress
{
    /** @var Mage_Index_Model_Progress $progress */
    $progress = Mage::getModel('index/progress');
    return $progress->init($steps ?: [
        ['code' => 'first', 'name' => 'First Index'],
        ['code' => 'second', 'name' => 'Second Index'],
    ]);
}

afterEach(function () {
    foreach (glob(Mage::getBaseDir('var') . DS . Mage_Index_Model_Progress::VAR_SUBDIR . DS . '*.json') ?: [] as $file) {
        @unlink($file);
    }
});

it('seeds every step as queued', function () {
    $record = makeProgress()->toArray();

    expect($record['finished'])->toBeFalse()
        ->and($record['steps'])->toHaveCount(2)
        ->and(array_column($record['steps'], 'state'))
        ->toBe([StepState::Queued->value, StepState::Queued->value]);
});

it('records the lifecycle of a step on disk', function () {
    $progress = makeProgress();
    $progress->startStep('first');

    $reader = Mage::getModel('index/progress')->setToken($progress->getToken());
    expect($reader->read()['steps'][0]['state'])->toBe(StepState::Running->value);

    $progress->finishStep('first', 1.239);
    $step = $reader->read()['steps'][0];

    expect($step['state'])->toBe(StepState::Success->value)
        ->and($step['duration'])->toBe(1.24);
});

it('keeps the failure message with the failed step', function () {
    $progress = makeProgress();
    $progress->failStep('second', 'Table is gone', 1.0);

    $steps = Mage::getModel('index/progress')->setToken($progress->getToken())->read()['steps'];

    expect($steps[1]['state'])->toBe(StepState::Error->value)
        ->and($steps[1]['message'])->toBe('Table is gone')
        ->and($steps[0]['state'])->toBe(StepState::Queued->value);
});

it('marks the run finished', function () {
    $progress = makeProgress();
    $progress->finish();

    expect(Mage::getModel('index/progress')->setToken($progress->getToken())->read()['finished'])->toBeTrue();
});

it('returns an empty record for an unknown run', function () {
    $unknown = Mage::getModel('index/progress')->setToken(Mage_Index_Model_Progress::generateToken());

    expect($unknown->read())->toBe([]);
});

it('rejects a token that is not one we generated', function (string $token) {
    Mage::getModel('index/progress')->setToken($token);
})->with([
    '../../../app/etc/local',
    'not-hex-at-all',
    '',
    'ABCDEF0123456789abcdef0123456789',
])->throws(Mage_Core_Exception::class);

it('removes only stale records', function () {
    $stale = makeProgress();
    $fresh = makeProgress();
    touch($stale->getFilePath(), time() - 7200);

    expect(Mage_Index_Model_Progress::cleanupStale(3600))->toBe(1)
        ->and(file_exists($stale->getFilePath()))->toBeFalse()
        ->and(file_exists($fresh->getFilePath()))->toBeTrue();
});
