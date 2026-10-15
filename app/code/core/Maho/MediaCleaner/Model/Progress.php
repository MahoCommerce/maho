<?php

/**
 * Progress record of a scan that runs after its request ended, for MahoJobDialog.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use Maho\Job\StepState;

class Maho_MediaCleaner_Model_Progress extends Mage_Index_Model_Progress
{
    public const VAR_SUBDIR = 'mediacleaner_progress';

    /** A record that a running scan did not write for this long belongs to a scan that stopped. */
    public const STALE_AFTER = 900;

    /**
     * Show that $done of $total folders of the step $code are read.
     */
    public function reportProgress(string $code, int $done, int $total): void
    {
        $this->updateStep($code, StepState::Running, [
            'progress' => $total > 0 ? (int) floor(100 * $done / $total) : 0,
            'message'  => Mage::helper('mediacleaner')->__('%d of %d folders read', $done, $total),
        ]);
    }

    public function finishStepWithMessage(string $code, float $duration, string $message): void
    {
        $this->updateStep($code, StepState::Success, [
            'duration' => round($duration, 2),
            'progress' => 100,
            'message'  => $message,
        ]);
    }

    /**
     * $record with its running step marked as failed, when the scan stopped without a word.
     *
     * @param array<string, mixed> $record from read()
     * @return array<string, mixed>
     */
    public static function detectInterruptedRun(array $record): array
    {
        if (!empty($record['finished']) || time() - (int) ($record['updated_at'] ?? 0) < static::STALE_AFTER) {
            return $record;
        }
        foreach ($record['steps'] as &$step) {
            if ($step['state'] !== StepState::Success->value) {
                $step['state'] = StepState::Error->value;
                $step['message'] = Mage::helper('mediacleaner')->__('The scan was interrupted.');
            }
        }
        unset($step);
        $record['finished'] = true;
        return $record;
    }
}
