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
     * Add the target path of the URL rewrite that has the same request path in the same store view, as
     * redirect_target. It is null when no rewrite exists.
     */
    public function addRedirectTarget(): static
    {
        if (!$this->redirectJoined) {
            $this->getSelect()->joinLeft(
                ['rewrite' => $this->getTable('core/url_rewrite')],
                'rewrite.request_path = main_table.request_path AND rewrite.store_id = main_table.store_id',
                ['redirect_target' => 'rewrite.target_path'],
            );
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
        $this->getSelect()->where($hasRedirect ? 'rewrite.url_rewrite_id IS NOT NULL' : 'rewrite.url_rewrite_id IS NULL');
        return $this;
    }
}
