<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Adminhtml_Feedmanager_FeedController extends Mage_Adminhtml_Controller_Action
{
    use Maho_FeedManager_Controller_Adminhtml_JsonResponseTrait;

    public const ADMIN_RESOURCE = 'catalog/feedmanager/feeds';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions([
            'delete',
            'save',
            'duplicate',
            'generate',
            'reset',
            'forceReset',
            'massGenerate',
            'massStatus',
            'massDelete',
            'upload',
        ]);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/feedmanager/feeds')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Feed Manager'), $this->__('Feed Manager'));
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'))
            ->_title($this->__('Feeds'));

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
        $feed = Mage::getModel('feedmanager/feed');

        if ($id) {
            $feed->load($id);
            if (!$feed->getId()) {
                $this->_getSession()->addError($this->__('This feed no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_feed', $feed);

        $feed->getConditions()->setJsFormObject('feed_conditions_fieldset');

        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'));

        if ($feed->getId()) {
            $this->_title($feed->getName());
        } else {
            $this->_title($this->__('New Feed'));
        }

        $this->_initAction();
        $this->_addBreadcrumb(
            $id ? $this->__('Edit Feed') : $this->__('New Feed'),
            $id ? $this->__('Edit Feed') : $this->__('New Feed'),
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
        $feed = Mage::getModel('feedmanager/feed');

        if ($id) {
            $feed->load($id);
            if (!$feed->getId()) {
                $this->_getSession()->addError($this->__('This feed no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        try {
            // Convert empty foreign keys to null
            if (isset($data['destination_id']) && $data['destination_id'] === '') {
                $data['destination_id'] = null;
            }
            if (isset($data['store_id']) && $data['store_id'] === '') {
                $data['store_id'] = 0;
            }

            // Convert product types array to comma-separated string
            if (isset($data['include_product_types']) && is_array($data['include_product_types'])) {
                $data['include_product_types'] = implode(',', $data['include_product_types']);
            }

            // Handle Rule conditions before addData to avoid array-to-string warning
            if (isset($data['rule']['conditions'])) {
                $conditionsData = $data['rule']['conditions'];
                unset($data['rule']);

                $conditionsArray = $this->_buildConditionsArray($conditionsData);
                $feed->addData($data);

                if (!empty($conditionsArray)) {
                    $feed->getConditions()->setConditions([])->loadArray($conditionsArray);
                }
            } else {
                unset($data['rule']);
                $feed->addData($data);
            }

            $feed->save();

            // Save attribute mappings
            if (isset($data['mapping'])) {
                $this->_saveMappings($feed, $data['mapping']);
            }

            $this->_getSession()->addSuccess($this->__('The feed has been saved.'));

            // Check if we should generate after save
            if ($this->getRequest()->getParam('generate_after_save')) {
                $this->_redirect('*/*/edit', ['id' => $feed->getId(), 'generate' => '1']);
                return;
            }

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $feed->getId()]);
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
     * Save attribute mappings for a feed
     */
    protected function _saveMappings(Maho_FeedManager_Model_Feed $feed, array $mappings): void
    {
        // Delete existing mappings
        $existingMappings = $feed->getAttributeMappings();
        foreach ($existingMappings as $mapping) {
            $mapping->delete();
        }

        // Save new mappings
        foreach ($mappings as $feedAttribute => $config) {
            if (empty($config['source_type'])) {
                continue;
            }

            $mapping = Mage::getModel('feedmanager/attributeMapping');
            $mapping->setFeedId($feed->getId())
                ->setFeedAttribute($feedAttribute)
                ->setSourceType($config['source_type'])
                ->setSourceValue($config['source_value'] ?? '')
                ->save();
        }
    }

    /**
     * Build conditions array from form post data
     */
    protected function _buildConditionsArray(array $conditionsData): array
    {
        $arr = $this->_convertFlatToRecursive($conditionsData);

        if (empty($arr) || !isset($arr[1])) {
            return [];
        }

        return $arr[1];
    }

    /**
     * Convert flat form conditions data to recursive array structure
     *
     * @return array<int|string, mixed>
     */
    protected function _convertFlatToRecursive(array $data): array
    {
        /** @var array<string, mixed> $arr */
        $arr = ['conditions' => []];
        foreach ($data as $id => $itemData) {
            $path = explode('--', (string) $id);
            $node = &$arr;
            for ($i = 0, $l = count($path); $i < $l; $i++) {
                if (!array_key_exists('conditions', $node)) {
                    $node['conditions'] = [];
                }
                if (!array_key_exists($path[$i], $node['conditions'])) {
                    $node['conditions'][$path[$i]] = [];
                }
                $node = &$node['conditions'][$path[$i]];
            }
            foreach ($itemData as $k => $v) {
                $node[$k] = $v;
            }
        }
        return $arr['conditions'];
    }

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find a feed to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $feed = Mage::getModel('feedmanager/feed')->load($id);
            $feed->delete();

            $this->_getSession()->addSuccess($this->__('The feed has been deleted.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * Duplicate a feed
     */
    public function duplicateAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find a feed to duplicate.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            /** @var Maho_FeedManager_Model_Feed $feed */
            $feed = Mage::getModel('feedmanager/feed')->load($id);

            if (!$feed->getId()) {
                $this->_getSession()->addError($this->__('Feed not found.'));
                $this->_redirect('*/*/');
                return;
            }

            // Create a copy of the feed data
            $data = $feed->getData();

            // Remove fields that shouldn't be copied
            unset($data['feed_id']);
            unset($data['created_at']);
            unset($data['updated_at']);
            unset($data['last_generated_at']);
            unset($data['generation_status']);

            // Modify name and filename
            $data['name'] = $feed->getName() . ' (Copy)';
            $data['filename'] = $feed->getFilename() . '_copy';

            // Create new feed
            /** @var Maho_FeedManager_Model_Feed $newFeed */
            $newFeed = Mage::getModel('feedmanager/feed');
            $newFeed->setData($data);
            $newFeed->save();

            // Copy attribute mappings if any
            $mappings = $feed->getAttributeMappings();
            foreach ($mappings as $mapping) {
                $mappingData = $mapping->getData();
                unset($mappingData['mapping_id']);
                $mappingData['feed_id'] = $newFeed->getId();

                $newMapping = Mage::getModel('feedmanager/attributeMapping');
                $newMapping->setData($mappingData);
                $newMapping->save();
            }

            $this->_getSession()->addSuccess(
                $this->__('Feed has been duplicated. You are now editing the copy.'),
            );

            $this->_redirect('*/*/edit', ['id' => $newFeed->getId()]);
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
        }
    }

    public function generateAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $async = (bool) $this->getRequest()->getParam('async');

        if (!$id) {
            if ($async) {
                $this->_sendJsonResponse(['error' => true, 'message' => 'Feed ID required']);
                return;
            }
            $this->_getSession()->addError($this->__('Unable to find a feed to generate.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $feed = Mage::getModel('feedmanager/feed')->load($id);

            if (!$feed->getId()) {
                if ($async) {
                    $this->_sendJsonResponse(['error' => true, 'message' => 'Feed not found']);
                    return;
                }
                $this->_getSession()->addError($this->__('This feed no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }

            // Check if already running
            if (Maho_FeedManager_Model_Generator::isGenerating($id)) {
                if ($async) {
                    $this->_sendJsonResponse([
                        'error' => true,
                        'message' => 'Feed is already being generated',
                        'status' => Maho_FeedManager_Model_Generator::getGenerationStatus($id),
                    ]);
                    return;
                }
                $this->_getSession()->addNotice($this->__('Feed is already being generated.'));
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }

            // For async requests, start generation in background
            if ($async) {
                // Start generation (this will block, but frontend will poll for status)
                $generator = new Maho_FeedManager_Model_Generator();
                $log = $generator->generate($feed);

                $this->_sendJsonResponse([
                    'success' => $log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED,
                    'status' => Maho_FeedManager_Model_Generator::getGenerationStatus($id),
                ]);
                return;
            }

            // Synchronous generation for non-AJAX requests
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                $this->_getSession()->addSuccess(
                    $this->__('Feed generated successfully with %d products.', $log->getProductCount()),
                );
                // Show any warnings
                $errors = $log->getErrorMessagesArray();
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        if (str_contains($error, 'Warning')) {
                            $this->_getSession()->addNotice($error);
                        }
                    }
                }
            } else {
                $errors = $log->getErrorMessagesArray();
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->_getSession()->addError($error);
                    }
                } else {
                    $this->_getSession()->addError($this->__('Feed generation failed. Check logs for details.'));
                }
            }

        } catch (Exception $e) {
            if ($async) {
                $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
                return;
            }
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/edit', ['id' => $id]);
    }

    /**
     * AJAX action to get generation status
     */
    public function statusAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_sendJsonResponse(['error' => true, 'message' => 'Feed ID required']);
            return;
        }

        $status = Maho_FeedManager_Model_Generator::getGenerationStatus($id);
        $this->_sendJsonResponse($status);
    }

    /**
     * AJAX action to generate feed preview
     */
    public function previewAction(): void
    {
        $feedId = (int) $this->getRequest()->getParam('feed_id');
        $previewCount = (int) $this->getRequest()->getParam('preview_count', 3);

        // Limit preview count
        $previewCount = min(max($previewCount, 1), 10);

        try {
            // Get form data for template preview
            $postData = $this->getRequest()->getPost();

            // Load existing feed or create temporary one
            $feed = Mage::getModel('feedmanager/feed');
            if ($feedId) {
                $feed->load($feedId);
            }

            // Apply posted form data to the feed for preview
            if ($postData) {
                // Apply XML template data if provided
                if (!empty($postData['xml_header'])) {
                    $feed->setXmlHeader($postData['xml_header']);
                }
                if (!empty($postData['xml_item_template'])) {
                    $feed->setXmlItemTemplate($postData['xml_item_template']);
                }
                if (!empty($postData['xml_footer'])) {
                    $feed->setXmlFooter($postData['xml_footer']);
                }

                // Apply format settings
                if (!empty($postData['price_currency'])) {
                    $feed->setPriceCurrency($postData['price_currency']);
                }
                if (isset($postData['price_decimals'])) {
                    $feed->setPriceDecimals($postData['price_decimals']);
                }
                if (!empty($postData['price_decimal_point'])) {
                    $feed->setPriceDecimalPoint($postData['price_decimal_point']);
                }
                if (isset($postData['price_thousands_sep'])) {
                    $feed->setPriceThousandsSep($postData['price_thousands_sep']);
                }
                if (!empty($postData['tax_mode'])) {
                    $feed->setTaxMode($postData['tax_mode']);
                }
                if (!empty($postData['store_id'])) {
                    $feed->setStoreId($postData['store_id']);
                }
            }

            // Generate preview
            $generator = new Maho_FeedManager_Model_Generator();
            $preview = $generator->generatePreview($feed, $previewCount);

            $this->_sendJsonResponse([
                'success' => true,
                'preview' => $preview,
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX action for CSV preview - uses Mapper for consistent output
     */
    public function csvPreviewAction(): void
    {
        $feedId = (int) $this->getRequest()->getParam('id');
        $columns = $this->getRequest()->getParam('columns');

        try {
            /** @var Maho_FeedManager_Model_Feed $feed */
            $feed = Mage::getModel('feedmanager/feed');
            if ($feedId) {
                $feed->load($feedId);
            }
            $columnsData = $columns ? Mage::helper('core')->jsonDecode($columns) : [];

            if (empty($columnsData)) {
                $this->_sendJsonResponse([
                    'success' => true,
                    'preview' => '',
                    'count' => 0,
                ]);
                return;
            }

            // Use Mapper for value extraction
            $mapper = new Maho_FeedManager_Model_Mapper($feed);
            $mapper->setMappingsFromCsvColumns($columnsData);

            // Get sample products
            $collection = $this->_getPreviewCollection();

            $output = [];

            // Header row
            $headers = array_column($columnsData, 'name');
            $output[] = $headers;

            // Data rows using Mapper
            foreach ($collection as $product) {
                $row = $mapper->mapProduct($product);
                $output[] = array_values($row);
            }

            // Convert to CSV string
            $delimiter = $feed->getCsvDelimiter() ?: ',';
            $enclosure = $feed->getCsvEnclosure() ?? '"';
            $csv = $this->_formatCsvOutput($output, $delimiter, $enclosure);

            $this->_sendJsonResponse([
                'success' => true,
                'preview' => $csv,
                'count' => count($output) - 1,
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX action for JSON preview - uses Mapper for consistent output
     */
    public function jsonPreviewAction(): void
    {
        $feedId = (int) $this->getRequest()->getParam('id');
        $structure = $this->getRequest()->getParam('structure');

        try {
            /** @var Maho_FeedManager_Model_Feed $feed */
            $feed = Mage::getModel('feedmanager/feed');
            if ($feedId) {
                $feed->load($feedId);
            }
            $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : [];

            $rootKey = $feed->getJsonRootKey() ?: 'products';

            if (empty($structureData)) {
                $this->_sendJsonResponse([
                    'success' => true,
                    'preview' => json_encode([$rootKey => []], JSON_PRETTY_PRINT),
                    'count' => 0,
                ]);
                return;
            }

            // Use Mapper for value extraction
            $mapper = new Maho_FeedManager_Model_Mapper($feed);

            // Get sample products
            $collection = $this->_getPreviewCollection(2);

            $products = [];
            foreach ($collection as $product) {
                $products[] = $mapper->mapProductToJsonStructure($product, $structureData);
            }

            $output = [$rootKey => $products];

            $this->_sendJsonResponse([
                'success' => true,
                'preview' => json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'count' => count($products),
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX action for XML preview - uses Mapper for consistent output
     */
    public function xmlPreviewAction(): void
    {
        $feedId = (int) $this->getRequest()->getParam('id');
        $structure = $this->getRequest()->getParam('structure');
        $fullPreview = (bool) $this->getRequest()->getParam('full_preview');

        try {
            /** @var Maho_FeedManager_Model_Feed $feed */
            $feed = Mage::getModel('feedmanager/feed');
            if ($feedId) {
                $feed->load($feedId);
            }
            $structureData = $structure ? Mage::helper('core')->jsonDecode($structure) : [];
            $itemTag = $feed->getXmlItemTag() ?: 'item';

            if (empty($structureData)) {
                $this->_sendJsonResponse([
                    'success' => true,
                    'preview' => '<' . $itemTag . ">\n</" . $itemTag . '>',
                    'count' => 0,
                ]);
                return;
            }

            // Use Mapper for value extraction
            $mapper = new Maho_FeedManager_Model_Mapper($feed);

            // Get sample products
            $collection = $this->_getPreviewCollection(2);

            $xml = '';

            // Add header for full preview
            if ($fullPreview) {
                $header = $feed->getXmlHeader();
                if ($header) {
                    $xml .= $header . "\n";
                }
            }

            foreach ($collection as $product) {
                $xml .= $mapper->mapProductToXmlStructure($product, $structureData, $itemTag, $fullPreview ? 1 : 0);
            }

            // Add footer for full preview
            if ($fullPreview) {
                $footer = $feed->getXmlFooter();
                if ($footer) {
                    $xml .= $footer . "\n";
                }
            }

            $this->_sendJsonResponse([
                'success' => true,
                'preview' => $xml,
                'count' => count($collection),
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get product collection for preview with media gallery loaded
     */
    protected function _getPreviewCollection(int $limit = 3): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->setPageSize($limit);

        // Load and attach media gallery to each product
        $mediaGalleryAttribute = Mage::getSingleton('eav/config')
            ->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'media_gallery');

        if ($mediaGalleryAttribute && $mediaGalleryAttribute->getId()) {
            $mediaBackend = $mediaGalleryAttribute->getBackend();
            foreach ($collection as $product) {
                $mediaBackend->afterLoad($product);
            }
        }

        return $collection;
    }

    /**
     * Format output as CSV string
     */
    protected function _formatCsvOutput(array $rows, string $delimiter, string $enclosure): string
    {
        $csv = '';
        foreach ($rows as $row) {
            if ($enclosure) {
                $csv .= $enclosure . implode($enclosure . $delimiter . $enclosure, array_map(function ($v) use ($enclosure) {
                    return str_replace($enclosure, $enclosure . $enclosure, (string) $v);
                }, $row)) . $enclosure . "\n";
            } else {
                $csv .= implode($delimiter, $row) . "\n";
            }
        }
        return $csv;
    }

    /**
     * AJAX action to get platform preset data for CSV/JSON/XML builders
     */
    public function platformPresetAction(): void
    {
        $platform = $this->getRequest()->getParam('platform');
        $format = $this->getRequest()->getParam('format', 'csv');

        if (!$platform) {
            $this->_sendJsonResponse(['error' => true, 'message' => 'Platform required']);
            return;
        }

        try {
            /** @var Maho_FeedManager_Model_Platform $platformModel */
            $platformModel = Mage::getSingleton('feedmanager/platform');
            $adapter = $platformModel->getAdapter($platform);

            if (!$adapter) {
                $this->_sendJsonResponse(['error' => true, 'message' => 'Unknown platform']);
                return;
            }

            $mappings = $adapter->getDefaultMappings();
            $requiredAttributes = $adapter->getRequiredAttributes();
            $optionalAttributes = $adapter->getOptionalAttributes();

            // Convert to column format for CSV builder
            $columns = [];
            foreach (array_merge($requiredAttributes, $optionalAttributes) as $key => $attr) {
                $mapping = $mappings[$key] ?? ['source_type' => 'attribute', 'source_value' => ''];
                $columns[] = [
                    'name' => $key,
                    'source_type' => $mapping['source_type'],
                    'source_value' => $mapping['source_value'],
                    'use_parent' => $mapping['use_parent'] ?? false,
                    'transformers' => $mapping['transformers'] ?? '',
                    'required' => $attr['required'] ?? false,
                ];
            }

            // Convert to structure for JSON/XML builder
            $structure = [];
            if ($format === 'xml') {
                // XML uses array format with tag property
                foreach (array_merge($requiredAttributes, $optionalAttributes) as $key => $attr) {
                    $mapping = $mappings[$key] ?? ['source_type' => 'attribute', 'source_value' => ''];
                    $structure[] = [
                        'tag' => $key,
                        'source_type' => $mapping['source_type'],
                        'source_value' => $mapping['source_value'],
                        'transformers' => $mapping['transformers'] ?? '',
                        'cdata' => in_array($key, ['title', 'description', 'google_product_category', 'product_category', 'product_type']),
                        'optional' => !($attr['required'] ?? false),
                        'use_parent' => $mapping['use_parent'] ?? '',
                    ];
                }
            } else {
                // JSON uses object format
                foreach (array_keys(array_merge($requiredAttributes, $optionalAttributes)) as $key) {
                    $mapping = $mappings[$key] ?? ['source_type' => 'attribute', 'source_value' => ''];
                    $structure[$key] = [
                        'type' => 'string',
                        'source_type' => $mapping['source_type'],
                        'source_value' => $mapping['source_value'],
                        'transformers' => $mapping['transformers'] ?? '',
                        'use_parent' => $mapping['use_parent'] ?? '',
                    ];
                }
            }

            $this->_sendJsonResponse([
                'success' => true,
                'columns' => $columns,
                'structure' => $structure,
                'platform' => $platform,
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Reset a hung feed
     */
    public function resetAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find feed.'));
            $this->_redirect('*/*/');
            return;
        }

        $cron = new Maho_FeedManager_Model_Cron();
        if ($cron->resetHungFeed($id)) {
            $this->_getSession()->addSuccess($this->__('Feed generation has been reset.'));
        } else {
            $this->_getSession()->addNotice($this->__('Feed is not stuck or has not exceeded the timeout.'));
        }

        $this->_redirect('*/*/edit', ['id' => $id]);
    }

    /**
     * Force cleanup any running jobs for a feed
     */
    protected function _forceCleanupRunningJobs(int $feedId): void
    {
        /** @var Maho_FeedManager_Model_Resource_Log_Collection $collection */
        $collection = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter($feedId)
            ->addFieldToFilter('status', Maho_FeedManager_Model_Log::STATUS_RUNNING);

        foreach ($collection as $log) {
            $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                ->setCompletedAt(Mage_Core_Model_Locale::now())
                ->addError('Cancelled - new generation started')
                ->save();
        }

        // Clean up state files
        $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
        if (is_dir($tmpDir)) {
            foreach (glob($tmpDir . "/feed_{$feedId}_*.state.json") as $stateFile) {
                $tmpFile = str_replace('.state.json', '.tmp', $stateFile);
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
                @unlink($stateFile);
            }
        }
    }

    public function downloadAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find feed.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $feed = Mage::getModel('feedmanager/feed')->load($id);

            if (!$feed->getId()) {
                $this->_getSession()->addError($this->__('This feed no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }

            $filePath = $feed->getOutputFilePath();
            $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
            $validPath = \Maho\Io::validatePath($filePath, $outputDir);

            if ($validPath === false) {
                $this->_getSession()->addError($this->__('Feed file not found. Please generate the feed first.'));
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }

            $extension = $feed->getFileFormat();
            if ($feed->getGzipCompression()) {
                $extension .= '.gz';
            }
            $this->_prepareDownloadResponse(
                $feed->getFilename() . '.' . $extension,
                ['type' => 'filename', 'value' => $validPath],
                'application/octet-stream',
            );

        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
        }
    }

    public function massGenerateAction(): void
    {
        $feedIds = $this->getRequest()->getParam('feed_ids');

        if (!is_array($feedIds)) {
            $this->_getSession()->addError($this->__('Please select feeds to generate.'));
            $this->_redirect('*/*/');
            return;
        }

        $generated = 0;
        $failed = 0;

        foreach ($feedIds as $feedId) {
            try {
                $feed = Mage::getModel('feedmanager/feed')->load($feedId);
                if ($feed->getId()) {
                    $generator = new Maho_FeedManager_Model_Generator();
                    $log = $generator->generate($feed);

                    if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                        $generated++;
                    } else {
                        $failed++;
                    }
                }
            } catch (Exception $e) {
                $failed++;
                Mage::logException($e);
            }
        }

        if ($generated) {
            $this->_getSession()->addSuccess($this->__('%d feed(s) generated successfully.', $generated));
        }
        if ($failed) {
            $this->_getSession()->addError($this->__('%d feed(s) failed to generate.', $failed));
        }

        $this->_redirect('*/*/');
    }

    public function massStatusAction(): void
    {
        $feedIds = $this->getRequest()->getParam('feed_ids');
        $status = (int) $this->getRequest()->getParam('status');

        if (!is_array($feedIds)) {
            $this->_getSession()->addError($this->__('Please select feeds.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            foreach ($feedIds as $feedId) {
                $feed = Mage::getModel('feedmanager/feed')->load($feedId);
                if ($feed->getId()) {
                    $feed->setIsEnabled($status)->save();
                }
            }

            $this->_getSession()->addSuccess(
                $this->__('%d feed(s) have been updated.', count($feedIds)),
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $feedIds = $this->getRequest()->getParam('feed_ids');

        if (!is_array($feedIds)) {
            $this->_getSession()->addError($this->__('Please select feeds to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            foreach ($feedIds as $feedId) {
                $feed = Mage::getModel('feedmanager/feed')->load($feedId);
                if ($feed->getId()) {
                    $feed->delete();
                }
            }

            $this->_getSession()->addSuccess(
                $this->__('%d feed(s) have been deleted.', count($feedIds)),
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function logsGridAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $feed = Mage::getModel('feedmanager/feed')->load($id);
        Mage::register('current_feed', $feed);

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * AJAX action to initialize batch generation
     */
    public function generateInitAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed ID required')]);
            return;
        }

        try {
            $feed = Mage::getModel('feedmanager/feed')->load($id);

            if (!$feed->getId()) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed not found')]);
                return;
            }

            // Check if already running
            $force = (bool) $this->getRequest()->getParam('force');
            if (Maho_FeedManager_Model_Generator::isGenerating($id)) {
                if ($force) {
                    // Force cleanup any stuck running jobs
                    $this->_forceCleanupRunningJobs($id);
                } else {
                    $this->_sendJsonResponse([
                        'error' => true,
                        'message' => $this->__('Feed is already being generated.'),
                        'status' => Maho_FeedManager_Model_Generator::getGenerationStatus($id),
                    ]);
                    return;
                }
            }

            // Initialize batch generation
            $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
            $result = $batchGenerator->initBatch($feed);

            $this->_sendJsonResponse([
                'success' => true,
                'job_id' => $result['job_id'],
                'log_id' => $result['log_id'],
                'total_products' => $result['total_products'],
                'batch_size' => $result['batch_size'],
                'batches_total' => $result['batches_total'],
                'message' => $this->__('Initialized generation for %d products', $result['total_products']),
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX action to process a single batch
     */
    public function generateBatchAction(): void
    {
        $jobId = $this->getRequest()->getParam('job_id');

        if (!$jobId) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Job ID required')]);
            return;
        }

        try {
            $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
            $result = $batchGenerator->processBatch($jobId);

            $this->_sendJsonResponse($result);

        } catch (Exception $e) {
            $this->_sendJsonResponse([
                'status' => 'failed',
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX action to finalize batch generation
     */
    public function generateFinalizeAction(): void
    {
        $jobId = $this->getRequest()->getParam('job_id');

        if (!$jobId) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Job ID required')]);
            return;
        }

        try {
            $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
            $result = $batchGenerator->finalize($jobId);

            $this->_sendJsonResponse($result);

        } catch (Exception $e) {
            $this->_sendJsonResponse([
                'status' => 'failed',
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX action to cancel batch generation
     */
    public function generateCancelAction(): void
    {
        $jobId = $this->getRequest()->getParam('job_id');

        if (!$jobId) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Job ID required')]);
            return;
        }

        try {
            $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
            $result = $batchGenerator->cancel($jobId);

            $this->_sendJsonResponse($result);

        } catch (Exception $e) {
            $this->_sendJsonResponse([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX action to force reset a stuck generation
     */
    public function forceResetAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed ID required')]);
            return;
        }

        try {
            // Mark all running logs for this feed as failed
            /** @var Maho_FeedManager_Model_Resource_Log_Collection $collection */
            $collection = Mage::getResourceModel('feedmanager/log_collection')
                ->addFeedFilter($id)
                ->addFieldToFilter('status', Maho_FeedManager_Model_Log::STATUS_RUNNING);

            $count = 0;
            foreach ($collection as $log) {
                /** @var Maho_FeedManager_Model_Log $log */
                $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                    ->addError('Manually reset by user')
                    ->save();
                $count++;
            }

            // Clean up any temp/state files for this feed
            $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
            if (is_dir($tmpDir)) {
                foreach (glob($tmpDir . "/feed_{$id}_*") as $file) {
                    unlink($file);
                }
            }

            $this->_sendJsonResponse([
                'success' => true,
                'message' => $this->__('Reset %d running job(s)', $count),
            ]);

        } catch (Exception $e) {
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX action to get batch job status
     */
    public function generateStatusAction(): void
    {
        $jobId = $this->getRequest()->getParam('job_id');

        if (!$jobId) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Job ID required')]);
            return;
        }

        try {
            $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
            $result = $batchGenerator->getStatus($jobId);

            $this->_sendJsonResponse($result);

        } catch (Exception $e) {
            $this->_sendJsonResponse([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mass batch generate action - initializes batch generation for multiple feeds
     */
    public function massBatchGenerateAction(): void
    {
        $feedIds = $this->getRequest()->getParam('feed_ids');

        if (!is_array($feedIds)) {
            $this->_sendJsonResponse([
                'error' => true,
                'message' => $this->__('Please select feeds to generate.'),
            ]);
            return;
        }

        $jobs = [];
        $errors = [];

        foreach ($feedIds as $feedId) {
            try {
                $feed = Mage::getModel('feedmanager/feed')->load($feedId);
                if ($feed->getId()) {
                    // Check if already running
                    if (Maho_FeedManager_Model_Generator::isGenerating((int) $feedId)) {
                        $errors[] = $this->__("Feed '%s' is already being generated", $feed->getName());
                        continue;
                    }

                    $batchGenerator = new Maho_FeedManager_Model_Generator_Batch();
                    $result = $batchGenerator->initBatch($feed);

                    $jobs[] = [
                        'feed_id' => $feedId,
                        'feed_name' => $feed->getName(),
                        'job_id' => $result['job_id'],
                        'total_products' => $result['total_products'],
                        'batches_total' => $result['batches_total'],
                    ];
                }
            } catch (Exception $e) {
                $errors[] = $this->__('Failed to initialize feed %s: %s', $feedId, $e->getMessage());
                Mage::logException($e);
            }
        }

        $this->_sendJsonResponse([
            'success' => !empty($jobs),
            'jobs' => $jobs,
            'errors' => $errors,
            'message' => $this->__('Initialized %d feed(s) for generation', count($jobs)),
        ]);
    }

    /**
     * AJAX action for manual upload
     */
    public function uploadAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed ID required')]);
            return;
        }

        try {
            /** @var Maho_FeedManager_Model_Feed $feed */
            $feed = Mage::getModel('feedmanager/feed')->load($id);

            if (!$feed->getId()) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed not found')]);
                return;
            }

            $destinationId = (int) $feed->getDestinationId();
            if (!$destinationId) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('No destination configured for this feed')]);
                return;
            }

            // Check if file exists
            $filePath = $feed->getOutputFilePath();
            if (!file_exists($filePath)) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Feed file not found. Please generate the feed first.')]);
                return;
            }

            /** @var Maho_FeedManager_Model_Destination $destination */
            $destination = Mage::getModel('feedmanager/destination')->load($destinationId);

            if (!$destination->getId()) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Destination not found')]);
                return;
            }

            if (!$destination->isEnabled()) {
                $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Destination is disabled')]);
                return;
            }

            // Perform upload
            $uploader = new Maho_FeedManager_Model_Uploader($destination);
            $extension = $feed->getFileFormat();
            if ($feed->getGzipCompression()) {
                $extension .= '.gz';
            }
            $remoteName = $feed->getFilename() . '.' . $extension;
            $success = $uploader->upload($filePath, $remoteName);

            // Update destination last upload info
            $destination->setLastUploadAt(Mage_Core_Model_Locale::now())
                ->setLastUploadStatus($success ? 'success' : 'failed')
                ->save();

            // Get or create log entry for this upload
            /** @var Maho_FeedManager_Model_Resource_Log_Collection $logCollection */
            $logCollection = Mage::getResourceModel('feedmanager/log_collection')
                ->addFeedFilter($id)
                ->setOrder('started_at', 'DESC')
                ->setPageSize(1);

            /** @var Maho_FeedManager_Model_Log $log */
            $log = $logCollection->getFirstItem();

            if ($success) {
                $message = $this->__('Uploaded to %s as %s', $destination->getName(), $remoteName);
                if ($log->getId()) {
                    $log->recordUploadSuccess($destinationId, $message);
                }
                $this->_sendJsonResponse([
                    'success' => true,
                    'message' => $message,
                ]);
            } else {
                $message = $this->__('Upload failed');
                if ($log->getId()) {
                    $log->recordUploadFailure($destinationId, $message);
                }
                $this->_sendJsonResponse([
                    'error' => true,
                    'message' => $message,
                ]);
            }

        } catch (Exception $e) {
            Mage::logException($e);
            $this->_sendJsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * New condition HTML action (AJAX)
     * Used by the Rule conditions builder to add new conditions
     */
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('feedmanager/feed'))
            ->setPrefix('conditions');

        if (!empty($typeArr[1])) {
            /** @phpstan-ignore argument.type (Condition models expect string attribute code) */
            $model->setAttribute($typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }

        $this->getResponse()->setBody($html);
    }
}
