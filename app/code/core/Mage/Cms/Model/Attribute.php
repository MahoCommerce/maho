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
 * The first save adds the column. A delete drops the column and all its values.
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
     * Every page uses the default set, whose groups give the order and the titles of the fields.
     */
    #[\Override]
    public function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        return new Mage_Eav_Model_Entity_Attribute_AdminPageSettings(
            acl: 'system/attributes/cms_page',
            title: Mage::helper('cms')->__('CMS Page Attributes'),
            setsTitle: Mage::helper('cms')->__('CMS Page Attribute Set'),
            mainTab: 'adminhtml/cms_attribute_edit_tab_main',
            addToDefaultSet: true,
            singleSet: true,
        );
    }

    /**
     * A condition can also read the status and the layout of the page, which are columns of cms_page.
     */
    #[\Override]
    public function getDependencySources(): array
    {
        $helper = Mage::helper('cms');
        $layouts = [];
        foreach (Mage::getSingleton('page/source_layout')->toOptionArray() as $option) {
            $layouts[] = ['value' => (string) $option['value'], 'label' => (string) $option['label']];
        }
        return [
            'is_active' => ['label' => $helper->__('Status'), 'options' => [
                ['value' => '1', 'label' => $helper->__('Enabled')],
                ['value' => '0', 'label' => $helper->__('Disabled')],
            ]],
            'root_template' => ['label' => $helper->__('Layout'), 'options' => $layouts],
        ] + parent::getDependencySources();
    }

    #[\Override]
    public function getSetNote(Mage_Eav_Model_Entity_Attribute_Set $set): ?string
    {
        $helper = Mage::helper('cms');
        $lines = [$helper->__('Every CMS page uses this set. A page shows only the attributes of the set.')];
        $firstGroup = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter((int) $set->getId())
            ->setSortOrder()
            ->getFirstItem();
        if ($firstGroup->getId()) {
            $lines[] = $helper->__('The fields of the first group, %s, show on the Page Information tab. Each other group is a tab of its own. On the storefront, each group is a table.', (string) $firstGroup->getAttributeGroupName());
        }
        return implode("\n", $lines);
    }

    /**
     * The values are columns of cms_page, which the nightly cleanup does not read, so the pages keep them.
     */
    #[\Override]
    public function getSetRemoveQuestion(Mage_Eav_Model_Entity_Attribute_Set $set, array $attributes): ?string
    {
        if (!$attributes) {
            return null;
        }
        return Mage::helper('cms')->__('The CMS pages stop showing these fields: %s. The pages keep their values. Save the set?', self::getQuestionLabels($attributes));
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
     * @throws Mage_Core_Exception when the code of a new attribute is a column of cms_page, or a reserved code
     */
    #[\Override]
    protected function _beforeSave()
    {
        $code = (string) $this->getAttributeCode();
        $resource = Mage::getSingleton('core/resource');
        // isObjectNew() stays true after the first save of an attribute, so a second save looks new too
        if (!$this->getId()
            && (in_array($code, [...\Maho\Db\Schema\Collector::declaredColumns('cms_page'), ...self::RESERVED_CODES], true)
                || $resource->getConnection('core_read')->tableColumnExists($resource->getTableName('cms/page'), $code))
        ) {
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
     * Drop the column after the commit, since DDL cannot run in a transaction.
     */
    #[\Override]
    protected function _afterDeleteCommit()
    {
        parent::_afterDeleteCommit();
        if ($this->getIsUserDefined()) {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $table = $resource->getTableName('cms/page');
            $connection->dropColumn($table, (string) $this->getAttributeCode());
            $connection->resetDdlCache($table);
        }
        return $this;
    }
}
