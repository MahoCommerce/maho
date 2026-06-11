<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

/**
 * @method array getFormData()
 * @method $this setFormData(array $value)
 * @method array getRedirectUrl()
 * @method $this setRedirectUrl(string $value)
 */

class Mage_Review_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('review');
    }
}
