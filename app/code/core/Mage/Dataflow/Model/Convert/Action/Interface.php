<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

interface Mage_Dataflow_Model_Convert_Action_Interface
{
    /**
     * Run current action
     *
     * @return Mage_Dataflow_Model_Convert_Action_Abstract
     */
    public function run();

    public function setProfile(Mage_Dataflow_Model_Convert_Profile_Interface $profile): static;

    public function importXml(\Maho\Simplexml\Element $actionNode): static;

    public function getParam(string $key, mixed $default = null): mixed;

    public function setParam(array|string $key, mixed $value = null): static;

    public function getContainer(string|int|null $name = null): Mage_Dataflow_Model_Convert_Container_Interface;

    public function setContainer(Mage_Dataflow_Model_Convert_Container_Interface $container): static;
}
