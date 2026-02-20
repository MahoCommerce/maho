<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract index process class
 * Predefine list of methods required by indexer
 *
 * @method Mage_Index_Model_Resource_Abstract _getResource()
 */
abstract class Mage_Index_Model_Indexer_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * @var array
     */
    protected $_matchedEntities = [];

    /**
     * Whether the indexer should be displayed on process/list page
     *
     * @var bool
     */
    protected $_isVisible = true;

    /**
     * Get Indexer name
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Get Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return '';
    }

    /**
     * Register indexer required data inside event object
     */
    abstract protected function _registerEvent(Mage_Index_Model_Event $event);

    /**
     * Process event based on event state data
     */
    abstract protected function _processEvent(Mage_Index_Model_Event $event);

    /**
     * Register data required by process in event object
     *
     * @return Mage_Index_Model_Indexer_Abstract
     */
    public function register(Mage_Index_Model_Event $event)
    {
        if ($this->matchEvent($event)) {
            $this->_registerEvent($event);
        }
        return $this;
    }

    /**
     * Process event
     *
     * @return  Mage_Index_Model_Indexer_Abstract
     */
    public function processEvent(Mage_Index_Model_Event $event)
    {
        if ($this->matchEvent($event)) {
            $this->_processEvent($event);
        }
        return $this;
    }

    /**
     * Check if event can be matched by process
     *
     * @return bool
     */
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        $entity = $event->getEntity();
        $type   = $event->getType();
        return $this->matchEntityAndType($entity, $type);
    }

    /**
     * Check if indexer matched specific entity and action type
     *
     * @param   string $entity
     * @param   string $type
     * @return  bool
     */
    public function matchEntityAndType($entity, $type)
    {
        if (!isset($this->_matchedEntities[$entity])) {
            return false;
        }

        if (in_array($type, $this->_matchedEntities[$entity])) {
            return true;
        }

        return false;
    }

    /**
     * Rebuild all index data
     */
    public function reindexAll()
    {
        $this->_getResource()->reindexAll();
    }

    /**
     * Try dynamically detect and call event handler from resource model.
     * Handler name will be generated from event entity and type code
     *
     * @return  Mage_Index_Model_Indexer_Abstract
     */
    public function callEventHandler(Mage_Index_Model_Event $event)
    {
        if ($event->getEntity()) {
            $method = $this->_camelize($event->getEntity() . '_' . $event->getType());
        } else {
            $method = $this->_camelize($event->getType());
        }

        $resourceModel = $this->_getResource();
        if (method_exists($resourceModel, $method)) {
            $resourceModel->$method($event);
        }
        return $this;
    }

    /**
     * Disable resource table keys
     *
     * @return Mage_Index_Model_Indexer_Abstract
     */
    public function disableKeys()
    {
        if (empty($this->_resourceName)) {
            return $this;
        }

        $resourceModel = $this->getResource();
        if ($resourceModel instanceof Mage_Index_Model_Resource_Abstract) {
            $resourceModel->disableTableKeys();
        }

        return $this;
    }

    /**
     * Enable resource table keys
     *
     * @return Mage_Index_Model_Indexer_Abstract
     */
    public function enableKeys()
    {
        if (empty($this->_resourceName)) {
            return $this;
        }

        $resourceModel = $this->getResource();
        if ($resourceModel instanceof Mage_Index_Model_Resource_Abstract) {
            $resourceModel->enableTableKeys();
        }

        return $this;
    }

    /**
     * Whether the indexer should be displayed on process/list page
     *
     * @return bool
     */
    public function isVisible()
    {
        return $this->_isVisible;
    }

    public function reindexEntity(int|array $entityIds): self
    {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }

        // Check if this indexer supports product entities at all
        if (!$this->matchEntityAndType(Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_SAVE)
            && !$this->matchEntityAndType(Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_MASS_ACTION)) {
            // This indexer doesn't handle product entities, skip silently
            return $this;
        }

        // Try resource-level reindexing first (more reliable)
        $resourceModel = $this->_getResource();

        if (method_exists($resourceModel, 'reindexProductIds')) {
            $resourceModel->reindexProductIds($entityIds);
        } elseif (method_exists($resourceModel, 'reindexEntities')) {
            $resourceModel->reindexEntities($entityIds);
        } elseif (method_exists($resourceModel, 'reindexProducts')) {
            $resourceModel->reindexProducts($entityIds);
        } elseif ($this->matchEntityAndType(Mage_Catalog_Model_Product::ENTITY, Mage_Index_Model_Event::TYPE_MASS_ACTION)) {
            // Create comprehensive mass action data object that all indexers expect
            $actionObject = new class ($entityIds) extends \Maho\DataObject {
                private array $productIds;

                public function __construct(array $productIds)
                {
                    parent::__construct();
                    $this->productIds = $productIds;
                }

                public function getProductIds(): array
                {
                    return $this->productIds;
                }

                public function getAttributesData(): array
                {
                    return ['force_reindex_required' => true];
                }

                public function getWebsiteIds(): null
                {
                    return null;
                }

                public function getActionType(): null
                {
                    return null;
                }
            };

            $event = Mage::getModel('index/event')
                ->setEntity(Mage_Catalog_Model_Product::ENTITY)
                ->setType(Mage_Index_Model_Event::TYPE_MASS_ACTION)
                ->setDataObject($actionObject);

            $this->processEvent($event);
            return $this;
        } else {
            // Final fallback: simulate individual save events
            foreach ($entityIds as $productId) {
                $product = Mage::getModel('catalog/product')->load($productId);
                if ($product->getId()) {
                    $event = Mage::getModel('index/event')
                        ->setEntity(Mage_Catalog_Model_Product::ENTITY)
                        ->setType(Mage_Index_Model_Event::TYPE_SAVE)
                        ->setDataObject($product);

                    $this->_processEvent($event);
                }
                unset($product); // Free memory immediately
            }
        }

        return $this;
    }
}
