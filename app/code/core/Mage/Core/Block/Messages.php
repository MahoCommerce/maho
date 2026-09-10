<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Block_Messages extends Mage_Core_Block_Template
{
    /**
     * Messages collection
     *
     * @var Mage_Core_Model_Message_Collection
     */
    protected $_messages;

    /**
     * Store first level html tag name for messages html output
     *
     * @var string
     */
    protected $_messagesFirstLevelTagName = 'ul';

    /**
     * Store second level html tag name for messages html output
     *
     * @var string
     */
    protected $_messagesSecondLevelTagName = 'li';

    /**
     * Store content wrapper html tag name for messages html output
     *
     * @var string
     */
    protected $_messagesContentWrapperTagName = 'span';

    /**
     * Storage for used types of message storages
     *
     * @var array
     */
    protected $_usedStorageTypes = ['core/session'];

    #[\Override]
    protected function _prepareLayout()
    {
        $this->addMessages(Mage::getSingleton('core/session')->getMessages(true));
        return parent::_prepareLayout();
    }

    /**
     * @deprecated since 26.9 message text is always escaped, so this flag no longer does anything
     */
    public function setEscapeMessageFlag(?bool $flag): self
    {
        return $this;
    }

    public function setMessages(Mage_Core_Model_Message_Collection $messages): self
    {
        $this->_messages = $messages;
        return $this;
    }

    public function addMessages(Mage_Core_Model_Message_Collection $messages): self
    {
        foreach ($messages->getItems() as $message) {
            $this->getMessageCollection()->add($message);
        }
        return $this;
    }

    public function getMessageCollection(): Mage_Core_Model_Message_Collection
    {
        if (!($this->_messages instanceof Mage_Core_Model_Message_Collection)) {
            $this->_messages = Mage::getModel('core/message_collection');
        }
        return $this->_messages;
    }

    public function addMessage(Mage_Core_Model_Message_Abstract $message): self
    {
        $this->getMessageCollection()->add($message);
        return $this;
    }

    /**
     * Adding new error message. The text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     */
    public function addError(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->error($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new warning message. The text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     */
    public function addWarning(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->warning($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new notice message. The text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     */
    public function addNotice(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->notice($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new success message. The text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     */
    public function addSuccess(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->addMessage(Mage::getSingleton('core/message')->success($text)->setTextArgs($args));
        return $this;
    }

    /**
     * @return Mage_Core_Model_Message_Abstract[]
     */
    public function getMessages(?string $type = null): array
    {
        return $this->getMessageCollection()->getItems($type);
    }

    public function getHtml(?string $type = null): string
    {
        $html = '<' . $this->_messagesFirstLevelTagName . ' id="admin_messages">';
        foreach ($this->getMessages($type) as $message) {
            $html .= '<' . $this->_messagesSecondLevelTagName . ' class="' . $message->getType() . '-msg">'
                . $this->_getMessageHtml($message)
                . '</' . $this->_messagesSecondLevelTagName . '>';
        }
        $html .= '</' . $this->_messagesFirstLevelTagName . '>';
        return $html;
    }

    public function getGroupedHtml(): string
    {
        $types = [
            Mage_Core_Model_Message::ERROR,
            Mage_Core_Model_Message::WARNING,
            Mage_Core_Model_Message::NOTICE,
            Mage_Core_Model_Message::SUCCESS,
        ];
        $html = '';
        foreach ($types as $type) {
            if ($messages = $this->getMessages($type)) {
                if (!$html) {
                    $html .= '<' . $this->_messagesFirstLevelTagName . ' class="messages">';
                }
                $html .= '<' . $this->_messagesSecondLevelTagName . ' class="' . $type . '-msg">';
                $html .= '<' . $this->_messagesFirstLevelTagName . '>';

                foreach ($messages as $message) {
                    $html .= '<' . $this->_messagesSecondLevelTagName . '>';
                    $html .= '<' . $this->_messagesContentWrapperTagName . '>';
                    $html .= $this->_getMessageHtml($message);
                    $html .= '</' . $this->_messagesContentWrapperTagName . '>';
                    $html .= '</' . $this->_messagesSecondLevelTagName . '>';
                }
                $html .= '</' . $this->_messagesFirstLevelTagName . '>';
                $html .= '</' . $this->_messagesSecondLevelTagName . '>';
            }
        }
        if ($html) {
            $html .= '</' . $this->_messagesFirstLevelTagName . '>';
        }
        $this->_messages = $this->getMessageCollection()->clear();
        return $html;
    }

    /**
     * The text and every argument are escaped here, so no caller ever escapes anything itself. A
     * \Maho\Message\Link argument becomes an anchor and a newline becomes a line break.
     */
    protected function _getMessageHtml(Mage_Core_Model_Message_Abstract $message): string
    {
        $html = $this->escapeHtml((string) $message->getCode());
        $args = $message->getTextArgs() ?? [];

        if ($args !== []) {
            try {
                $html = vsprintf($html, array_map($this->_renderMessageArg(...), $args));
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }

        return nl2br($html, false);
    }

    /**
     * A wrong type here throws inside the vsprintf() call above, which logs it and falls back to
     * the unsubstituted text rather than letting a renderer fatal.
     */
    protected function _renderMessageArg(string|\Maho\Message\Link|null $arg): string
    {
        if ($arg instanceof \Maho\Message\Link) {
            return '<a href="' . $this->escapeUrl($arg->url) . '">' . $this->escapeHtml($arg->label) . '</a>';
        }

        return (string) $this->escapeHtml((string) $arg);
    }

    #[\Override]
    protected function _toHtml(): string
    {
        return $this->getGroupedHtml();
    }

    public function setMessagesFirstLevelTagName(string $tagName): void
    {
        $this->_messagesFirstLevelTagName = $tagName;
    }

    public function setMessagesSecondLevelTagName(string $tagName): void
    {
        $this->_messagesSecondLevelTagName = $tagName;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function getCacheKeyInfo(): array
    {
        return [
            'storage_types' => serialize($this->_usedStorageTypes),
        ];
    }

    public function addStorageType(string $type): void
    {
        $this->_usedStorageTypes[] = $type;
    }
}
