<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Adminhtml_AiController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config';

    /**
     * Per-action ACL resources. Mass-queue + destructive actions require
     * granular Maho AI permissions (declared in etc/adminhtml.xml under
     * system/maho_ai/*) rather than the base system/config grant — a user
     * with general config access shouldn't be able to one-click-queue
     * thousands of paid embedding tasks.
     */
    private const ACTION_RESOURCES = [
        'tasks'       => 'system/maho_ai/tasks',
        'view'        => 'system/maho_ai/tasks',
        'taskstatus'  => 'system/maho_ai/tasks',
        'usage'       => 'system/maho_ai/usage',
        'reindex'     => 'system/maho_ai/reindex',
        'reindexpost' => 'system/maho_ai/reindex',
    ];

    /** Read-only JSON poll — skip URL secret key, session cookie + ACL is sufficient. */
    protected $_publicActions = ['taskStatus'];

    #[\Override]
    public function preDispatch(): static
    {
        // fetchModels triggers outbound provider HTTP and writes the cached
        // model list to core_config_data, so it's state-changing and needs
        // a form key.
        $this->_setForcedFormKeyActions(['reindexPost', 'fetchModels']);
        return parent::preDispatch();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        $action = strtolower((string) $this->getRequest()->getActionName());
        $resource = self::ACTION_RESOURCES[$action] ?? static::ADMIN_RESOURCE;
        return Mage::getSingleton('admin/session')->isAllowed($resource);
    }

    /**
     * Per-action active menu paths. Drives the highlighted item in the
     * left-hand admin nav. Defaults to the parent "maho_ai" node.
     */
    private const ACTION_MENUS = [
        'tasks'   => 'system/maho_ai/tasks',
        'view'    => 'system/maho_ai/tasks',
        'usage'   => 'system/maho_ai/usage',
        'reindex' => 'system/maho_ai/reindex',
    ];

    protected function _initAction(): static
    {
        $action = strtolower((string) $this->getRequest()->getActionName());
        $menu   = self::ACTION_MENUS[$action] ?? 'system/maho_ai';

        $this->loadLayout()
            ->_setActiveMenu($menu)
            ->_addBreadcrumb(
                Mage::helper('ai')->__('AI'),
                Mage::helper('ai')->__('AI'),
            );

        return $this;
    }

    #[Maho\Config\Route('/admin/ai/tasks')]
    public function tasksAction(): void
    {
        $this->_title(Mage::helper('ai')->__('AI Task History'));
        $this->_initAction();
        $this->_addBreadcrumb(
            Mage::helper('ai')->__('Task History'),
            Mage::helper('ai')->__('Task History'),
        );
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/ai/view')]
    public function viewAction(): void
    {
        $id   = (int) $this->getRequest()->getParam('id');
        $task = Mage::getModel('ai/task')->load($id);

        if (!$task->getId()) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('ai')->__('Task not found.'));
            $this->_redirect('*/*/tasks');
            return;
        }

        Mage::register('current_ai_task', $task);

        $this->_title(Mage::helper('ai')->__('Task #%s', $id));
        $this->_initAction();
        $this->_addBreadcrumb(
            Mage::helper('ai')->__('Task History'),
            Mage::helper('ai')->__('Task History'),
            $this->getUrl('*/*/tasks'),
        );
        $this->_addBreadcrumb(
            Mage::helper('ai')->__('Task #%s', $id),
            Mage::helper('ai')->__('Task #%s', $id),
        );
        $this->renderLayout();
    }

    /**
     * JSON endpoint for polling task status — used by frontends that submit
     * an async AI task and want to display progress to the user without
     * blocking the originating HTTP request (Cloudflare's 60s edge timeout
     * is the typical motivator). Pair with submitImageTask() / submitTask()
     * + processTask() in the submitting controller.
     *
     * Returns: {task_id, status, task_type, response, error_message, completed_at}.
     * `response` is only populated when status is 'complete'; for image tasks,
     * it is the generated image URL.
     */
    #[Maho\Config\Route('/admin/ai/task_status')]
    public function taskStatusAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBodyJson(['error' => 'Missing or invalid id parameter.']);
            return;
        }

        /** @var Maho_Ai_Model_Task $task */
        $task = Mage::getModel('ai/task')->load($id);
        if (!$task->getId()) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->getResponse()->setBodyJson(['error' => 'Task not found.']);
            return;
        }

        $status = (string) $task->getData('status');
        $this->getResponse()->setBodyJson([
            'task_id'       => $id,
            'status'        => $status,
            'task_type'     => (string) $task->getData('task_type'),
            'response'      => $status === Maho_Ai_Model_Task::STATUS_COMPLETE
                ? (string) $task->getData('response')
                : null,
            'error_message' => (string) $task->getData('error_message') ?: null,
            'completed_at'  => $task->getData('completed_at'),
        ]);
    }

    #[Maho\Config\Route('/admin/ai/usage')]
    public function usageAction(): void
    {
        $this->_title(Mage::helper('ai')->__('AI Usage'));
        $this->_initAction();
        $this->_addBreadcrumb(
            Mage::helper('ai')->__('Usage'),
            Mage::helper('ai')->__('Usage'),
        );
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/ai/reindex')]
    public function reindexAction(): void
    {
        $this->_title(Mage::helper('ai')->__('Embeddings'));
        $this->_initAction();
        $this->_addBreadcrumb(
            Mage::helper('ai')->__('Embeddings'),
            Mage::helper('ai')->__('Embeddings'),
        );
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/ai/reindexPost')]
    public function reindexPostAction(): void
    {
        $types   = (array) $this->getRequest()->getPost('types', []);
        $storeId = 0;
        $queued  = 0;

        if (in_array('products', $types, true)) {
            $queued += $this->_queueEntityType('product', $storeId);
        }
        if (in_array('categories', $types, true)) {
            $queued += $this->_queueEntityType('category', $storeId);
        }

        if ($queued > 0) {
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('ai')->__('%s items queued for embedding.', number_format($queued)),
            );
        } else {
            Mage::getSingleton('adminhtml/session')->addNotice(
                Mage::helper('ai')->__('No items were queued. Make sure products/categories have text content.'),
            );
        }

        $this->_redirect('*/*/reindex');
    }

    /**
     * AJAX: re-fetch a provider's available models and cache them in
     * core_config_data. POST-only + form-key required (registered in
     * preDispatch). The admin URL secret key is added automatically by
     * getUrl(); mahoFetch attaches form_key from the page's FORM_KEY
     * global on every POST. The provider whitelist is enforced by the
     * match() in ModelFetcher::fetchForProvider().
     *
     * Auto-fetch on API-key save (Backend\ApiKey, Backend\FetchTrigger)
     * covers the first-time setup; this endpoint exists so admins can
     * pick up newly released models without re-entering the API key.
     */
    #[Maho\Config\Route('/admin/ai/fetchModels', methods: ['POST'])]
    public function fetchModelsAction(): void
    {
        $provider = (string) $this->getRequest()->getParam('provider');
        $capability = (string) $this->getRequest()->getParam('capability') ?: 'chat';
        if ($provider === '') {
            $this->getResponse()->setBodyJson(['error' => 'Provider is required.']);
            return;
        }

        try {
            /** @var Maho_Ai_Model_Platform_ModelFetcher $fetcher */
            $fetcher = Mage::getModel('ai/platform_modelFetcher');
            $models = $fetcher->fetchForProvider($provider, $capability);

            Mage::getModel('core/config')->saveConfig(
                "maho_ai/models_cache/{$provider}",
                Mage::helper('core')->jsonEncode($models),
            );
            Mage::app()->getCache()->cleanType('config');

            $this->getResponse()->setBodyJson(['models' => $models]);
        } catch (Mage_Core_Exception $e) {
            // User-facing Maho exceptions are written by us and safe to
            // surface (e.g. "OpenAI API key is not configured.").
            $this->getResponse()->setBodyJson(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Upstream HttpClient exceptions can echo request URLs back —
            // and the Google fetch URL includes ?key=<APIKEY>. Log the
            // detail, give the admin a generic prompt to check logs.
            Mage::logException($e);
            $this->getResponse()->setBodyJson([
                'error' => Mage::helper('ai')->__('Failed to fetch models. See exception.log for details.'),
            ]);
        }
    }

    /**
     * Batch-queue all entities of a given type for embedding.
     * Loads entities in pages of 500 and bulk-inserts task rows.
     */
    private function _queueEntityType(string $type, int $storeId): int
    {
        $conn      = Mage::getSingleton('core/resource')->getConnection('core_write');
        $taskTable = Mage::getSingleton('core/resource')->getTableName('ai/task');
        $now       = Mage::app()->getLocale()->formatDateForDb('now');
        $batch     = [];
        $count     = 0;

        $baseRow = [
            'action'      => 'embed',
            'task_type'   => Maho_Ai_Model_Task::TYPE_EMBEDDING,
            'status'      => Maho_Ai_Model_Task::STATUS_PENDING,
            'priority'    => Maho_Ai_Model_Task::PRIORITY_BACKGROUND,
            'max_retries' => 3,
            'store_id'    => $storeId,
            'created_at'  => $now,
        ];

        $flush = function () use (&$batch, &$count, $conn, $taskTable): void {
            // PHPStan can't track that $batch gets appended to outside the
            // closure (the loop below grows it before each flush call), so
            // it sees the initial [] and concludes this if is always false.
            // @phpstan-ignore if.alwaysFalse
            if ($batch) {
                $conn->insertMultiple($taskTable, $batch);
                $count += count($batch);
                $batch = [];
            }
        };

        if ($type === 'product') {
            $collection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect(['name', 'short_description', 'description'])
                ->setStoreId($storeId)
                ->addAttributeToFilter('status', ['eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED])
                ->setPageSize(500);

            $pages = $collection->getLastPageNumber();
            for ($page = 1; $page <= $pages; $page++) {
                $collection->setCurPage($page)->load();
                foreach ($collection as $product) {
                    $text = $this->_buildProductText($product);
                    if ($text === '') {
                        continue;
                    }
                    $batch[] = $baseRow + [
                        'consumer' => 'catalog_product',
                        'messages' => Mage::helper('core')->jsonEncode([['role' => 'user', 'content' => $text]]),
                        'context'  => Mage::helper('core')->jsonEncode(['entity_type' => 'product', 'entity_id' => (int) $product->getId()]),
                    ];
                    if (count($batch) >= 500) {
                        $flush();
                    }
                }
                $collection->clear();
            }
        } elseif ($type === 'category') {
            $collection = Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect(['name', 'description'])
                ->addAttributeToFilter('is_active', ['eq' => 1])
                ->addAttributeToFilter('level', ['gt' => 1])
                ->setPageSize(500);

            $pages = $collection->getLastPageNumber();
            for ($page = 1; $page <= $pages; $page++) {
                $collection->setCurPage($page)->load();
                foreach ($collection as $category) {
                    $text = trim(implode(' ', array_filter([
                        $category->getName(),
                        strip_tags((string) ($category->getData('description') ?? '')),
                    ])));
                    if ($text === '') {
                        continue;
                    }
                    $batch[] = $baseRow + [
                        'consumer' => 'catalog_category',
                        'messages' => Mage::helper('core')->jsonEncode([['role' => 'user', 'content' => $text]]),
                        'context'  => Mage::helper('core')->jsonEncode(['entity_type' => 'category', 'entity_id' => (int) $category->getId()]),
                    ];
                    if (count($batch) >= 500) {
                        $flush();
                    }
                }
                $collection->clear();
            }
        }

        $flush();

        return $count;
    }

    private function _buildProductText(Mage_Catalog_Model_Product $product): string
    {
        return trim(implode(' ', array_filter([
            $product->getName(),
            strip_tags((string) ($product->getData('short_description') ?? '')),
            strip_tags((string) ($product->getData('description') ?? '')),
        ])));
    }
}
