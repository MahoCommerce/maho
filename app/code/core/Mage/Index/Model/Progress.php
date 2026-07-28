<?php

/**
 * Per-run progress record for a backgrounded reindex.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

use Maho\Job\StepState;

/**
 * The reindex runs with its connection already closed, so it reports here and the polling endpoint
 * reads it back. A file rather than the cache: reindexing flushes cache tags and would wipe its
 * own progress.
 */
class Mage_Index_Model_Progress
{
    public const VAR_SUBDIR = 'index_progress';

    /**
     * A record untouched for this long, with nothing holding an index lock, belongs to a worker
     * that was killed and will never write again.
     */
    public const STALE_AFTER = 300;

    protected const TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    protected ?string $token = null;

    protected array $steps = [];

    protected ?int $startedAt = null;

    protected bool $finished = false;

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @throws Mage_Core_Exception when the token is not one we generated
     */
    public function setToken(string $token): self
    {
        // Reaches us straight from the request and ends up in a file path
        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            Mage::throwException(Mage::helper('index')->__('Invalid reindex token.'));
        }

        $this->token = $token;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @throws Mage_Core_Exception when no token is set or the directory cannot be created
     */
    public function getFilePath(): string
    {
        if ($this->token === null) {
            Mage::throwException(Mage::helper('index')->__('Invalid reindex token.'));
        }

        $dir = Mage::getConfig()->getVarDir(self::VAR_SUBDIR);
        if ($dir === false) {
            Mage::throwException(Mage::helper('index')->__('Unable to create directory var/%s.', self::VAR_SUBDIR));
        }

        return $dir . DS . $this->token . '.json';
    }

    /**
     * Start a new record. Steps are ['code' => ..., 'name' => ...] in the order they will run.
     */
    public function init(array $steps): self
    {
        if ($this->token === null) {
            $this->token = self::generateToken();
        }

        $this->startedAt = time();
        $this->finished = false;
        $this->steps = [];

        foreach ($steps as $step) {
            $this->steps[] = [
                'code'     => (string) $step['code'],
                'name'     => (string) $step['name'],
                'state'    => StepState::Queued->value,
                'duration' => null,
                'message'  => null,
            ];
        }

        return $this->write();
    }

    public function startStep(string $code): self
    {
        return $this->updateStep($code, StepState::Running);
    }

    public function finishStep(string $code, float $duration): self
    {
        return $this->updateStep($code, StepState::Success, ['duration' => round($duration, 2)]);
    }

    public function failStep(string $code, string $message, float $duration): self
    {
        return $this->updateStep($code, StepState::Error, [
            'duration' => round($duration, 2),
            'message'  => $message,
        ]);
    }

    public function skipStep(string $code, string $message): self
    {
        return $this->updateStep($code, StepState::Skipped, ['message' => $message]);
    }

    public function finish(): self
    {
        $this->finished = true;
        return $this->write();
    }

    /**
     * The record as this process knows it, without going back to disk.
     */
    public function toArray(): array
    {
        return [
            'token'      => $this->token,
            'started_at' => $this->startedAt,
            'updated_at' => time(),
            'finished'   => $this->finished,
            'steps'      => $this->steps,
        ];
    }

    /**
     * Read the record back from disk. Returns an empty array when the run is unknown.
     */
    public function read(): array
    {
        $file = $this->getFilePath();
        if (!is_readable($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public function clear(): self
    {
        $file = $this->getFilePath();
        if (file_exists($file)) {
            unlink($file);
        }
        return $this;
    }

    /**
     * Drop records left behind by runs nobody polled to the end. Returns the count removed.
     */
    public static function cleanupStale(int $olderThanSeconds = 86400): int
    {
        // Resolve without getVarDir(): a cleanup pass must not create the directory it cleans
        $dir = Mage::getBaseDir('var') . DS . self::VAR_SUBDIR;
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - $olderThanSeconds;
        $removed = 0;
        foreach (glob($dir . DS . '*.json') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    protected function updateStep(string $code, StepState $state, array $extra = []): self
    {
        foreach ($this->steps as &$step) {
            if ($step['code'] === $code) {
                $step = array_merge($step, $extra, ['state' => $state->value]);
                break;
            }
        }
        unset($step);

        return $this->write();
    }

    /**
     * Written to a temporary file and renamed, so a poller never reads a half-written document.
     */
    protected function write(): self
    {
        $file = $this->getFilePath();

        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, Mage::helper('core')->jsonEncode($this->toArray())) === false) {
            return $this;
        }
        rename($tmp, $file);

        return $this;
    }
}
