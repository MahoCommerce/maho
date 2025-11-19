<?php

/**
 * Maho
 *
 * @package    Mage_Rating
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Rating_Model_Resource_Rating_Option_Collection getResourceCollection()
 * @method Mage_Rating_Model_Resource_Rating_Option _getResource()
 * @method Mage_Rating_Model_Resource_Rating_Option getResource()
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method int getDoUpdate()
 * @method $this setDoUpdate(int $value)
 * @method string getEntityPkValue()
 * @method $this setEntityPkValue(string $value)
 * @method $this setOptionId(int $value)
 * @method int getPosition()
 * @method $this setPosition(int $value)
 * @method int getRatingId()
 * @method $this setRatingId(int $value)
 * @method int getReviewId()
 * @method $this setReviewId(int $value)
 * @method int getValue()
 * @method $this setValue(int $value)
 * @method int getVoteId()
 * @method $this setVoteId(int $value)
 */
class Mage_Rating_Model_Rating_Option extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('rating/rating_option');
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function addVote()
    {
        $this->getResource()->addVote($this);
        return $this;
    }

    /**
     * @param int $id
     * @return $this
     */
    #[\Override]
    public function setId($id)
    {
        $this->setOptionId($id);
        return $this;
    }

    public function getLabel(): string
    {
        if ($this->getValue() == 1) {
            return Mage::helper('rating')->__('%d star', $this->getValue());
        }
        return Mage::helper('rating')->__('%d stars', $this->getValue());
    }
}
