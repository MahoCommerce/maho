<?php

/**
 * Conversation management of the admin assistant: grid, history and deletion.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Adminhtml_Ai_ChatController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/ai/chat';

    #[Maho\Config\Route('/admin/ai_chat/index')]
    public function indexAction(): void
    {
        $this->_title(Mage::helper('ai')->__('Assistant Conversations'));
        $this->loadLayout()
            ->_setActiveMenu('system/ai/chat')
            ->_addBreadcrumb(Mage::helper('ai')->__('AI'), Mage::helper('ai')->__('AI'))
            ->_addBreadcrumb(Mage::helper('ai')->__('Assistant Conversations'), Mage::helper('ai')->__('Assistant Conversations'))
            ->renderLayout();
    }

    /**
     * The current admin's conversations, newest first, for the panel picker.
     */
    #[Maho\Config\Route('/admin/ai_chat/list', methods: ['GET'])]
    public function listAction(): void
    {
        $collection = Mage::getResourceModel('ai/conversation_collection')
            ->addFieldToFilter('admin_user_id', $this->adminId())
            ->addFieldToFilter('status', Maho_Ai_Model_Conversation::STATUS_ACTIVE)
            ->setOrder('updated_at', 'DESC')
            ->setPageSize(30);

        $items = [];
        /** @var Maho_Ai_Model_Conversation $conversation */
        foreach ($collection as $conversation) {
            $items[] = [
                'id' => (int) $conversation->getId(),
                'title' => (string) ($conversation->getTitle() ?? ''),
                'updated_at' => (string) $conversation->getUpdatedAt(),
                'pending' => $conversation->getPendingWrites() !== [],
            ];
        }
        $this->getResponse()->setBodyJson(['conversations' => $items]);
    }

    /**
     * The messages of one conversation, shaped for the panel history.
     */
    #[Maho\Config\Route('/admin/ai_chat/messages', methods: ['GET'])]
    public function messagesAction(): void
    {
        $conversation = $this->ownConversation();
        if ($conversation === null) {
            $this->getResponse()->setHttpResponseCode(404)->setBodyJson(['error' => Mage::helper('ai')->__('Conversation not found.')]);
            return;
        }

        $messages = [];
        /** @var Maho_Ai_Model_Conversation_Message $message */
        foreach ($conversation->messagesCollection() as $message) {
            $row = [
                'id' => (int) $message->getId(),
                'role' => $message->getRole(),
                'content' => (string) $message->getContent(),
                'created_at' => $message->getCreatedAt(),
            ];
            if ($message->getRole() === Maho_Ai_Model_Conversation_Message::ROLE_TOOL) {
                $row['tool'] = [
                    'id' => $message->getToolCallId(),
                    'name' => $message->getToolName(),
                    'arguments' => $message->getToolArguments(),
                    'status' => $message->getToolStatus(),
                    'is_write' => (bool) $message->getIsWrite(),
                ];
                unset($row['content']);
            }
            $messages[] = $row;
        }

        $this->getResponse()->setBodyJson([
            'conversation' => [
                'id' => (int) $conversation->getId(),
                'title' => (string) ($conversation->getTitle() ?? ''),
            ],
            'messages' => $messages,
        ]);
    }

    #[Maho\Config\Route('/admin/ai_chat/delete', methods: ['POST'])]
    public function deleteAction(): void
    {
        $conversation = $this->ownConversation();
        if ($conversation === null) {
            $this->getResponse()->setHttpResponseCode(404)->setBodyJson(['error' => Mage::helper('ai')->__('Conversation not found.')]);
            return;
        }
        $conversation->delete();
        $this->getResponse()->setBodyJson(['success' => true]);
    }

    #[Maho\Config\Route('/admin/ai_chat/rename', methods: ['POST'])]
    public function renameAction(): void
    {
        $conversation = $this->ownConversation();
        if ($conversation === null) {
            $this->getResponse()->setHttpResponseCode(404)->setBodyJson(['error' => Mage::helper('ai')->__('Conversation not found.')]);
            return;
        }
        $title = mb_substr(trim((string) $this->getRequest()->getParam('title')), 0, 255);
        $conversation->setTitle($title === '' ? null : $title);
        $conversation->save();
        $this->getResponse()->setBodyJson(['success' => true, 'title' => (string) ($conversation->getTitle() ?? '')]);
    }

    #[Maho\Config\Route('/admin/ai_chat/massDelete', methods: ['POST'])]
    public function massDeleteAction(): void
    {
        $ids = array_map(intval(...), (array) $this->getRequest()->getParam('conversation_id', []));
        $deleted = 0;
        foreach ($ids as $id) {
            /** @var Maho_Ai_Model_Conversation $conversation */
            $conversation = Mage::getModel('ai/conversation')->load($id);
            if ($conversation->getId()) {
                $conversation->delete();
                $deleted++;
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('ai')->__('%d conversation(s) were deleted.', $deleted));
        $this->_redirect('*/*/index');
    }

    private function adminId(): int
    {
        return (int) Mage::getSingleton('admin/session')->getUser()->getId();
    }

    /**
     * The conversation in the request, only when the current admin owns it.
     */
    private function ownConversation(): ?Maho_Ai_Model_Conversation
    {
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load((int) $this->getRequest()->getParam('id'));

        return $conversation->isOwnedBy($this->adminId()) ? $conversation : null;
    }
}
