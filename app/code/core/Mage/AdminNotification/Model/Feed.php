<?php

/**
 * Maho
 *
 * @package    Mage_AdminNotification
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_AdminNotification_Model_Feed extends Mage_Core_Model_Abstract
{
    public const XML_FEED_URL_PATH     = 'system/adminnotification/feed_url';
    public const XML_FREQUENCY_PATH    = 'system/adminnotification/frequency';

    /**
     * Feed url
     *
     * @var string|null
     */
    protected $_feedUrl;

    /**
     * Init model
     */
    #[\Override]
    protected function _construct() {}

    /**
     * Retrieve feed url
     *
     * @return string
     */
    public function getFeedUrl()
    {
        if (is_null($this->_feedUrl)) {
            $this->_feedUrl = Mage::getStoreConfig(self::XML_FEED_URL_PATH);
        }
        return $this->_feedUrl;
    }

    /**
     * Check feed for modification
     *
     * @return $this
     */
    public function checkUpdate()
    {
        if (($this->getFrequency() + $this->getLastUpdate()) > time()) {
            return $this;
        }

        $feedData = [];
        $feedXml = $this->getFeedData();
        if ($feedXml && $feedXml->channel && $feedXml->channel->item) {
            foreach ($feedXml->channel->item as $item) {
                $feedData[] = [
                    'severity'      => (int) $item->severity,
                    'date_added'    => $this->getDate((string) $item->pubDate),
                    'title'         => (string) $item->title,
                    'description'   => (string) $item->description,
                    'url'           => (string) $item->link,
                ];
            }

            if ($feedData) {
                Mage::getModel('adminnotification/inbox')->parse(array_reverse($feedData));
            }
        }
        $this->setLastUpdate();

        return $this;
    }

    /**
     * Retrieve DB date from RSS date
     *
     * @param string $rssDate
     * @return string YYYY-MM-DD YY:HH:SS
     */
    public function getDate($rssDate)
    {
        return gmdate(Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT, strtotime($rssDate));
    }

    /**
     * Retrieve Update Frequency
     *
     * @return int
     */
    public function getFrequency()
    {
        return Mage::getStoreConfig(self::XML_FREQUENCY_PATH) * 3600;
    }

    /**
     * Retrieve Last update time
     *
     * @return string|false
     */
    public function getLastUpdate()
    {
        return Mage::app()->loadCache('admin_notifications_lastcheck');
    }

    /**
     * Set last update time (now)
     *
     * @return $this
     */
    public function setLastUpdate()
    {
        Mage::app()->saveCache(time(), 'admin_notifications_lastcheck');
        return $this;
    }

    /**
     * Retrieve feed data as XML element
     *
     * @return SimpleXMLElement|false
     */
    public function getFeedData()
    {
        $client = \Maho\Http\Client::create(['timeout' => 2]);

        try {
            $response = $client->request('GET', $this->getFeedUrl());
            $data = trim($response->getContent());
        } catch (Exception $e) {
            return false;
        }

        try {
            $xml  = new SimpleXMLElement($data);
        } catch (Exception $e) {
            return false;
        }

        return $xml;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getFeedXml()
    {
        try {
            $data = $this->getFeedData();
            $xml  = new SimpleXMLElement($data);
        } catch (Exception $e) {
            $xml  = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><feed />');
        }

        return $xml;
    }
}
