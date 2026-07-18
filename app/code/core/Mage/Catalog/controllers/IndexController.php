<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/catalog', name: 'catalog.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->_redirect('/');
    }
}
