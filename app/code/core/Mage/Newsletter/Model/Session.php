<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

class Mage_Newsletter_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('newsletter');
    }

    /**
     * @deprecated since 26.9 render the messages through a core/messages block, which escapes
     *             the text and keeps a \Maho\Message\Link as a link. The caller of this method
     *             must escape the text itself, and a link arrives as its label alone.
     */
    public function getError(): string
    {
        return $this->takeFirstMessageText(Mage_Core_Model_Message::ERROR);
    }

    /**
     * @deprecated since 26.9 render the messages through a core/messages block, which escapes
     *             the text and keeps a \Maho\Message\Link as a link. The caller of this method
     *             must escape the text itself, and a link arrives as its label alone.
     */
    public function getSuccess(): string
    {
        return $this->takeFirstMessageText(Mage_Core_Model_Message::SUCCESS);
    }

    /** Read the oldest message of one type and remove it, as the two getters above always did. */
    private function takeFirstMessageText(string $type): string
    {
        $collection = $this->getMessages();
        $messages = $collection->getItemsByType($type);
        $message = reset($messages);
        if (!$message instanceof Mage_Core_Model_Message_Abstract) {
            return '';
        }

        $collection->deleteMessage($message);
        return $message->getText();
    }
}
