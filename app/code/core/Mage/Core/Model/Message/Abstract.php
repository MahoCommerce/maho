<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

abstract class Mage_Core_Model_Message_Abstract
{
    protected $_type;
    protected $_code;
    protected $_class;
    protected $_method;
    protected $_identifier;
    protected $_isSticky = false;
    /** @var list<string|\Maho\Message\Link|null>|null */
    protected ?array $_textArgs = null;

    /**
     * Mage_Core_Model_Message_Abstract constructor.
     * @param string $type
     * @param string $code
     */
    public function __construct($type, $code = '')
    {
        $this->_type = $type;
        $this->_code = $code;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * The finished text, with the %s placeholders replaced and a \Maho\Message\Link reduced to
     * its label. This is the message as a reader sees it, so it is also its identity.
     *
     * A renderer that escapes the parts separately reads getCode() and getTextArgs() instead. A
     * bad format string falls back to the raw text here and is reported once, by that renderer.
     */
    public function getText(): string
    {
        return $this->formatText(
            (string) $this->getCode(),
            static fn(string|\Maho\Message\Link|null $arg): string => $arg instanceof \Maho\Message\Link ? $arg->label : (string) $arg,
        );
    }

    /**
     * Replace the %s placeholders in $text with the arguments, each one passed through
     * $renderArg. Every renderer shares this rule, so the plain text and the HTML always carry
     * the same values. A format string that does not match the arguments returns $text
     * unchanged and reports through $onError, so a broken message never stops a page.
     *
     * @param callable(string|\Maho\Message\Link|null): string $renderArg
     * @param (callable(Throwable): mixed)|null $onError
     */
    public function formatText(string $text, callable $renderArg, ?callable $onError = null): string
    {
        if ($this->_textArgs === null || $this->_textArgs === []) {
            return $text;
        }

        try {
            return vsprintf($text, array_map($renderArg, $this->_textArgs));
        } catch (Throwable $e) {
            if ($onError !== null) {
                $onError($e);
            }
            return $text;
        }
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class)
    {
        $this->_class = $class;
        return $this;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->_method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->getType() . ': ' . $this->getText();
    }

    /**
     * Set message identifier
     *
     * @param string $id
     * @return Mage_Core_Model_Message_Abstract
     */
    public function setIdentifier($id)
    {
        $this->_identifier = $id;
        return $this;
    }

    /**
     * Get message identifier
     *
     *  @return string
     */
    public function getIdentifier()
    {
        return $this->_identifier;
    }

    /**
     * Set message sticky status
     *
     * @param bool $isSticky
     * @return Mage_Core_Model_Message_Abstract
     */
    public function setIsSticky($isSticky = true)
    {
        $this->_isSticky = $isSticky;
        return $this;
    }

    /**
     * Get whether message is sticky
     *
     * @return bool
     */
    public function getIsSticky()
    {
        return $this->_isSticky;
    }

    /**
     * Values for the %s placeholders in the message text.
     *
     * The renderer escapes every one of them, so a caller never escapes anything itself. A
     * \Maho\Message\Link argument renders as an anchor, a newline in the text as a line break.
     *
     * @param list<string|\Maho\Message\Link|null> $args
     */
    public function setTextArgs(array $args): static
    {
        $this->_textArgs = array_values($args);
        return $this;
    }

    /**
     * @return list<string|\Maho\Message\Link|null>|null null when the message was not added as plain text
     */
    public function getTextArgs(): ?array
    {
        return $this->_textArgs;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return Mage_Core_Model_Message_Abstract
     */
    public function setCode($code)
    {
        $this->_code = $code;
        return $this;
    }
}
