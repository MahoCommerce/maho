<?php

/**
 * Blog posts keyed by url key and store set; skipped when the blog module is off.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class BlogPosts extends AbstractCmsImporter
{
    private const TEXT_COLUMNS = ['title', 'image', 'meta_title', 'meta_keywords', 'meta_description', 'short_content'];

    /** @var array<string, \Mage_Eav_Model_Entity_Attribute_Abstract>|null */
    private ?array $customAttributes = null;

    /** @var array<int, list<string>> */
    private array $setAttributeCodes = [];

    /** @var array<string, int> */
    private array $setIds = [];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['url_key', 'stores'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        if (!$this->enabled()) {
            return [];
        }
        $rows = [];
        foreach ($file as $line => $row) {
            $urlKey = $this->requireValue($file, $line, $row, 'url_key');
            $this->requireValue($file, $line, $row, 'stores');
            if (!preg_match(\Maho_Blog_Model_Post::URL_KEY_PATTERN, $urlKey)) {
                $this->fail($file, $line, "url_key '$urlKey' must be lowercase letters, digits and dashes");
            }
            $row['store_ids'] = $this->storeIds($file, $line, $row);
            $row['is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_active'] ?? '', true));
            if (($row['publish_date'] ?? '') !== '' && !Mage::helper('core')->isValidDate($row['publish_date'])) {
                $this->fail($file, $line, "publish_date '{$row['publish_date']}' is not a date");
            }
            // An inactive post is still the same post, and so is one on any of the row's stores:
            // a rerun updates it instead of adding a twin
            $row['post_id'] = null;
            foreach ($row['store_ids'] as $storeId) {
                $row['post_id'] = Mage::getModel('blog/post')->getPostIdByUrlKey($urlKey, $storeId, null);
                if ($row['post_id'] !== null) {
                    break;
                }
            }
            $row['body'] = $this->content($file, $line, $row, $options, $row['post_id'] === null);
            if ($row['post_id'] === null && ($row['title'] ?? '') === '') {
                $this->fail($file, $line, 'title is required for a new post');
            }
            $row['attribute_set_id'] = ($row['_attribute_set'] ?? '') !== '' ? $this->attributeSetId($file, $line, $row['_attribute_set']) : null;
            $row['custom'] = [];
            $setCodes = null;
            foreach ($this->customAttributes() as $code => $attribute) {
                if (($row[$code] ?? '') === '') {
                    continue;
                }
                // The nightly eav_clean_orphaned_records cron deletes a value whose attribute is not in the set
                $setCodes ??= $this->setAttributeCodes($row['attribute_set_id'] ?? $this->postSetId($row['post_id']));
                if (!in_array($code, $setCodes, true)) {
                    $this->fail($file, $line, "$code is not in the attribute set of the post");
                }
                $row['custom'][$code] = $this->customValue($file, $line, $attribute, $row[$code]);
            }
            // A condition reads the option IDs of the file, and the status as the save writes it, 0 or 1
            $post = new \Maho\DataObject(['is_active' => (int) $row['is_active']] + $row['custom']);
            // The save gives an attribute that the file leaves out its default value, and a condition reads that value
            foreach ($this->customAttributes() as $code => $attribute) {
                if (!isset($row['custom'][$code])) {
                    $attribute->getBackend()->beforeSave($post);
                }
            }
            foreach ($this->customAttributes() as $code => $attribute) {
                // A new post needs each required attribute of its set, unless the attribute has a default value
                if ($row['post_id'] === null && !isset($row['custom'][$code]) && $attribute->getIsRequired()
                    && (string) $attribute->getDefaultValue() === '' && !Mage::helper('eav')->isHidden($post, $attribute)
                    && in_array($code, $setCodes ??= $this->setAttributeCodes($row['attribute_set_id'] ?? $this->postSetId(null)), true)
                ) {
                    $this->fail($file, $line, "$code is required for a new post");
                }
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        if (!$this->enabled()) {
            $result->notices[] = 'the blog module is disabled, ' . basename($file->getPath()) . ' was skipped';
            return $result;
        }
        /** @var \Maho_Blog_Service_Post $service */
        $service = Mage::getService('blog/post');
        foreach ($rows as $row) {
            if ($row['post_id'] !== null) {
                $post = $service->getById($row['post_id']);
                $result->updated++;
            } else {
                $post = $service->newPost();
                $result->created++;
            }
            $post->setUrlKey($row['url_key'])->setIsActive($row['is_active'] ? 1 : 0)->setStores($row['store_ids']);
            foreach (self::TEXT_COLUMNS as $column) {
                if (($row[$column] ?? '') !== '') {
                    $post->setData($column, $row[$column]);
                }
            }
            if (($row['publish_date'] ?? '') !== '') {
                $post->setPublishDate($row['publish_date']);
            }
            if ($row['body'] !== null) {
                $post->setContent($row['body']);
            }
            if ($row['attribute_set_id'] !== null) {
                $post->setAttributeSetId($row['attribute_set_id']);
            }
            foreach ($row['custom'] as $code => $value) {
                $post->setData($code, $value);
            }
            $service->save($post);
        }
        $reporter->info(count($rows) . ' posts');
        return $result;
    }

    /**
     * The user-defined attributes of blog posts, keyed by code. Each one can be a column of the file.
     *
     * @return array<string, \Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    private function customAttributes(): array
    {
        return $this->customAttributes ??= \Maho\ApiPlatform\Service\CustomAttributes::userDefined(\Maho_Blog_Model_Post::ENTITY);
    }

    private function attributeSetId(CsvFile $file, int $line, string $name): int
    {
        $type = Mage::getSingleton('eav/config')->getEntityType(\Maho_Blog_Model_Post::ENTITY);
        $id = $this->setIds[$name] ??= (int) Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter((int) $type->getId())
            ->addFieldToFilter('attribute_set_name', $name)
            ->getFirstItem()
            ->getId();
        if (!$id) {
            $this->fail($file, $line, "_attribute_set '$name' is not a set of blog posts");
        }
        return $id;
    }

    private function postSetId(?int $postId): int
    {
        $resource = Mage::getResourceSingleton('blog/post');
        $adapter = $resource->getReadConnection();
        $setId = $postId !== null ? (int) $adapter->fetchOne($adapter->select()
            ->from($resource->getEntityTable(), ['attribute_set_id'])
            ->where($resource->getEntityIdField() . ' = ?', $postId)) : 0;
        return $setId ?: (int) Mage::getSingleton('eav/config')->getEntityType(\Maho_Blog_Model_Post::ENTITY)->getDefaultAttributeSetId();
    }

    /**
     * @return list<string>
     */
    private function setAttributeCodes(int $setId): array
    {
        $this->setAttributeCodes[$setId] ??= array_values(array_map(strval(...), Mage::getResourceModel('eav/entity_attribute_collection')
            ->setAttributeSetFilter($setId)
            ->getColumnValues('attribute_code')));
        return $this->setAttributeCodes[$setId];
    }

    private function enabled(): bool
    {
        return Mage::helper('core')->isModuleEnabled('Maho_Blog');
    }
}
