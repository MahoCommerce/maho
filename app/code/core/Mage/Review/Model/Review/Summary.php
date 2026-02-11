<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @method $this setStoreId(int $value)
 */

class Mage_Review_Model_Review_Summary extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('review/review_summary');
    }

    /**
     * @return string
     */
    public function getEntityPkValue()
    {
        return $this->_getData('entity_pk_value');
    }

    /**
     * @return array
     */
    public function getRatingSummary()
    {
        return $this->_getData('rating_summary');
    }

    /**
     * @return int
     */
    public function getReviewsCount()
    {
        return $this->_getData('reviews_count');
    }
}
