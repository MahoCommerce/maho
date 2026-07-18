<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_Cron
{
    /**
     * Get all cron job definitions. Merges XML-defined jobs (legacy / custom
     * projects) with PHP-attribute cron jobs compiled into
     * vendor/composer/maho_attributes.php.
     *
     * Collisions: if the same job name exists in both XML and as an attribute,
     * the attribute version wins (the XML entry is replaced). This matches the
     * runtime cron registration order.
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
            $configPath = $jobConfig['schedule']['config_path'] ?? null;

            if (!$cronExpr && !empty($configPath)) {
                $cronExpr = Mage::getStoreConfig($configPath);
            }

            $result[$jobName] = [
                'name' => $jobName,
                'model' => $jobConfig['run']['model'] ?? '',
                'schedule' => $cronExpr ?? '',
                'config_path' => $configPath,
                'module' => null,
                'source' => 'xml',
            ];
        }

        $compiledCron = Maho::getCompiledAttributes()['crontab'] ?? [];
        foreach ($compiledCron as $jobName => $jobConfig) {
            $cronExpr = $jobConfig['schedule'] ?? null;
            $configPath = $jobConfig['config_path'] ?? null;

            if (!$cronExpr && !empty($configPath)) {
                $cronExpr = Mage::getStoreConfig($configPath);
            }

            $alias = $jobConfig['alias'] ?? '';
            $method = $jobConfig['method'] ?? '';
            $model = $alias !== '' && $method !== '' ? "{$alias}::{$method}" : '';

            $result[$jobName] = [
                'name' => $jobName,
                'model' => $model,
                'schedule' => $cronExpr ?? '',
                'config_path' => $configPath,
                'module' => $jobConfig['module'] ?? null,
                'source' => 'attribute',
            ];
        }

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }
}
