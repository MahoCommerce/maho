<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Message
{
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const SUCCESS   = 'success';

    /**
     * Build a message of one type.
     *
     * $text is plain text with %s placeholders. $args fills the placeholders.
     * The renderer escapes the text and each argument. Do not escape them in the caller.
     * A \Maho\Message\Link argument renders as an anchor. A newline renders as a line break.
     *
     * @param list<string|\Maho\Message\Link|null> $args
     */
    protected function _factory(string $text, string $type, array $args = []): Mage_Core_Model_Message_Abstract
    {
        $message = match ($type) {
            self::ERROR => new Mage_Core_Model_Message_Error($text),
            self::WARNING => new Mage_Core_Model_Message_Warning($text),
            self::SUCCESS => new Mage_Core_Model_Message_Success($text),
            default => new Mage_Core_Model_Message_Notice($text),
        };

        return $message->setTextArgs($args);
    }

    /** @see self::_factory() for the rules on $text and $args */
    public function error(string $text, string|\Maho\Message\Link|null ...$args): Mage_Core_Model_Message_Abstract
    {
        return $this->_factory($text, self::ERROR, $args);
    }

    /** @see self::_factory() for the rules on $text and $args */
    public function warning(string $text, string|\Maho\Message\Link|null ...$args): Mage_Core_Model_Message_Abstract
    {
        return $this->_factory($text, self::WARNING, $args);
    }

    /** @see self::_factory() for the rules on $text and $args */
    public function notice(string $text, string|\Maho\Message\Link|null ...$args): Mage_Core_Model_Message_Abstract
    {
        return $this->_factory($text, self::NOTICE, $args);
    }

    /** @see self::_factory() for the rules on $text and $args */
    public function success(string $text, string|\Maho\Message\Link|null ...$args): Mage_Core_Model_Message_Abstract
    {
        return $this->_factory($text, self::SUCCESS, $args);
    }
}
