<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
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
     */
    public function run(): static;

    public function addAction(?Mage_Dataflow_Model_Convert_Action_Interface $action = null): Mage_Dataflow_Model_Convert_Action_Interface;

    public function setContainers(Mage_Dataflow_Model_Convert_Container_Collection $containers): static;

    public function getContainer(string|int|null $name = null): Mage_Dataflow_Model_Convert_Container_Interface;

    public function addContainer(string|int|null $name, Mage_Dataflow_Model_Convert_Container_Interface $container): Mage_Dataflow_Model_Convert_Container_Interface;

    public function addException(Mage_Dataflow_Model_Convert_Exception $e): static;

    public function getDryRun(): ?bool;
}
