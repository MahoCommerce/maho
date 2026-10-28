<?php

/**
 * A custom attribute of CMS pages.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

/**
 * The attribute is static: its values are a column of cms_page with the attribute code as its name.
 * The first save adds the column, and a delete keeps it, so the pages keep their values.
 */
class Mage_Cms_Model_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    /**
     * Data keys of a page that are not columns of cms_page, and the request keys of the admin page form.
     */
    public const RESERVED_CODES = ['stores', 'store_id', 'form_key', 'back'];

    #[\Override]
    protected $_eventPrefix = 'cms_page_attribute';

    /**
     * Every input type stores its values in a column of cms_page.
     */
    #[\Override]
    public function getAdminPageSettings(): ?array
    {
        return [
            'acl' => 'system/attributes/cms_page',
            'title' => Mage::helper('cms')->__('CMS Page Attributes'),
            'sets_title' => null,
        ];
    }

    #[\Override]
    public function getBackendTypeByInput($type)
    {
        return 'static';
    }

    /**
     * The options of a dropdown or a multiple select are in the EAV option tables.
     */
    #[\Override]
    public function getDefaultSourceModel(): string
    {
        return 'eav/entity_attribute_source_table';
    }

    /**
     * Return the column definition of the values of this attribute.
     *
     * @return array{type: string, length: int|string|null, nullable: true, comment: string}
     */
    public function getColumnDefinition(): array
    {
        [$type, $length] = match ($this->getFrontendInput()) {
            'textarea', 'multiselect' => [Maho\Db\Ddl\Table::TYPE_TEXT, 65536],
            'date' => [Maho\Db\Ddl\Table::TYPE_DATE, null],
            'select', 'boolean' => [Maho\Db\Ddl\Table::TYPE_INTEGER, null],
            default => [Maho\Db\Ddl\Table::TYPE_TEXT, 255],
        };
        return [
            'type' => $type,
            'length' => $length,
            'nullable' => true,
            'comment' => 'Page attribute ' . $this->getAttributeCode(),
        ];
    }

    /**
     * @throws Mage_Core_Exception when the code of a new attribute is a column that the CMS schema declares, or a reserved code
     */
    #[\Override]
    protected function _beforeSave()
    {
        $code = (string) $this->getAttributeCode();
        if ($this->isObjectNew() && in_array($code, [...$this->getDeclaredColumns(), ...self::RESERVED_CODES], true)) {
            Mage::throwException(Mage::helper('cms')->__('The code "%s" is already used by CMS pages. Use another code.', $code));
        }
        return parent::_beforeSave();
    }

    /**
     * Add the column after the commit, since DDL cannot run in a transaction.
     */
    #[\Override]
    public function afterCommitCallback()
    {
        parent::afterCommitCallback();
        if ($this->getIsUserDefined()) {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $table = $resource->getTableName('cms/page');
            if (!$connection->tableColumnExists($table, (string) $this->getAttributeCode())) {
                $connection->addColumn($table, (string) $this->getAttributeCode(), $this->getColumnDefinition());
            }
            $connection->resetDdlCache($table);
        }
        return $this;
    }

    /**
     * @return list<string>
     */
    protected function getDeclaredColumns(): array
    {
        [$schema] = \Maho\Db\Schema\Collector::collect();
        $columns = [];
        foreach ($schema->getTable(\Maho\Db\Schema\Collector::tablePrefix() . 'cms_page')->getColumns() as $column) {
            $columns[] = $column->getObjectName()->getIdentifier()->getValue();
        }
        return $columns;
    }
}
