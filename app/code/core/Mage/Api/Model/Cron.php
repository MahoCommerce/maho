<?php

/**
 * SPDX-FileCopyrightText: 2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

class Mage_Api_Model_Cron
{
    /**
     * Clean session table
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @return $this
     */
    #[Maho\Config\CronJob('api_session_cleanup', schedule: '0 35 * * *')]
    public function cleanOldSessions($schedule)
    {
        Mage::getResourceSingleton('api/user')->cleanOldSessions(null);
        return $this;
    }
}
