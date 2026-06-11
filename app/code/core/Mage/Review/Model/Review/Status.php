<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

class Mage_Review_Model_Review_Status extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('review/review_status');
    }
}
