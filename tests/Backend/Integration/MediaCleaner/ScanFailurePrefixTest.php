<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Exception\DriverException;

uses(Tests\MahoBackendTestCase::class);

function mediaScanFailureException(string $message): DriverException
{
    $driver = new class ($message, 22001) extends RuntimeException implements Doctrine\DBAL\Driver\Exception {
        #[\Override]
        public function getSQLState(): ?string
        {
            return '22001';
        }
    };
    return new DriverException($driver, null);
}

beforeEach(function (): void {
    $this->db = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->table = Mage::getSingleton('core/resource')->getTableName('mediacleaner/image');
    $suffix = bin2hex(random_bytes(6));
    $this->scanType = 'test_failure_' . $suffix;
    $this->otherType = 'test_control_' . $suffix;
    $this->db->insert($this->table, ['type' => $this->otherType, 'path' => 'control.jpg']);
    $this->paths = array_map(static fn(int $index): string => sprintf('path_%03d.jpg', $index), range(0, 200));
    $this->rows = fn(): array => $this->db->fetchCol($this->db->select()
        ->from($this->table, 'path')->where('type = ?', $this->scanType)->order('image_id'));
    $this->scanner = new class extends Maho_MediaCleaner_Model_Scanner {
        public function persist(string $type, array $paths): void
        {
            $this->storeUnusedImages($type, $paths);
        }
    };
    $this->hook = new class ($this->table) {
        public mixed $previous = null;
        public ?string $badPath = null;
        public bool $rejectBatchOnce = false;
        public int $batches = 0;
        public array $singlePaths = [];
        public Throwable $batchError;
        public DriverException $rowError;

        public function __construct(private readonly string $table)
        {
            $this->batchError = mediaScanFailureException('Rejected scan batch');
            $this->rowError = mediaScanFailureException('Rejected scan row');
        }

        public function record(string $sql, array $bind): void
        {
            if ($this->previous) {
                $this->previous['object']->{$this->previous['method']}($sql, $bind);
            }
            if (!str_starts_with($sql, 'INSERT') || !str_contains($sql, $this->table)) {
                return;
            }
            $single = count($bind) === 2;
            if ($single) {
                $this->singlePaths[] = array_values($bind)[1];
            } else {
                $this->batches++;
            }
            // Inject the driver exception before execution; all successful SQL uses the real adapter.
            if (!$single && $this->rejectBatchOnce && $this->batches === 1) {
                throw $this->batchError;
            }
            if ($this->badPath !== null && in_array($this->badPath, $bind, true)) {
                throw $single ? $this->rowError : $this->batchError;
            }
        }
    };
    $this->persist = function (): ?Throwable {
        $this->hook->previous = $this->db->setQueryHook(['object' => $this->hook, 'method' => 'record']);
        try {
            $this->scanner->persist($this->scanType, $this->paths);
            return null;
        } catch (Throwable $error) {
            return $error;
        } finally {
            $this->db->setQueryHook($this->hook->previous);
        }
    };
});

afterEach(function (): void {
    $this->db->delete($this->table, ['type IN (?)' => [$this->scanType, $this->otherType]]);
});

it('retains the valid prefix and stops at the first rejected row outside transactions', function (int $badIndex): void {
    $this->hook->badPath = $this->paths[$badIndex];
    $error = ($this->persist)();
    $batchStart = intdiv($badIndex, 100) * 100;

    expect(($this->rows)())->toBe(array_slice($this->paths, 0, $badIndex))
        ->and($error)->toBe($this->hook->rowError)
        ->and($this->hook->singlePaths)->toBe(array_slice($this->paths, $batchStart, $badIndex - $batchStart + 1))
        ->and($this->db->fetchCol($this->db->select()->from($this->table, 'path')->where('type = ?', $this->otherType)))
        ->toBe(['control.jpg']);
})->with([6, 149]);

it('propagates the original batch error when every row retry succeeds', function (): void {
    $this->hook->rejectBatchOnce = true;
    $error = ($this->persist)();

    expect($error)->toBe($this->hook->batchError)
        ->and(($this->rows)())->toBe(array_slice($this->paths, 0, 100))
        ->and($this->hook->batches)->toBe(1)
        ->and($this->hook->singlePaths)->toBe(array_slice($this->paths, 0, 100));
});

it('leaves an external transaction and the batch exception to its caller', function (string $owner): void {
    $connection = $this->db->getConnection();
    $pdo = $connection->getNativeConnection();
    if ($owner === 'adapter') {
        $this->db->beginTransaction();
    } elseif ($owner === 'dbal') {
        $connection->beginTransaction();
    } else {
        $pdo->beginTransaction();
    }
    $this->hook->badPath = $this->paths[149];
    try {
        $error = ($this->persist)();
        expect($error)->toBe($this->hook->batchError)
            ->and($this->hook->singlePaths)->toBe([])
            ->and(($this->rows)())->toBe(array_slice($this->paths, 0, 100))
            ->and($this->db->getTransactionLevel())->toBe($owner === 'adapter' ? 1 : 0)
            ->and($connection->isTransactionActive())->toBe($owner !== 'pdo')
            ->and($pdo->inTransaction())->toBeTrue();
    } finally {
        if ($owner === 'adapter') {
            $this->db->rollBack();
        } elseif ($owner === 'dbal') {
            $connection->rollBack();
        } else {
            $pdo->rollBack();
        }
    }
})->with(['adapter', 'dbal', 'pdo']);

it('does not retry a non-driver exception', function (): void {
    $this->hook->batchError = new RuntimeException('Observer error');
    $this->hook->rejectBatchOnce = true;
    expect(($this->persist)())->toBe($this->hook->batchError)
        ->and($this->hook->singlePaths)->toBe([])
        ->and(($this->rows)())->toBe([]);
});
