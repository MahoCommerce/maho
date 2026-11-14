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
     * Add the target path of the URL rewrite that has the same request path, as redirect_target. A rewrite of the
     * store view wins over a rewrite of store 0, as in the URL rewrite lookup. It is null when no rewrite exists.
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
                ->columns(['redirect_target' => new \Maho\Db\Expr('COALESCE(rewrite.target_path, rewrite_default.target_path)')]);
            $this->redirectJoined = true;
        }
        return $this;
    }

    /**
     * Keep only the paths that have a URL rewrite ($hasRedirect true) or only the paths without one.
     */
    public function addRedirectFilter(bool $hasRedirect): static
    {
        $this->addRedirectTarget();
        $this->getSelect()->where(
            $hasRedirect
                ? '(rewrite.url_rewrite_id IS NOT NULL OR rewrite_default.url_rewrite_id IS NOT NULL)'
                : '(rewrite.url_rewrite_id IS NULL AND rewrite_default.url_rewrite_id IS NULL)',
        );
        return $this;
    }
}
