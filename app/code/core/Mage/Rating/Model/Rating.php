<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

/**
 * @method Mage_Rating_Model_Resource_Rating getResource()
 * @method Mage_Rating_Model_Resource_Rating _getResource()
 * @method Mage_Rating_Model_Resource_Rating_Collection getCollection()
 * @method Mage_Rating_Model_Resource_Rating_Collection getResourceCollection()
 *
 * @method $this setId(string $value)
 * @method bool hasRatingCodes()
 * @method bool hasStores()
 */
class Mage_Rating_Model_Rating extends Mage_Core_Model_Abstract
{
    /**
     * rating entity codes
     */
    public const ENTITY_PRODUCT_CODE           = 'product';
    public const ENTITY_PRODUCT_REVIEW_CODE    = 'product_review';
    public const ENTITY_REVIEW_CODE            = 'review';

    /**
     * Define resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('rating/rating');
    }

    /**
     * @param int $optionId
     * @param int|string $entityPkValue
     * @param int $customerId
     * @return $this
     */
    public function addOptionVote($optionId, $entityPkValue, $customerId = null)
    {
        Mage::getModel('rating/rating_option')->setOptionId($optionId)
            ->setRatingId($this->getId())
            ->setReviewId($this->getReviewId())
            ->setEntityPkValue($entityPkValue)
            ->setCustomerId($customerId)
            ->addVote();
        return $this;
    }

    /**
     * @param int $optionId
     * @return $this
     */
    public function updateOptionVote($optionId)
    {
        Mage::getModel('rating/rating_option')->setOptionId($optionId)
            ->setVoteId($this->getVoteId())
            ->setReviewId($this->getReviewId())
            ->setDoUpdate(1)
            ->addVote();
        return $this;
    }

    /**
     * retrieve rating options
     *
     * @return array
     */
    public function getOptions()
    {
        if ($options = $this->getData('options')) {
            return $options;
        }
        if ($id = $this->getId()) {
            return Mage::getResourceModel('rating/rating_option_collection')
               ->addRatingFilter($id)
               ->setPositionOrder()
               ->load()
               ->getItems();
        }
        return [];
    }

    /**
     * Get rating collection object
     *
     * @param string $entityPkValue
     * @param bool $onlyForCurrentStore
     * @return array|Mage_Rating_Model_Rating
     */

    public function getEntitySummary($entityPkValue, $onlyForCurrentStore = true)
    {
        $this->setEntityPkValue($entityPkValue);
        return $this->_getResource()->getEntitySummary($this, $onlyForCurrentStore);
    }

    /**
     * @param int $reviewId
     * @param bool $onlyForCurrentStore
     * @return array
     */
    public function getReviewSummary($reviewId, $onlyForCurrentStore = true)
    {
        $this->setReviewId($reviewId);
        return $this->_getResource()->getReviewSummary($this, $onlyForCurrentStore);
    }

    /**
     * Get rating entity type id by code
     *
     * @param string $entityCode
     * @return string
     */
    public function getEntityIdByCode($entityCode)
    {
        return $this->getResource()->getEntityIdByCode($entityCode);
    }

    public function setCount(?int $value): static
    {
        return $this->setData('count', $value);
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function setEntityId(?int $value): static
    {
        return $this->setData('entity_id', $value);
    }

    public function getEntityPkValue(): int|string|null
    {
        return $this->getData('entity_pk_value');
    }

    public function setEntityPkValue(int|string|null $value): static
    {
        return $this->setData('entity_pk_value', $value);
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getRatingCode(): ?string
    {
        $value = $this->getData('rating_code');
        return $value === null ? null : (string) $value;
    }

    public function setRatingCode(?string $value): static
    {
        return $this->setData('rating_code', $value);
    }

    public function getRatingCodes(): ?array
    {
        return $this->getData('rating_codes');
    }

    public function setRatingCodes(?array $value): static
    {
        return $this->setData('rating_codes', $value);
    }

    public function setRatingId(?int $value): static
    {
        return $this->setData('rating_id', $value);
    }

    public function getReviewId(): ?int
    {
        $value = $this->getData('review_id');
        return $value === null ? null : (int) $value;
    }

    public function setReviewId(?int $value): static
    {
        return $this->setData('review_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getStores(): ?array
    {
        return $this->getData('stores');
    }

    public function setStores(?array $value): static
    {
        return $this->setData('stores', $value);
    }

    public function setSum(?int $value): static
    {
        return $this->setData('sum', $value);
    }

    public function setSummary(float|int|null $value): static
    {
        return $this->setData('summary', $value);
    }

    public function getVoteId(): ?int
    {
        $value = $this->getData('vote_id');
        return $value === null ? null : (int) $value;
    }

}
