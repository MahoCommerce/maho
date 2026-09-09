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
     * Flag which require message text escape
     *
     * @var bool
     */
    protected $_escapeMessageFlag = true;

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
     * Set message escape flag
     * @param bool $flag
     * @return $this
     */
    public function setEscapeMessageFlag($flag)
    {
        $this->_escapeMessageFlag = $flag;
        return $this;
    }

    /**
     * Set messages collection
     *
     * @return  Mage_Core_Block_Messages
     */
    public function setMessages(Mage_Core_Model_Message_Collection $messages)
    {
        $this->_messages = $messages;
        return $this;
    }

    /**
     * Add messages to display
     *
     * @return $this
     */
    public function addMessages(Mage_Core_Model_Message_Collection $messages)
    {
        foreach ($messages->getItems() as $message) {
            $this->getMessageCollection()->add($message);
        }
        return $this;
    }

    /**
     * Retrieve messages collection
     *
     * @return Mage_Core_Model_Message_Collection
     */
    public function getMessageCollection()
    {
        if (!($this->_messages instanceof Mage_Core_Model_Message_Collection)) {
            $this->_messages = Mage::getModel('core/message_collection');
        }
        return $this->_messages;
    }

    /**
     * Adding new message to message collection
     *
     * @return  Mage_Core_Block_Messages
     */
    public function addMessage(Mage_Core_Model_Message_Abstract $message)
    {
        $this->getMessageCollection()->add($message);
        return $this;
    }

    /**
     * Adding new error message
     *
     * @param   string $message
     * @return  Mage_Core_Block_Messages
     */
    public function addError($message)
    {
        $this->addMessage(Mage::getSingleton('core/message')->error($message));
        return $this;
    }

    /**
     * Adding new error message whose text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     *
     * @return $this
     */
    public function addErrorText(string $text, string|int|float|bool|\Stringable|\Maho\Message\Link|null ...$args)
    {
        $this->addMessage(Mage::getSingleton('core/message')->error($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new warning message
     *
     * @param   string $message
     * @return  Mage_Core_Block_Messages
     */
    public function addWarning($message)
    {
        $this->addMessage(Mage::getSingleton('core/message')->warning($message));
        return $this;
    }

    /**
     * Adding new warning message whose text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     *
     * @return $this
     */
    public function addWarningText(string $text, string|int|float|bool|\Stringable|\Maho\Message\Link|null ...$args)
    {
        $this->addMessage(Mage::getSingleton('core/message')->warning($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new nitice message
     *
     * @param   string $message
     * @return  Mage_Core_Block_Messages
     */
    public function addNotice($message)
    {
        $this->addMessage(Mage::getSingleton('core/message')->notice($message));
        return $this;
    }

    /**
     * Adding new notice message whose text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     *
     * @return $this
     */
    public function addNoticeText(string $text, string|int|float|bool|\Stringable|\Maho\Message\Link|null ...$args)
    {
        $this->addMessage(Mage::getSingleton('core/message')->notice($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Adding new success message
     *
     * @param   string $message
     * @return  Mage_Core_Block_Messages
     */
    public function addSuccess($message)
    {
        $this->addMessage(Mage::getSingleton('core/message')->success($message));
        return $this;
    }

    /**
     * Adding new success message whose text is plain: %s placeholders take the arguments, the
     * renderer escapes them, a \Maho\Message\Link renders as a link and a newline as a break.
     *
     * @return $this
     */
    public function addSuccessText(string $text, string|int|float|bool|\Stringable|\Maho\Message\Link|null ...$args)
    {
        $this->addMessage(Mage::getSingleton('core/message')->success($text)->setTextArgs($args));
        return $this;
    }

    /**
     * Retrieve messages array by message type
     *
     * @param   string $type
     * @return  array
     */
    public function getMessages($type = null)
    {
        return $this->getMessageCollection()->getItems($type);
    }

    /**
     * Retrieve messages in HTML format
     *
     * @param   string $type
     * @return  string
     */
    public function getHtml($type = null)
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

    /**
     * Retrieve messages in HTML format grouped by type
     *
     * @return  string
     */
    public function getGroupedHtml()
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

    protected function _getMessageHtml(Mage_Core_Model_Message_Abstract $message): string
    {
        $text = (string) $message->getText();

        $args = $message->getTextArgs();
        if ($args !== null) {
            return $this->_renderTextMessage($text, $args);
        }

        return $this->_escapeMessageFlag ? $this->escapeHtml($text) : $text;
    }

    /**
     * Renders a plain-text message: the text and every argument are escaped, a
     * \Maho\Message\Link becomes an anchor and a newline becomes a line break.
     *
     * @param list<string|int|float|bool|\Stringable|\Maho\Message\Link|null> $args
     */
    protected function _renderTextMessage(string $text, array $args): string
    {
        $html = $this->escapeHtml($text);

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
    protected function _renderMessageArg(string|int|float|bool|\Stringable|\Maho\Message\Link|null $arg): string
    {
        if ($arg instanceof \Maho\Message\Link) {
            return '<a href="' . $this->escapeUrl($arg->url) . '">' . $this->escapeHtml($arg->label) . '</a>';
        }

        return (string) $this->escapeHtml((string) $arg);
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        return $this->getGroupedHtml();
    }

    /**
     * Set messages first level html tag name for output messages as html
     *
     * @param string $tagName
     */
    public function setMessagesFirstLevelTagName($tagName)
    {
        $this->_messagesFirstLevelTagName = $tagName;
    }

    /**
     * Set messages first level html tag name for output messages as html
     *
     * @param string $tagName
     */
    public function setMessagesSecondLevelTagName($tagName)
    {
        $this->_messagesSecondLevelTagName = $tagName;
    }

    /**
     * Get cache key informative items
     *
     * @return array
     */
    #[\Override]
    public function getCacheKeyInfo()
    {
        return [
            'storage_types' => serialize($this->_usedStorageTypes),
        ];
    }

    /**
     * Add used storage type
     *
     * @param string $type
     */
    public function addStorageType($type)
    {
        $this->_usedStorageTypes[] = $type;
    }
}
