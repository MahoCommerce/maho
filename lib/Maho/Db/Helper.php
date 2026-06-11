<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db;

class Helper
{
    /**
     * Dictionary for generate short name
     *
     * @var array
     */
    protected static $_translateMap = [
        'address'       => 'addr',
        'admin'         => 'adm',
        'attribute'     => 'attr',
        'enterprise'    => 'ent',
        'catalog'       => 'cat',
        'category'      => 'ctgr',
        'customer'      => 'cstr',
        'notification'  => 'ntfc',
        'product'       => 'prd',
        'session'       => 'sess',
        'user'          => 'usr',
        'entity'        => 'entt',
        'datetime'      => 'dtime',
        'decimal'       => 'dec',
        'varchar'       => 'vchr',
        'index'         => 'idx',
        'compare'       => 'cmp',
        'bundle'        => 'bndl',
        'option'        => 'opt',
        'gallery'       => 'glr',
        'media'         => 'mda',
        'value'         => 'val',
        'link'          => 'lnk',
        'title'         => 'ttl',
        'super'         => 'spr',
        'label'         => 'lbl',
        'website'       => 'ws',
        'aggregat'      => 'aggr',
        'minimal'       => 'min',
        'inventory'     => 'inv',
        'status'        => 'sts',
        'agreement'     => 'agrt',
        'layout'        => 'lyt',
        'resource'      => 'res',
        'directory'     => 'dir',
        'downloadable'  => 'dl',
        'element'       => 'elm',
        'fieldset'      => 'fset',
        'checkout'      => 'chkt',
        'newsletter'    => 'nlttr',
        'shipping'      => 'shpp',
        'calculation'   => 'calc',
        'search'        => 'srch',
        'query'         => 'qr',
    ];

    /**
     * Convert name using dictionary
     */
    public static function shortName(string $name): string
    {
        return strtr($name, self::$_translateMap);
    }

    /**
     * Add or replace translate to dictionary
     */
    public static function addTranslate(string $from, string $to): void
    {
        self::$_translateMap[$from] = $to;
    }
}
