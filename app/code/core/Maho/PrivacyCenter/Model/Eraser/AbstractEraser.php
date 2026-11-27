<?php

/**
 * Removes or anonymizes one kind of personal data of a subject, for an erasure. The config node
 * global/privacycenter/erasers lists the erasers, in the order in which they run.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

abstract class Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    /**
     * What the eraser removes, for a person: the deletion page of the customer account lists it.
     */
    abstract public function getLabel(): string;

    /**
     * Erase the data of $subject. Return a short line for the admin note of the request, such as "3 carts deleted".
     */
    abstract public function erase(Maho_PrivacyCenter_Model_Subject $subject): string;

    protected function getConnection(): \Maho\Db\Adapter\AdapterInterface
    {
        return Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    protected function getTable(string $alias): string
    {
        return Mage::getSingleton('core/resource')->getTableName($alias);
    }
}
