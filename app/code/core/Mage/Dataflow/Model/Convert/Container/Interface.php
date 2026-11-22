<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

interface Mage_Dataflow_Model_Convert_Container_Interface
{
    public function getData(): mixed;

    public function setData(mixed $data): static;

    public function setProfile(Mage_Dataflow_Model_Convert_Profile_Interface $profile): static;

    public function setAction(Mage_Dataflow_Model_Convert_Action_Interface $action): static;

    public function setVar(array|string $key, mixed $value = null): static;

    public function addException(string $error, ?string $level = null): Mage_Dataflow_Model_Convert_Exception;
}
