<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Config;

use Attribute;

/**
 * Registers a method as a cron job.
 *
 * Compiled at `composer dump-autoload` into `vendor/composer/maho_attributes.php`.
 * Run `composer dump-autoload` after adding, modifying, or removing this attribute.
 * Provide either a fixed `$schedule` or a `$configPath` for admin-configurable schedules, not both.
 *
 * @see Mage_Cron_Model_Observer::dispatch()
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class CronJob
{
    /**
     * @param string  $id         Job identifier (e.g. 'sitemap_generate', 'core_clean_cache')
     * @param ?string $schedule   Cron expression (e.g. '0 2 * * *')
     * @param ?string $configPath Config path for admin-configurable schedule (e.g. 'crontab/jobs/my_job/schedule/cron_expr')
     */
    public function __construct(
        public string $id,
        public ?string $schedule = null,
        public ?string $configPath = null,
    ) {}
}
