<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

declare(strict_types=1);

class Mage_Tag_ListController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/tag/list', name: 'tag.list.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
