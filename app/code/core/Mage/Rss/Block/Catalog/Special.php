<?php

/**
 * Maho
 *
 * @package    Mage_Rss
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rss_Block_Catalog_Special extends Mage_Rss_Block_Catalog_Abstract
{
    /**
     * DateTime object for date comparisons
     *
     * @var DateTime|null
     */
    protected static $_currentDate = null;

    /**
     * @throws Mage_Core_Model_Store_Exception
     * @throws Exception
     */
    #[\Override]
    protected function _construct()
    {
        /*
        * setting cache to save the rss for 10 minutes
        */
        $this->setCacheKey('rss_catalog_special_' . $this->_getStoreId() . '_' . $this->_getCustomerGroupId());
        $this->setCacheLifetime(600);
    }

    /**
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     * @throws Exception
     */
    #[\Override]
    protected function _toHtml()
    {
        //store id is store view id
        $storeId = $this->_getStoreId();
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();

        //customer group id
        $customerGroupId = $this->_getCustomerGroupId();

        $product = Mage::getModel('catalog/product');

        $fields = [
            'final_price',
            'price',
        ];
        $specials = $product->setStoreId($storeId)->getResourceCollection()
            ->addPriceDataFieldFilter('%s < %s', $fields)
            ->addPriceData($customerGroupId, $websiteId)
            ->addAttributeToSelect(
                [
                    'name', 'short_description', 'description', 'price', 'thumbnail',
                    'special_price', 'special_to_date',
                    'msrp_enabled', 'msrp_display_actual_price_type', 'msrp',
                ],
                'left',
            )
            ->addAttributeToSort('name', 'asc')
        ;

        $newurl = Mage::getUrl('rss/catalog/special/store_id/' . $storeId);
        $title = Mage::helper('rss')->__('%s - Special Products', Mage::app()->getStore()->getFrontendName());
        $lang = Mage::getStoreConfig('general/locale/code');

        $rssObj = Mage::getModel('rss/rss');
        $data = ['title' => $title,
            'description' => $title,
            'link'        => $newurl,
            'charset'     => 'UTF-8',
            'language'    => $lang,
        ];
        $rssObj->_addHeader($data);

        /** @var array[] $results */
        $results = [];

        /*
        using resource iterator to load the data one by one
        instead of loading all at the same time. loading all data at the same time can cause the big memory allocation.
        */
        Mage::getSingleton('core/resource_iterator')->walk(
            $specials->getSelect(),
            [[$this, 'addSpecialXmlCallback']],
            ['rssObj' => $rssObj, 'results' => &$results],
        );

        if (count($results)) {
            /** @var Mage_Catalog_Helper_Image $imageHelper */
            $imageHelper = $this->helper('catalog/image');
            /** @var Mage_Catalog_Helper_Output $outputHelper */
            $outputHelper = $this->helper('catalog/output');

            foreach ($results as $result) {
                // render a row for RSS feed
                $product->setData($result);
                $html = sprintf(
                    '<table><tr>
                    <td><a href="%s"><img src="%s" alt="" border="0" align="left" height="75" width="75" /></a></td>
                    <td style="text-decoration:none;">%s',
                    $product->getProductUrl(),
                    $imageHelper->init($product, 'thumbnail')->resize(75, 75),
                    $outputHelper->productAttribute(
                        $product,
                        $product->getDescription(),
                        'description',
                    ),
                );

                // add price data if needed
                if ($product->getAllowedPriceInRss()) {
                    if (Mage::helper('catalog')->canApplyMsrp($product)) {
                        $html .= '<br/><a href="' . $product->getProductUrl() . '">'
                            . $this->__('Click for price') . '</a>';
                    } else {
                        $special = '';
                        if ($result['use_special']) {
                            $special = '<br />' . Mage::helper('catalog')->__('Special Expires On: %s', $this->formatDate($result['special_to_date'], Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM));
                        }
                        $html .= sprintf(
                            '<p>%s %s%s</p>',
                            Mage::helper('catalog')->__('Price: %s', Mage::helper('core')->currency($result['price'])),
                            Mage::helper('catalog')->__('Special Price: %s', Mage::helper('core')->currency($result['final_price'])),
                            $special,
                        );
                    }
                }

                $html .= '</td></tr></table>';

                $rssObj->_addEntry([
                    'title'       => $product->getName(),
                    'link'        => $product->getProductUrl(),
                    'description' => $html,
                ]);
            }
        }
        return $rssObj->createRssXml();
    }

    /**
     * Preparing data and adding to rss object
     *
     * @param array $args
     */
    public function addSpecialXmlCallback($args)
    {
        if (!isset(self::$_currentDate)) {
            self::$_currentDate = new DateTime();
        }

        // dispatch event to determine whether the product will eventually get to the result
        $product = new \Maho\DataObject(['allowed_in_rss' => true, 'allowed_price_in_rss' => true]);
        $args['product'] = $product;
        Mage::dispatchEvent('rss_catalog_special_xml_callback', $args);
        if (!$product->getAllowedInRss()) {
            return;
        }

        // add row to result and determine whether special price is active (less or equal to the final price)
        $row = $args['row'];
        $row['use_special'] = false;
        $row['allowed_price_in_rss'] = $product->getAllowedPriceInRss();
        if (isset($row['special_to_date']) && $row['final_price'] <= $row['special_price']
            && $row['allowed_price_in_rss']
        ) {
            $specialToDate = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $row['special_to_date']);
            if ($specialToDate && self::$_currentDate <= $specialToDate) {
                $row['use_special'] = true;
            }
        }

        $args['results'][] = $row;
    }

    /**
     * Function for comparing two items in collection
     *
     * @param \Maho\DataObject $a
     * @param \Maho\DataObject $b
     * @return int
     */
    public function sortByStartDate($a, $b)
    {
        return $b['start_date'] <=> $a['start_date'];
    }
}
