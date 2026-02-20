<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Rss
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rss_Model_Rss
{
    protected array $_feedArray = [];

    public function _addHeader(array $data = []): self
    {
        $this->_feedArray = $data;
        return $this;
    }

    public function _addEntries(array $entries): self
    {
        $this->_feedArray['entries'] = $entries;
        return $this;
    }

    public function _addEntry(array $entry): self
    {
        $this->_feedArray['entries'][] = $entry;
        return $this;
    }

    public function getFeedArray(): array
    {
        return $this->_feedArray;
    }

    /**
     * Generate RSS 2.0 XML from feed array
     */
    public function createRssXml(): string
    {
        try {
            $feed = $this->getFeedArray();

            $dom = new DOMDocument('1.0', $feed['charset'] ?? 'UTF-8');
            $dom->formatOutput = true;

            // Create root <rss> element
            $rss = $dom->createElement('rss');
            $rss->setAttribute('version', '2.0');
            $dom->appendChild($rss);

            // Create <channel> element
            $channel = $dom->createElement('channel');
            $rss->appendChild($channel);

            // Add channel metadata
            $this->addTextElement($dom, $channel, 'title', $feed['title'] ?? '');
            $this->addTextElement($dom, $channel, 'link', $feed['link'] ?? '');
            $this->addTextElement($dom, $channel, 'description', $feed['description'] ?? '', true);

            if (!empty($feed['language'])) {
                // Convert locale code (en_US) to ISO-639 language code (en)
                $language = str_contains($feed['language'], '_')
                    ? substr($feed['language'], 0, strpos($feed['language'], '_'))
                    : $feed['language'];
                $this->addTextElement($dom, $channel, 'language', $language);
            }

            // Add items
            if (!empty($feed['entries']) && is_array($feed['entries'])) {
                foreach ($feed['entries'] as $entry) {
                    $this->addItem($dom, $channel, $entry);
                }
            }

            return $dom->saveXML();
        } catch (Exception $e) {
            return Mage::helper('rss')->__('Error in processing xml. %s', $e->getMessage());
        }
    }

    private function addTextElement(DOMDocument $dom, DOMElement $parent, string $name, string $value, bool $useCdata = false): void
    {
        $element = $dom->createElement($name);
        if ($useCdata) {
            $element->appendChild($dom->createCDATASection($value));
        } else {
            $element->appendChild($dom->createTextNode($value));
        }
        $parent->appendChild($element);
    }

    /**
     * Add an RSS item to the channel
     */
    private function addItem(DOMDocument $dom, DOMElement $channel, array $entry): void
    {
        $item = $dom->createElement('item');
        $channel->appendChild($item);

        if (!empty($entry['title'])) {
            $this->addTextElement($dom, $item, 'title', $entry['title']);
        }

        if (!empty($entry['link'])) {
            $this->addTextElement($dom, $item, 'link', $entry['link']);
        }

        if (!empty($entry['description'])) {
            $this->addTextElement($dom, $item, 'description', $entry['description'], true);
        }

        // Optional fields
        if (!empty($entry['author'])) {
            $this->addTextElement($dom, $item, 'author', $entry['author']);
        }

        // Add guid (use explicit guid or fallback to link)
        $guid = $entry['guid'] ?? $entry['link'] ?? '';
        if (!empty($guid)) {
            $this->addTextElement($dom, $item, 'guid', $guid);
        }

        if (!empty($entry['pubDate'])) {
            $this->addTextElement($dom, $item, 'pubDate', $entry['pubDate']);
        }
    }
}
