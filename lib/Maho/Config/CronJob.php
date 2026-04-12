<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
     * @param string  $jobCode    Job code identifier (e.g. 'sitemap_generate', 'core_clean_cache')
     * @param ?string $schedule   Cron expression (e.g. '0 2 * * *')
     * @param ?string $configPath Config path for admin-configurable schedule (e.g. 'crontab/jobs/my_job/schedule/cron_expr')
     */
    public function __construct(
        public string $jobCode,
        public ?string $schedule = null,
        public ?string $configPath = null,
    ) {}
}
