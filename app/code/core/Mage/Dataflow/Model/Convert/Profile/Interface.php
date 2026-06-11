<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

interface Mage_Dataflow_Model_Convert_Profile_Interface
{
    /**
     * Run current action
     *
     * @return Mage_Dataflow_Model_Convert_Profile_Abstract
     */
    public function run();
}
