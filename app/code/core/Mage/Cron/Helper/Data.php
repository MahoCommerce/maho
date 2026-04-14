<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Cron';

    public function getConfiguredJobs(): array
    {
        $jobs = [];
        $cronNode = Mage::getConfig()->getNode('crontab/jobs');
        $defaultCronNode = Mage::getConfig()->getNode('default/crontab/jobs');

        if ($cronNode) {
            $jobs = array_merge($jobs, $cronNode->asArray());
        }
        if ($defaultCronNode) {
            $jobs = array_merge($jobs, $defaultCronNode->asArray());
        }
        ksort($jobs, SORT_NATURAL | SORT_FLAG_CASE);

        $result = [];
        foreach ($jobs as $jobCode => $jobConfig) {
            $cronExpr = '';
            $configPath = '';
            if (!empty($jobConfig['schedule']['config_path'])) {
                $configPath = $jobConfig['schedule']['config_path'];
                $cronExpr = (string) Mage::getStoreConfig($configPath);
            }
            if (empty($cronExpr) && !empty($jobConfig['schedule']['cron_expr'])) {
                $cronExpr = $jobConfig['schedule']['cron_expr'];
            }

            $result[$jobCode] = [
                'job_code' => $jobCode,
                'model_method' => $jobConfig['run']['model'] ?? '',
                'cron_expr' => $cronExpr,
                'config_path' => $configPath,
                'enabled' => !isset($jobConfig['schedule']['enabled']) || (string) $jobConfig['schedule']['enabled'] !== '0',
            ];
        }

        foreach (Maho::getCompiledAttributes()['crontab'] ?? [] as $jobCode => $jobDef) {
            $configPath = $jobDef['config_path'] ?? '';
            $result[$jobCode] = [
                'job_code' => $jobCode,
                'model_method' => $jobDef['alias'] . '::' . $jobDef['method'],
                'cron_expr' => $this->resolveCompiledCronExpr($jobDef),
                'config_path' => $configPath,
                'enabled' => $this->isJobEnabled($jobCode),
            ];
        }

        return $result;
    }

    public function resolveCompiledCronExpr(array $jobDef): string
    {
        $cronExpr = '';
        if (!empty($jobDef['config_path'])) {
            $cronExpr = (string) Mage::getStoreConfig($jobDef['config_path']);
        }
        if (empty($cronExpr) && !empty($jobDef['schedule'])) {
            $cronExpr = $jobDef['schedule'];
        }
        return $cronExpr;
    }

    public function getHumanReadableCronExpr(string $expr): string
    {
        $expr = trim($expr);
        if ($expr === '') {
            return $this->__('Not scheduled');
        }
        if ($expr === 'always') {
            return $this->__('Every cron run');
        }

        $parts = preg_split('#\s+#', $expr, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 5) {
            return $expr;
        }

        [$min, $hour, $day, $month, $weekday] = $parts;

        if ($min === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return $this->__('Every minute');
        }

        if (preg_match('#^\*/(\d+)$#', $min, $m) && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return $this->__('Every %d minutes', (int) $m[1]);
        }

        if ($min === '0' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            return $this->__('Every hour');
        }

        if (preg_match('#^\*/(\d+)$#', $hour, $m) && is_numeric($min) && $day === '*' && $month === '*' && $weekday === '*') {
            return $this->__('Every %d hours at minute %d', (int) $m[1], (int) $min);
        }

        if (is_numeric($min) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*') {
            return $this->__('Daily at %s', sprintf('%d:%02d', (int) $hour, (int) $min));
        }

        if (is_numeric($min) && is_numeric($hour) && $day === '*' && $month === '*' && is_numeric($weekday)) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayName = $days[(int) $weekday] ?? $weekday;
            return $this->__('Weekly on %s at %s', $dayName, sprintf('%d:%02d', (int) $hour, (int) $min));
        }

        if (is_numeric($min) && is_numeric($hour) && is_numeric($day) && $month === '*' && $weekday === '*') {
            return $this->__('Monthly on day %d at %s', (int) $day, sprintf('%d:%02d', (int) $hour, (int) $min));
        }

        return $this->_buildFieldDescription($parts);
    }

    protected function _buildFieldDescription(array $parts): string
    {
        $labels = [];

        if ($parts[0] !== '*') {
            $labels[] = $this->__('minute %s', $parts[0]);
        }
        if ($parts[1] !== '*') {
            $labels[] = $this->__('hour %s', $parts[1]);
        }
        if ($parts[2] !== '*') {
            $labels[] = $this->__('day %s', $parts[2]);
        }
        if ($parts[3] !== '*') {
            $labels[] = $this->__('month %s', $parts[3]);
        }
        if ($parts[4] !== '*') {
            $labels[] = $this->__('weekday %s', $parts[4]);
        }

        return $this->__('At %s', implode(', ', $labels));
    }

    public function getNextRunTime(string $cronExpr): ?string
    {
        if (empty($cronExpr) || $cronExpr === 'always') {
            return null;
        }

        try {
            $schedule = Mage::getModel('cron/schedule');
            $schedule->setCronExpr($cronExpr);
        } catch (\Exception) {
            return null;
        }

        $now = time();
        $maxTime = $now + 172800; // 48 hours

        for ($time = $now; $time < $maxTime; $time += 60) {
            if ($schedule->trySchedule($time)) {
                return date('Y-m-d H:i:00', $time);
            }
        }

        return null;
    }

    public function getLastExecution(string $jobCode): ?array
    {
        $all = $this->getAllLastExecutions();
        return $all[$jobCode] ?? null;
    }

    public function getAllLastExecutions(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('cron/schedule');

        $subSelect = $adapter->select()
            ->from($table, [
                'job_code',
                'max_executed' => new \Maho\Db\Expr('MAX(executed_at)'),
            ])
            ->where('executed_at IS NOT NULL')
            ->group('job_code');

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from(['s' => $table], ['job_code', 'executed_at', 'finished_at', 'status'])
                ->join(
                    ['latest' => $subSelect],
                    's.job_code = latest.job_code AND s.executed_at = latest.max_executed',
                    [],
                ),
        );

        $cache = [];
        foreach ($rows as $row) {
            $duration = null;
            if ($row['executed_at'] && $row['finished_at']) {
                $duration = strtotime($row['finished_at']) - strtotime($row['executed_at']);
            }
            $cache[$row['job_code']] = [
                'executed_at' => $row['executed_at'],
                'finished_at' => $row['finished_at'],
                'status' => $row['status'],
                'duration' => $duration,
            ];
        }

        return $cache;
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '';
        }
        if ($seconds < 60) {
            return $this->__('%ds', $seconds);
        }
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;
        if ($minutes < 60) {
            return $secs > 0 ? $this->__('%dm %ds', $minutes, $secs) : $this->__('%dm', $minutes);
        }
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return $mins > 0 ? $this->__('%dh %dm', $hours, $mins) : $this->__('%dh', $hours);
    }

    public function isJobEnabled(string $jobCode): bool
    {
        $node = Mage::getConfig()->getNode("crontab/jobs/{$jobCode}/schedule/enabled");
        if ($node === false) {
            $node = Mage::getConfig()->getNode("default/crontab/jobs/{$jobCode}/schedule/enabled");
        }
        return $node === false || (string) $node !== '0';
    }

    public function setJobEnabled(string $jobCode, bool $enabled): void
    {
        $this->setJobsEnabled([$jobCode], $enabled);
    }

    public function setJobsEnabled(array $jobCodes, bool $enabled): void
    {
        foreach ($jobCodes as $jobCode) {
            $path = "crontab/jobs/{$jobCode}/schedule/enabled";
            Mage::getConfig()->saveConfig($path, $enabled ? '1' : '0');
        }
        Mage::getConfig()->reinit();
    }
}
