<?php

/**
 * Prepare and count the product searches by text, for the storefront, the API and GraphQL.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

declare(strict_types=1);

use Mage_CatalogSearch_Model_Query as Query;

class Mage_CatalogSearch_Service_Query
{
    /**
     * Return the search query of the catalogsearch helper in the store view $storeId. The search layer and the
     * fulltext collection read the text from the helper, so the transport puts the text into the request first.
     * A text shorter than the minimum length gets no ID: the search runs, and log() does not count it.
     */
    public function prepare(int $storeId): Query
    {
        $helper = Mage::helper('catalogsearch');
        $query = $helper->getQuery();
        $query->setStoreId($storeId);
        if ($query->getQueryText() != '' && $helper->isMinQueryLength()) {
            $query->setId(0)
                ->setIsActive()
                ->setIsProcessed();
        }
        return $query;
    }

    /**
     * Return the URL that the search term of $query sends the shopper to, or null.
     */
    public function getRedirect(Query $query): ?string
    {
        if (Mage::helper('catalogsearch')->isMinQueryLength()) {
            return null;
        }
        return $query->getRedirect() ?: null;
    }

    /**
     * Count one search of $query in the Search Terms report, while the client is inside the budget of the search
     * log. A text shorter than the minimum length is not counted.
     */
    public function log(Query $query, ?int $numResults = null): void
    {
        $helper = Mage::helper('catalogsearch');
        if ($query->getQueryText() == '' || $helper->isMinQueryLength() || !$helper->canLogQuery()) {
            return;
        }
        $query->setPopularity($query->getId() ? $query->getPopularity() + 1 : 1);
        if ($numResults !== null) {
            $query->setNumResults($numResults);
        }
        $query->save();
    }
}
