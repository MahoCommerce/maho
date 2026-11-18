<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_NotFound_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    private bool $redirectJoined = false;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/notFound');
    }

    /**
     * Add the target path of the URL rewrite that redirects the path, as redirect_target. A rewrite of the store view
     * wins over a rewrite of store 0, as in the URL rewrite lookup. It is null when the rewrite that the lookup picks
     * does not redirect, or when no rewrite exists. Add the ID of the rewrite of the store view as url_rewrite_id.
     */
    public function addRedirectTarget(): static
    {
        if (!$this->redirectJoined) {
            $rewriteTable = $this->getTable('core/url_rewrite');
            $this->getSelect()
                ->joinLeft(
                    ['rewrite' => $rewriteTable],
                    'rewrite.request_path = main_table.request_path AND rewrite.store_id = main_table.store_id',
                    [],
                )
                ->joinLeft(
                    ['rewrite_default' => $rewriteTable],
                    'rewrite_default.request_path = main_table.request_path AND rewrite_default.store_id = '
                        . Mage_Core_Model_App::ADMIN_STORE_ID,
                    [],
                )
                ->columns([
                    'redirect_target' => new \Maho\Db\Expr(self::redirectTargetSql()),
                    'url_rewrite_id' => 'rewrite.url_rewrite_id',
                ]);
            $this->redirectJoined = true;
        }
        return $this;
    }

    /**
     * Keep only the paths that a URL rewrite redirects ($hasRedirect true) or only the other paths.
     */
    public function addRedirectFilter(bool $hasRedirect): static
    {
        $this->addRedirectTarget();
        $this->getSelect()->where('(' . self::redirectTargetSql() . ($hasRedirect ? ') IS NOT NULL' : ') IS NULL'));
        return $this;
    }

    private static function redirectTargetSql(): string
    {
        return 'CASE WHEN ' . self::redirectsSql('rewrite') . ' THEN rewrite.target_path'
            . ' WHEN rewrite.url_rewrite_id IS NULL AND ' . self::redirectsSql('rewrite_default')
            . ' THEN rewrite_default.target_path END';
    }

    /**
     * The storefront redirects a rewrite with the option R or RP, and a rewrite with a target that starts with
     * http:/ or https:, as Mage_Core_Model_Controller_Front_Observer::processRedirectOptions() does.
     */
    private static function redirectsSql(string $alias): string
    {
        return "({$alias}.options IN ('R', 'RP') OR {$alias}.target_path LIKE 'http:/%' OR {$alias}.target_path LIKE 'https:%')";
    }
}
