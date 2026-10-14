<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_ResizeMessageHandler
{
    /**
     * @throws RuntimeException when a file could not be written, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function createFiles(Mage_Core_Model_Image_ResizeMessage $message): void
    {
        $failed = [];
        Mage::getSingleton('core/image_resizer')->createFiles($message->entity, $message->files, onFailure: function (string $file) use (&$failed): void {
            $failed[] = $file;
        });
        if ($failed !== []) {
            throw new RuntimeException(sprintf('%d images of the entity "%s" could not be written.', count($failed), $message->entity));
        }
    }

    #[Maho\Config\MessageHandler]
    public function createAll(Mage_Core_Model_Image_ResizeAllMessage $message): void
    {
        Mage::getSingleton('core/image_resizer')->createAll($message->entity);
    }

    /**
     * @throws RuntimeException when the mount could not read a file, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function readSizes(Mage_Core_Model_Image_SizeMessage $message): void
    {
        $failed = 0;
        Mage::getSingleton('core/image_size')->readAll($message->mount, $message->paths, function () use (&$failed): void {
            $failed++;
        });
        if ($failed > 0) {
            throw new RuntimeException(sprintf('The mount "%s" could not read %d images.', $message->mount, $failed));
        }
    }
}
