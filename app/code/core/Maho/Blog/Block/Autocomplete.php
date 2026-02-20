<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Blog_Block_Autocomplete extends Mage_Core_Block_Template
{
    protected ?Maho_Blog_Model_Resource_Post_Collection $_blogCollection = null;

    public function getBlogCollection(): ?Maho_Blog_Model_Resource_Post_Collection
    {
        if ($this->_blogCollection === null) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = Mage::helper('catalogsearch');
            $query = $helper->getQueryText();

            $collection = Mage::getModel('blog/post')->getCollection();
            if (!$collection instanceof Maho_Blog_Model_Resource_Post_Collection) {
                return null;
            }

            // Apply search filter based on configured search type
            $searchType = (int) Mage::getStoreConfig(Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_TYPE);
            $searchSeparator = strtoupper(Mage::getStoreConfig(Mage_CatalogSearch_Model_Fulltext::XML_PATH_CATALOG_SEARCH_SEPARATOR));

            // Get max query words and min query length from configuration
            $maxQueryWords = (int) Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MAX_QUERY_WORDS);
            $minQueryLength = (int) Mage::getStoreConfig(Mage_CatalogSearch_Model_Query::XML_PATH_MIN_QUERY_LENGTH);

            // Split query into words and filter by minimum length
            $words = Mage::helper('core/string')->splitWords($query, true, $maxQueryWords);
            if ($words) {
                $words = array_filter($words, function ($word) use ($minQueryLength) {
                    return strlen($word) >= $minQueryLength;
                });
            }

            // Build search conditions for title and content
            $searchConditions = [];

            // If no valid words remain, fall back to full phrase search
            if (!$words) {
                $searchConditions[] = ['like' => "%{$query}%"];
            } elseif ($searchType === Mage_CatalogSearch_Model_Fulltext::SEARCH_TYPE_COMBINE) {
                // COMBINE: match full phrase OR individual words
                if ($searchSeparator === 'OR') {
                    $searchConditions[] = ['like' => "%{$query}%"];
                    foreach ($words as $word) {
                        if ($word !== $query) {
                            $searchConditions[] = ['like' => "%{$word}%"];
                        }
                    }
                } else {
                    // With AND separator, just use full phrase (simpler than complex AND logic)
                    $searchConditions[] = ['like' => "%{$query}%"];
                }
            } else {
                // FULLTEXT and LIKE: search with individual words
                if ($searchSeparator === 'AND') {
                    // AND logic: all words must be present - use full phrase for simplicity
                    $searchConditions[] = ['like' => "%{$query}%"];
                } else {
                    // OR logic: any word can match
                    foreach ($words as $word) {
                        $searchConditions[] = ['like' => "%{$word}%"];
                    }
                }
            }

            // Apply search filter - search in both title and content
            // Since title and content are static attributes, we need to use raw SQL
            $adapter = $collection->getConnection();
            $conditions = [];

            foreach ($searchConditions as $condition) {
                $value = $condition['like'];
                $conditions[] = 'e.title LIKE ' . $adapter->quote($value) . ' OR e.content LIKE ' . $adapter->quote($value);
            }

            if (count($conditions) > 0) {
                $operator = ($searchSeparator === 'AND') ? ' AND ' : ' OR ';
                $whereCondition = '(' . implode($operator, $conditions) . ')';
                $collection->getSelect()->where($whereCondition);
            }

            // Only show active posts
            $collection->addFieldToFilter('is_active', 1);

            // Only show published posts (current date >= publish_date or publish_date is null)
            $now = Mage_Core_Model_Locale::now();
            $collection->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $now);

            // Apply store filter
            $collection->addStoreFilter(Mage::app()->getStore()->getId());

            // Limit results based on configuration
            $limit = (int) Mage::getStoreConfig('blog/search/autosuggest_limit');
            if ($limit <= 0) {
                $limit = 3; // Default fallback
            }
            $collection->setPageSize($limit);

            // Order by publish date (newest first), then by title
            $collection->setOrder('publish_date', 'DESC')
                ->setOrder('title', 'ASC');

            $this->_blogCollection = $collection;
        }

        return $this->_blogCollection;
    }

    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag('blog/search/enable_autosuggest');
    }

    public function getBlogUrl(Maho_Blog_Model_Post $post): string
    {
        return $post->getUrl();
    }

    /**
     * Get formatted publish date for display
     */
    public function getFormattedPublishDate(Maho_Blog_Model_Post $post): string
    {
        $publishDate = $post->getPublishDate();
        if (!$publishDate) {
            return '';
        }

        $date = new DateTime($publishDate);
        return $date->format('M j, Y');
    }


    #[\Override]
    protected function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $blogPosts = $this->getBlogCollection();
        if (count($blogPosts) === 0) {
            return '';
        }

        $html = '<div class="blog-results">';
        $html .= '<div class="blog-results-title">' . Mage::helper('blog')->__('Blog Posts') . '</div>';
        $html .= '<ul class="blog-list">';

        foreach ($blogPosts as $post) {
            $html .= '<li class="blog-item">';
            $html .= '<a href="' . $this->escapeUrl($this->getBlogUrl($post)) . '">';
            $html .= '<div class="blog-title">' . $this->escapeHtml($post->getTitle()) . '</div>';

            $publishDate = $this->getFormattedPublishDate($post);
            if ($publishDate) {
                $html .= '<div class="blog-date">' . $this->escapeHtml($publishDate) . '</div>';
            }

            $excerpt = Mage::helper('blog')->truncateContent($post, 80);
            if ($excerpt) {
                $html .= '<div class="blog-excerpt">' . $this->escapeHtml($excerpt) . '</div>';
            }

            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
