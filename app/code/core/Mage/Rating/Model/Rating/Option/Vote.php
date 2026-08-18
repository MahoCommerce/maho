<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

/**
 * @method Mage_Rating_Model_Resource_Rating_Option_Vote_Collection getResourceCollection()
 * @method $this setRatingOptions(Mage_Rating_Model_Resource_Rating_Option_Collection $options)
 */

class Mage_Rating_Model_Rating_Option_Vote extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('rating/rating_option_vote');
    }

    public function getEntityPkValue(): ?int
    {
        $value = $this->getData('entity_pk_value');
        return $value === null ? null : (int) $value;
    }

    public function getRatingId(): ?int
    {
        $value = $this->getData('rating_id');
        return $value === null ? null : (int) $value;
    }
}
