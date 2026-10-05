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

    /** A notification link comes from cron, which has no session to sign it with: open only redirects. */
    #[\Override]
    protected $_publicActions = ['open'];

    /** The audit trail has its own ACL entry: it shows what every administrator did. */
    #[\Override]
    protected function _isAllowed(): bool
    {
        if (in_array($this->getRequest()->getActionName(), ['actions', 'exportActionsCsv'], true)) {
            return Mage::getSingleton('admin/session')->isAllowed('system/ai/actions');
        }

        return parent::_isAllowed();
    }

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

    /** Every write the assistant made, for any administrator: the audit trail. */
    #[Maho\Config\Route('/admin/ai_chat/actions')]
    public function actionsAction(): void
    {
        $this->_title(Mage::helper('ai')->__('Assistant Actions Log'));
        $this->loadLayout()
            ->_setActiveMenu('system/ai/actions')
            ->_addBreadcrumb(Mage::helper('ai')->__('AI'), Mage::helper('ai')->__('AI'))
            ->_addBreadcrumb(Mage::helper('ai')->__('Assistant Actions Log'), Mage::helper('ai')->__('Assistant Actions Log'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/ai_chat/exportActionsCsv')]
    public function exportActionsCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('ai/adminhtml_action_grid');
        $this->_prepareDownloadResponse('assistant_actions.csv', $grid->getCsvFile());
    }

    /** The link of an assistant notification: the dashboard, with the panel open on the conversation. */
    #[Maho\Config\Route('/admin/ai_chat/open', methods: ['GET'])]
    public function openAction(): void
    {
        $conversation = $this->ownConversation();
        if ($conversation === null) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('ai')->__('This conversation belongs to another administrator. The notification text holds the result.'));
            $this->_redirect('adminhtml/dashboard/index');
            return;
        }
        Mage::getSingleton('adminhtml/session')->setData(Maho_Ai_Block_Adminhtml_Assistant::SESSION_OPEN_CONVERSATION, (int) $conversation->getId());
        $this->_redirect('adminhtml/dashboard/index');
    }

    /**
     * The current admin's conversations, newest first, for the panel picker.
     */
    #[Maho\Config\Route('/admin/ai_chat/list', methods: ['GET'])]
    public function listAction(): void
    {
        $collection = Mage::getResourceModel('ai/conversation_collection')
            ->addFieldToFilter('admin_user_id', $this->adminId())
            ->addFieldToFilter('status', ['in' => [Maho_Ai_Model_Conversation::STATUS_ACTIVE, Maho_Ai_Model_Conversation::STATUS_RUNNING]])
            ->setOrder('updated_at', 'DESC')
            ->setPageSize(30);

        $items = [];
        /** @var Maho_Ai_Model_Conversation $conversation */
        foreach ($collection as $conversation) {
            $conversation->reconcileBackgroundJob();
            $items[] = [
                'id' => (int) $conversation->getId(),
                'title' => (string) ($conversation->getTitle() ?? ''),
                'updated_at' => (string) $conversation->getUpdatedAt(),
                'pending' => $conversation->getPendingWrites() !== [],
                'running' => $conversation->isRunning(),
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

        $conversation->reconcileBackgroundJob();
        $messages = [];
        /** @var Maho_Ai_Model_Conversation_Message $message */
        foreach ($conversation->messagesCollection() as $message) {
            $row = [
                'id' => (int) $message->getId(),
                'role' => $message->getRole(),
                'content' => (string) $message->getContent(),
                'created_at' => $message->getCreatedAt(),
            ];
            if ($message->getRole() === Maho_Ai_Model_Conversation_Message::ROLE_ASSISTANT && $message->getToolStatus() !== null) {
                $row['notice'] = $message->getToolStatus();
            }
            if ($message->getRole() === Maho_Ai_Model_Conversation_Message::ROLE_USER && $message->getAttachments() !== []) {
                $row['attachments'] = $message->getAttachments();
            }
            if ($message->getRole() === Maho_Ai_Model_Conversation_Message::ROLE_TOOL) {
                $row['tool'] = [
                    'id' => $message->getToolCallId(),
                    'name' => $message->getToolName(),
                    'arguments' => $message->getToolArguments(),
                    'status' => $message->getToolStatus(),
                    'is_write' => (bool) $message->getIsWrite(),
                    'undo' => $message->getToolStatus() === Maho_Ai_Model_Conversation_Message::TOOL_DONE && $message->getUndoArguments() !== null ? (int) $message->getId() : null,
                ];
                unset($row['content']);
            }
            $messages[] = $row;
        }

        $this->getResponse()->setBodyJson([
            'conversation' => [
                'id' => (int) $conversation->getId(),
                'title' => (string) ($conversation->getTitle() ?? ''),
                'running' => $conversation->isRunning(),
                // A run that no worker took yet: without cron, no worker starts, and the panel says so.
                'queued' => $conversation->isRunning() && (bool) $conversation->latestTask()?->isPending(),
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
