<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Maho\Job\StepState;

uses(Tests\MahoBackendTestCase::class);

function detectInterruptedRun(array $record): array
{
    $controller = (new ReflectionClass(Mage_Index_Adminhtml_ProcessController::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controller, '_detectInterruptedRun');

    return $method->invoke($controller, $record);
}

function interruptedRunRecord(int $age): array
{
    return [
        'token' => Mage_Index_Model_Progress::generateToken(),
        'started_at' => time() - $age,
        'updated_at' => time() - $age,
        'finished' => false,
        'steps' => [
            ['code' => 'no_such_index', 'name' => 'First', 'state' => StepState::Success->value, 'duration' => 1.0, 'message' => null],
            ['code' => 'no_such_index_2', 'name' => 'Second', 'state' => StepState::Running->value, 'duration' => null, 'message' => null],
            ['code' => 'no_such_index_3', 'name' => 'Third', 'state' => StepState::Queued->value, 'duration' => null, 'message' => null],
        ],
    ];
}

it('leaves a record that was updated recently alone', function () {
    $record = detectInterruptedRun(interruptedRunRecord(5));

    expect($record['finished'])->toBeFalse()
        ->and($record['steps'][1]['state'])->toBe(StepState::Running->value);
});

it('reports a stale record as interrupted', function () {
    $record = detectInterruptedRun(interruptedRunRecord(Mage_Index_Model_Progress::STALE_AFTER + 60));

    expect($record['finished'])->toBeTrue()
        ->and($record['steps'][0]['state'])->toBe(StepState::Success->value)
        ->and($record['steps'][1]['state'])->toBe(StepState::Error->value)
        ->and($record['steps'][1]['message'])->not->toBeEmpty()
        ->and($record['steps'][2]['state'])->toBe(StepState::Skipped->value);
});

it('leaves a stale record alone while a worker still holds the step lock', function () {
    $record = interruptedRunRecord(Mage_Index_Model_Progress::STALE_AFTER + 60);
    $lockName = Mage_Index_Model_Runner::lockName($record['token'], $record['steps'][1]['code']);

    /** @var Mage_Core_Model_Lock $lock */
    $lock = Mage::getSingleton('core/lock');
    expect($lock->acquire($lockName))->toBeTrue();

    try {
        expect(detectInterruptedRun($record)['steps'][1]['state'])->toBe(StepState::Running->value);
    } finally {
        $lock->release($lockName);
    }
});

it('finishes a stale record that has nothing left to run', function () {
    $record = interruptedRunRecord(Mage_Index_Model_Progress::STALE_AFTER + 60);
    $record['steps'] = [$record['steps'][0]];

    expect(detectInterruptedRun($record)['finished'])->toBeTrue();
});
