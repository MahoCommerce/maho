<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Adminhtml_Feedmanager_DestinationController extends Mage_Adminhtml_Controller_Action
{
    use Maho_FeedManager_Controller_Adminhtml_JsonResponseTrait;

    public const ADMIN_RESOURCE = 'catalog/feedmanager/destinations';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'save', 'massStatus', 'massDelete']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/feedmanager/destinations')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Feed Manager'), $this->__('Feed Manager'));
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'))
            ->_title($this->__('Manage Destinations'));

        $this->_initAction();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $destination = Mage::getModel('feedmanager/destination');

        if ($id) {
            $destination->load($id);
            if (!$destination->getId()) {
                $this->_getSession()->addError($this->__('This destination no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_destination', $destination);

        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'));

        if ($destination->getId()) {
            $this->_title($destination->getName());
        } else {
            $this->_title($this->__('New Destination'));
        }

        $this->_initAction();
        $this->_addBreadcrumb(
            $id ? $this->__('Edit Destination') : $this->__('New Destination'),
            $id ? $this->__('Edit Destination') : $this->__('New Destination'),
        );

        $this->renderLayout();
    }

    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();

        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $id = (int) $this->getRequest()->getParam('id');
        $destination = Mage::getModel('feedmanager/destination');

        if ($id) {
            $destination->load($id);
            if (!$destination->getId()) {
                $this->_getSession()->addError($this->__('This destination no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        try {
            // Extract config fields based on type, preserving sensitive fields if empty
            $configFields = $this->_extractConfigFields($data, $destination);
            $destination->setConfigArray($configFields);

            // Remove config fields from main data
            unset(
                $data['host'],
                $data['port'],
                $data['username'],
                $data['password'],
                $data['private_key'],
                $data['auth_type'],
                $data['remote_path'],
                $data['passive_mode'],
                $data['ssl'],
                $data['merchant_id'],
                $data['content_api_key'],
                $data['catalog_id'],
                $data['access_token'],
                $data['config'],
            );

            $destination->addData($data);

            // Validate config
            $errors = $destination->validateConfig();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->_getSession()->addError($error);
                }
                $this->_getSession()->setFormData($this->getRequest()->getPost());
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }

            $destination->save();

            $this->_getSession()->addSuccess($this->__('The destination has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $destination->getId()]);
                return;
            }

            $this->_redirect('*/*/');
            return;

        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_getSession()->setFormData($data);
            $this->_redirect('*/*/edit', ['id' => $id]);
            return;
        }
    }

    /**
     * Sensitive config fields that should preserve old values when submitted empty
     */
    private const SENSITIVE_CONFIG_FIELDS = [
        'password',
        'private_key',
        'service_account_json',
        'access_token',
    ];

    /**
     * Extract config fields from POST data based on destination type
     * Form sends data as config[field] which becomes $data['config']['field']
     *
     * For existing destinations, sensitive fields (passwords, keys, tokens) preserve
     * their old values when submitted empty, since password fields don't show existing values.
     */
    protected function _extractConfigFields(array $data, Maho_FeedManager_Model_Destination $destination): array
    {
        $type = $data['type'] ?? '';
        $configData = $data['config'] ?? [];
        $oldConfig = $destination->getId() ? $destination->getConfigArray() : [];

        $config = match ($type) {
            Maho_FeedManager_Model_Destination::TYPE_SFTP => [
                'host' => $configData['host'] ?? '',
                'port' => $configData['port'] ?? '22',
                'username' => $configData['username'] ?? '',
                'auth_type' => $configData['auth_type'] ?? 'password',
                'password' => $configData['password'] ?? '',
                'private_key' => $configData['private_key'] ?? '',
                'remote_path' => $configData['remote_path'] ?? '/',
            ],
            Maho_FeedManager_Model_Destination::TYPE_FTP => [
                'host' => $configData['host'] ?? '',
                'port' => $configData['port'] ?? '21',
                'username' => $configData['username'] ?? '',
                'password' => $configData['password'] ?? '',
                'remote_path' => $configData['remote_path'] ?? '/',
                'passive_mode' => $configData['passive_mode'] ?? '1',
                'ssl' => $configData['ssl'] ?? '0',
            ],
            Maho_FeedManager_Model_Destination::TYPE_GOOGLE_API => [
                'merchant_id' => $configData['merchant_id'] ?? '',
                'target_country' => $configData['target_country'] ?? 'AU',
                'service_account_json' => $configData['service_account_json'] ?? '',
            ],
            Maho_FeedManager_Model_Destination::TYPE_FACEBOOK_API => [
                'business_id' => $configData['business_id'] ?? '',
                'catalog_id' => $configData['catalog_id'] ?? '',
                'access_token' => $configData['access_token'] ?? '',
            ],
            default => [],
        };

        // Preserve old values for sensitive fields when submitted empty
        foreach (self::SENSITIVE_CONFIG_FIELDS as $field) {
            if (isset($config[$field]) && $config[$field] === '' && isset($oldConfig[$field])) {
                $config[$field] = $oldConfig[$field];
            }
        }

        return $config;
    }

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find a destination to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            // Check if any feeds are using this destination
            $feedCount = Mage::getResourceModel('feedmanager/feed_collection')
                ->addFieldToFilter('destination_id', $id)
                ->getSize();

            if ($feedCount > 0) {
                $this->_getSession()->addError(
                    $this->__('Cannot delete destination. %d feed(s) are using it.', $feedCount),
                );
                $this->_redirect('*/*/');
                return;
            }

            $destination = Mage::getModel('feedmanager/destination')->load($id);
            $destination->delete();

            $this->_getSession()->addSuccess($this->__('The destination has been deleted.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function testAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_sendJsonResponse(['error' => true, 'message' => 'Destination ID required']);
            return;
        }

        try {
            $destination = Mage::getModel('feedmanager/destination')->load($id);

            if (!$destination->getId()) {
                $this->_sendJsonResponse(['error' => true, 'message' => 'Destination not found']);
                return;
            }

            $uploader = new Maho_FeedManager_Model_Uploader($destination);
            $result = $uploader->testConnection();

            if ($result['success']) {
                $this->_sendJsonResponse([
                    'success' => true,
                    'message' => 'Connection successful',
                ]);
            } else {
                $this->_sendJsonResponse([
                    'error' => true,
                    'message' => $result['message'] ?? 'Connection failed',
                ]);
            }

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function massStatusAction(): void
    {
        $destinationIds = $this->getRequest()->getParam('destination_ids');
        $status = (int) $this->getRequest()->getParam('status');

        if (!is_array($destinationIds)) {
            $this->_getSession()->addError($this->__('Please select destinations.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            foreach ($destinationIds as $destinationId) {
                $destination = Mage::getModel('feedmanager/destination')->load($destinationId);
                if ($destination->getId()) {
                    $destination->setIsEnabled($status)->save();
                }
            }

            $this->_getSession()->addSuccess(
                $this->__('%d destination(s) have been updated.', count($destinationIds)),
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $destinationIds = $this->getRequest()->getParam('destination_ids');

        if (!is_array($destinationIds)) {
            $this->_getSession()->addError($this->__('Please select destinations to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        $deleted = 0;
        $skipped = 0;

        try {
            foreach ($destinationIds as $destinationId) {
                // Check if any feeds are using this destination
                $feedCount = Mage::getResourceModel('feedmanager/feed_collection')
                    ->addFieldToFilter('destination_id', $destinationId)
                    ->getSize();

                if ($feedCount > 0) {
                    $skipped++;
                    continue;
                }

                $destination = Mage::getModel('feedmanager/destination')->load($destinationId);
                if ($destination->getId()) {
                    $destination->delete();
                    $deleted++;
                }
            }

            if ($deleted) {
                $this->_getSession()->addSuccess(
                    $this->__('%d destination(s) have been deleted.', $deleted),
                );
            }
            if ($skipped) {
                $this->_getSession()->addNotice(
                    $this->__('%d destination(s) were skipped because they are in use by feeds.', $skipped),
                );
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }
}
