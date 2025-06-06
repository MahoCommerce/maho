<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Model_Observer
{
    public const OLD_INDEX_EVENT_THRESHOLD_SECONDS = 24 * 60 * 60;
    public const OLD_INDEX_EVENT_DELETE_COUNT = 1000;

    /**
     * Indexer model
     *
     * @var Mage_Index_Model_Indexer
     */
    protected $_indexer;

    public function __construct()
    {
        $this->_indexer = Mage::getSingleton('index/indexer');
    }

    /**
     * Store after commit observer. Process store related indexes
     *
     * @throws Throwable
     */
    public function processStoreSave(Varien_Event_Observer $observer)
    {
        $store = $observer->getEvent()->getStore();
        $this->_indexer->processEntityAction(
            $store,
            Mage_Core_Model_Store::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
    }

    /**
     * Store group after commit observer. Process store group related indexes
     *
     * @throws Throwable
     */
    public function processStoreGroupSave(Varien_Event_Observer $observer)
    {
        $storeGroup = $observer->getEvent()->getStoreGroup();
        $this->_indexer->processEntityAction(
            $storeGroup,
            Mage_Core_Model_Store_Group::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
    }

    /**
     * Website save after commit observer. Process website related indexes
     *
     * @throws Throwable
     */
    public function processWebsiteSave(Varien_Event_Observer $observer)
    {
        $website = $observer->getEvent()->getWebsite();
        $this->_indexer->processEntityAction(
            $website,
            Mage_Core_Model_Website::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
    }

    /**
     * Store after commit observer. Process store related indexes
     *
     * @throws Throwable
     */
    public function processStoreDelete(Varien_Event_Observer $observer)
    {
        $store = $observer->getEvent()->getStore();
        $this->_indexer->processEntityAction(
            $store,
            Mage_Core_Model_Store::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE,
        );
    }

    /**
     * Store group after commit observer. Process store group related indexes
     *
     * @throws Throwable
     */
    public function processStoreGroupDelete(Varien_Event_Observer $observer)
    {
        $storeGroup = $observer->getEvent()->getStoreGroup();
        $this->_indexer->processEntityAction(
            $storeGroup,
            Mage_Core_Model_Store_Group::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE,
        );
    }

    /**
     * Website save after commit observer. Process website related indexes
     *
     * @throws Throwable
     */
    public function processWebsiteDelete(Varien_Event_Observer $observer)
    {
        $website = $observer->getEvent()->getWebsite();
        $this->_indexer->processEntityAction(
            $website,
            Mage_Core_Model_Website::ENTITY,
            Mage_Index_Model_Event::TYPE_DELETE,
        );
    }

    /**
     * Config data after commit observer.
     *
     * @throws Throwable
     */
    public function processConfigDataSave(Varien_Event_Observer $observer)
    {
        $configData = $observer->getEvent()->getConfigData();
        $this->_indexer->processEntityAction(
            $configData,
            Mage_Core_Model_Config_Data::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
    }

    /**
     * Clean old index events for indexers in manual mode
     *
     * @return void
     * @throws Exception
     */
    public function cleanOutdatedEvents()
    {
        $manualIndexProcessCollection = Mage::getSingleton('index/indexer')
            ->getProcessesCollection()
            ->addFieldToFilter('mode', Mage_Index_Model_Process::MODE_MANUAL);

        $now = new DateTime();
        /** @noinspection PhpUnhandledExceptionInspection */
        $dateInterval = new DateInterval('PT' . self::OLD_INDEX_EVENT_THRESHOLD_SECONDS . 'S');
        $oldEventsThreshold = $now
            ->sub($dateInterval)
            ->format(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT);

        $coreResource = Mage::getSingleton('core/resource');
        $writeConnection = $coreResource->getConnection('core_write');
        $indexEventTableName = $coreResource->getTableName('index/event');

        /** @var Mage_Index_Model_Process $process */
        foreach ($manualIndexProcessCollection as $process) {
            $unprocessedEventsCollection = $process
                ->getUnprocessedEventsCollection()
                ->addFieldToFilter('created_at', ['lt' => $oldEventsThreshold])
                ->load();

            $i = 0;
            $eventList = [];
            /** @var Mage_Index_Model_Event $unprocessedEvent */
            foreach ($unprocessedEventsCollection as $unprocessedEvent) {
                $i++;
                $eventList[] = $unprocessedEvent->getId();
                if ($i === self::OLD_INDEX_EVENT_DELETE_COUNT) {
                    break;
                }
            }

            if (!empty($eventList)) {
                $where = new Zend_Db_Expr(
                    sprintf(
                        'event_id in (%s)',
                        implode(',', $eventList),
                    ),
                );
                $writeConnection->delete($indexEventTableName, $where);
            }
        }
    }
}
