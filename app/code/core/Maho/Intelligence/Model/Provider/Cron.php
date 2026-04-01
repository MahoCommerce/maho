<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Cron
{
    /**
     * Get all cron job definitions
     */
    public function getAllJobs(): array
    {
        $config = Mage::getConfig();
        $jobs = [];

        $cronNode = $config->getNode('crontab/jobs');
        $defaultCronNode = $config->getNode('default/crontab/jobs');

        if ($cronNode) {
            $jobs = array_merge($jobs, $cronNode->asArray());
        }
        if ($defaultCronNode) {
            $jobs = array_merge($jobs, $defaultCronNode->asArray());
        }

        $result = [];
        foreach ($jobs as $jobName => $jobConfig) {
            $cronExpr = $jobConfig['schedule']['cron_expr'] ?? null;

            if (!$cronExpr && !empty($jobConfig['schedule']['config_path'])) {
                $cronExpr = Mage::getStoreConfig($jobConfig['schedule']['config_path']);
            }

            $result[$jobName] = [
                'name' => $jobName,
                'model' => $jobConfig['run']['model'] ?? '',
                'schedule' => $cronExpr ?? '',
                'config_path' => $jobConfig['schedule']['config_path'] ?? null,
            ];
        }

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }
}
