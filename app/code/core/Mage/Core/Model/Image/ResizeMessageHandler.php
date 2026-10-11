<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_ResizeMessageHandler
{
    #[Maho\Config\MessageHandler]
    public function createFiles(Mage_Core_Model_Image_ResizeMessage $message): void
    {
        Mage::getSingleton('core/image_resizer')->createFiles($message->entity, $message->files);
    }

    #[Maho\Config\MessageHandler]
    public function createAll(Mage_Core_Model_Image_ResizeAllMessage $message): void
    {
        Mage::getSingleton('core/image_resizer')->createAll($message->entity);
    }
}
