<?php

/**
 * Starts the scheduled assistant tasks that are due.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Task_Scheduler
{
    /**
     * A schedule whose minute passed while cron did not run starts once, late, and then
     * waits for its next minute: it never runs once for every minute it missed.
     */
    #[Maho\Config\CronJob('ai_run_schedules', schedule: '* * * * *')]
    public function runDueSchedules(): void
    {
        if (!Mage::helper('ai')->isChatEnabled() || !Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return;
        }
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        /** @var Maho_Ai_Model_Resource_Task_Schedule_Collection $collection */
        $collection = Mage::getModel('ai/task_schedule')->getCollection();
        $collection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('next_run_at', ['lteq' => $now]);

        foreach ($collection as $schedule) {
            /** @var Maho_Ai_Model_Task_Schedule $schedule */
            try {
                $this->start($schedule);
            } catch (Throwable $e) {
                Mage::logException($e);
            } finally {
                $schedule->setNextRunAt($schedule->computeNextRun($now))->save();
            }
        }
    }

    /** A schedule whose administrator is gone or inactive stops, so it never runs as nobody. */
    private function start(Maho_Ai_Model_Task_Schedule $schedule): void
    {
        $admin = Mage::getModel('admin/user')->load((int) $schedule->getAdminUserId());
        if (!$admin->getId() || !$admin->getIsActive()) {
            $schedule->setIsActive(false);
            return;
        }
        $schedule->run();
    }
}
