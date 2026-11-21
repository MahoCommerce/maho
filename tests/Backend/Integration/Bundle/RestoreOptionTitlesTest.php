<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

use MahoCLI\Commands\LegacyFixBundleOptionTitles;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

it('restores missing bundle option titles from the chosen store view', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    $storeId = (int) $store->getId();
    $resources = Mage::getSingleton('core/resource');
    $write = $resources->getConnection('core_write');
    $table = $resources->getTableName('bundle/option_value');
    $write->beginTransaction();
    try {
        $attributeSetId = (int) Mage::getSingleton('eav/config')
            ->getEntityType('catalog_product')
            ->getDefaultAttributeSetId();
        $product = Mage::getModel('catalog/product')->setStoreId(0)
            ->setAttributeSetId($attributeSetId)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
            ->setSku('bundle-repair-' . bin2hex(random_bytes(6)))
            ->setName('Bundle title recovery test')
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
            ->setPrice(0)
            ->save();

        $optionIds = [];
        foreach (['Missing row', 'Empty title', 'Keep this title', 'No source', 'Out of scope'] as $title) {
            /** @var Mage_Bundle_Model_Option $option */
            $option = Mage::getModel('bundle/option');
            $option->setParentId((int) $product->getId())->setType('select')->setRequired(false)->setPosition(0)
                ->setStoreId(0)->setTitle($title)->save();
            $optionIds[] = (int) $option->getId();
        }
        [$missing, $empty, $existing, $noSource, $outOfScope] = $optionIds;
        $write->delete($table, ['option_id IN (?)' => [$missing, $noSource, $outOfScope], 'store_id = ?' => 0]);
        $write->update($table, ['title' => ' '], ['option_id = ?' => $empty, 'store_id = ?' => 0]);
        foreach ([
            $missing => 'Restored missing title',
            $empty => 'Restored empty title',
            $existing => 'Different store title',
            $outOfScope => 'Outside this repair',
        ] as $id => $title) {
            $write->insert($table, ['option_id' => $id, 'store_id' => $storeId, 'title' => $title]);
        }

        $defaults = $write->select()->from($table, ['option_id', 'title'])
            ->where('option_id IN (?)', $optionIds)->where('store_id = ?', 0)->order('option_id ASC');
        $sourceTitles = $write->fetchPairs($write->select()->from($table, ['option_id', 'title'])
            ->where('option_id IN (?)', $optionIds)->where('store_id = ?', $storeId)->order('option_id ASC'));
        $input = ['source-store' => $store->getCode(), '--option' => [$missing, $empty, $existing, $noSource]];
        $command = new LegacyFixBundleOptionTitles();
        new Application()->addCommand($command);

        $preview = new CommandTester($command)->run($input);
        $this->assertCommandIsSuccessful($preview);
        expect($preview->getOutput())->toContain('2 option title(s) would be restored', '1 option(s) have no title in the source store view')
            ->and($write->fetchPairs($defaults))->toBe([$empty => ' ', $existing => 'Keep this title']);

        $apply = new CommandTester($command)->run($input + ['--force' => true]);
        $this->assertCommandIsSuccessful($apply);
        expect($apply->getOutput())->toContain('2 option title(s) restored')
            ->and($write->fetchPairs($defaults))->toBe([
                $missing => 'Restored missing title',
                $empty => 'Restored empty title',
                $existing => 'Keep this title',
            ]);
        expect($write->fetchPairs($write->select()->from($table, ['option_id', 'title'])
            ->where('option_id IN (?)', $optionIds)->where('store_id = ?', $storeId)->order('option_id ASC')))->toBe($sourceTitles);

        $retry = new CommandTester($command)->run($input + ['--force' => true]);
        $this->assertCommandIsSuccessful($retry);
        expect($retry->getOutput())->toContain('0 option title(s) restored');
    } finally {
        $write->rollBack();
    }
});
