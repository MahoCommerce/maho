<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Block_Adminhtml_Reindex extends Mage_Adminhtml_Block_Widget
{
    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/reindexPost');
    }

    public function isEmbedEnabled(): bool
    {
        return Mage::getStoreConfigFlag('maho_ai/embed/enabled');
    }

    public function isQueueEnabled(): bool
    {
        return Mage::getStoreConfigFlag('maho_ai/queue/enabled');
    }

    public function getEmbedPlatform(): string
    {
        return (string) Mage::getStoreConfig('maho_ai/embed/default_platform') ?: '—';
    }

    public function getEmbedModel(): string
    {
        $platform = Mage::getStoreConfig('maho_ai/embed/default_platform');
        return (string) Mage::getStoreConfig("maho_ai/embed/{$platform}_model") ?: '—';
    }

    public function getProductCount(): int
    {
        return (int) Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('status', ['eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED])
            ->getSize();
    }

    public function getCategoryCount(): int
    {
        return (int) Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('is_active', ['eq' => 1])
            ->addAttributeToFilter('level', ['gt' => 1]) // exclude root categories
            ->getSize();
    }

    public function getPendingTaskCount(): int
    {
        return (int) Mage::getModel('ai/task')->getCollection()
            ->addFieldToFilter('status', Maho_Ai_Model_Task::STATUS_PENDING)
            ->addFieldToFilter('task_type', Maho_Ai_Model_Task::TYPE_EMBEDDING)
            ->getSize();
    }

    /**
     * Estimate minutes to process N tasks given current queue settings.
     */
    public function estimateMinutes(int $taskCount): int
    {
        $maxPerRun  = (int) Mage::getStoreConfig('maho_ai/queue/max_tasks_per_run') ?: 10;
        $cronExpr   = (string) Mage::getStoreConfig('maho_ai/queue/cron_schedule') ?: '*/2 * * * *';

        // Parse interval from simple "*/N * * * *" expressions
        $intervalMinutes = 2;
        if (preg_match('#^\*/(\d+)#', $cronExpr, $m)) {
            $intervalMinutes = (int) $m[1];
        }

        $tasksPerMinute = $maxPerRun / max(1, $intervalMinutes);
        return (int) ceil($taskCount / max(0.01, $tasksPerMinute));
    }
}
