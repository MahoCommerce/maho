<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

/**
 * @method Mage_Rating_Model_Resource_Rating_Option_Collection getResourceCollection()
 * @method Mage_Rating_Model_Resource_Rating_Option _getResource()
 * @method Mage_Rating_Model_Resource_Rating_Option getResource()
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

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getDoUpdate(): ?int
    {
        $value = $this->getData('do_update');
        return $value === null ? null : (int) $value;
    }

    public function setDoUpdate(?int $value): static
    {
        return $this->setData('do_update', $value);
    }

    public function getEntityPkValue(): ?string
    {
        $value = $this->getData('entity_pk_value');
        return $value === null ? null : (string) $value;
    }

    public function setEntityPkValue(?string $value): static
    {
        return $this->setData('entity_pk_value', $value);
    }

    public function setOptionId(?int $value): static
    {
        return $this->setData('option_id', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    public function getRatingId(): ?int
    {
        $value = $this->getData('rating_id');
        return $value === null ? null : (int) $value;
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

    public function getValue(): ?int
    {
        $value = $this->getData('value');
        return $value === null ? null : (int) $value;
    }

    public function setValue(?int $value): static
    {
        return $this->setData('value', $value);
    }

    public function getVoteId(): ?int
    {
        $value = $this->getData('vote_id');
        return $value === null ? null : (int) $value;
    }

    public function setVoteId(?int $value): static
    {
        return $this->setData('vote_id', $value);
    }

}
