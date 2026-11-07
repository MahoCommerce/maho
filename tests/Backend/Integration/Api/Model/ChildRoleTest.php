<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('saves an API child role one level below its parent', function (): void {
    $parent = Mage::getModel('api/roles')->setName('Parent test role')->setRoleType('G')->save();
    $child = Mage::getModel('api/roles')->setName('Child test role')->setRoleType('G');
    $grandchild = Mage::getModel('api/roles')->setName('Grandchild test role')->setRoleType('G');

    try {
        expect($parent->getTreeLevel())->toBe(1);
        $child->setData('pid', $parent->getId())->setParentId($parent->getId())->save();

        $loaded = Mage::getModel('api/roles')->load($child->getId());
        expect($loaded->getTreeLevel())->toBe(2)
            ->and($loaded->getRoleName())->toBe('Child test role')
            ->and($loaded->getParentId())->toBe($parent->getId());

        $grandchild->setData('pid', $child->getId())->setParentId($child->getId())->save();
        expect(Mage::getModel('api/roles')->load($grandchild->getId())->getTreeLevel())->toBe(3);
    } finally {
        if ($grandchild->getId()) {
            $grandchild->delete();
        }
        if ($child->getId()) {
            $child->delete();
        }
        $parent->delete();
    }
});
