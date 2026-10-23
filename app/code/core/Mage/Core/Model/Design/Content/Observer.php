<?php

/**
 * Queues and runs the build of the Tailwind classes of the storefront content.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Design_Content_Observer
{
    /**
     * Queue a build when the saved model is the model of a source and a field of that source changed.
     */
    #[Maho\Config\Observer('model_save_commit_after', id: 'tailwind_source_check')]
    public function checkSavedModel(\Maho\Event\Observer $observer): void
    {
        $object = $observer->getEvent()->getData('object');
        if (!$object instanceof Mage_Core_Model_Abstract) {
            return;
        }

        foreach (Mage::getSingleton('core/design_content_collector')->getSources() as $source) {
            if ($source['model'] === null || !$object instanceof $source['model']) {
                continue;
            }
            $changed = $source['fields'] === []
                || array_any($source['fields'], fn(string $field): bool => $object->dataHasChangedFor($field));
            if ($changed) {
                Mage::getSingleton('core/design_content_bundle')->queueBuild();
                return;
            }
        }
    }

    /**
     * Queue a build for a theme whose committed css/styles.css changed after its last build, or
     * that has no build, such as after a deploy.
     */
    #[Maho\Config\CronJob('theme_content_check', schedule: '*/15 * * * *')]
    public function queueOutdated(): void
    {
        $bundle = Mage::getSingleton('core/design_content_bundle');
        if ($bundle->getOutdatedThemes() !== []) {
            $bundle->queueBuild();
        }
    }

    /**
     * Build, then queue one more build when a request came after the start of this one, or when
     * another build held the lock. The new message keeps the dedupe key, so a later save adds
     * no second message. It skips the dedupe check, because this message still holds the key.
     */
    #[Maho\Config\MessageHandler]
    public function build(Mage_Core_Model_Design_Content_BuildMessage $message): void
    {
        $bundle = Mage::getSingleton('core/design_content_bundle');
        $startedAt = time();
        $status = $bundle->build();
        if ($status === null || $bundle->getRequestTime() >= $startedAt) {
            \Maho\Queue\QueueManager::dispatch(
                new Mage_Core_Model_Design_Content_BuildMessage(),
                delaySeconds: Mage_Core_Model_Design_Content_Bundle::BUILD_DELAY,
                queue: Mage_Core_Model_Design_Content_Bundle::QUEUE_NAME,
                stamps: [new \Maho\Queue\Stamp\DedupeKeyStamp(Mage_Core_Model_Design_Content_Bundle::QUEUE_NAME, enforce: false)],
            );
        }
    }
}
