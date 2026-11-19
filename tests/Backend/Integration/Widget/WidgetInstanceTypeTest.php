<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const WIDGET_TEST_DECLARED_TYPE = 'cms/widget_page_link';

function widgetTestInstance(string $type): Mage_Widget_Model_Widget_Instance
{
    /** @var Mage_Widget_Model_Widget_Instance $instance */
    $instance = Mage::getModel('widget/widget_instance');
    $instance->setType($type)
        ->setPackageTheme('base/default')
        ->setTitle('Widget type test')
        ->setStoreIds([0]);
    return $instance;
}

/**
 * Insert a widget instance with one page group and one layout update, as an older release could store them.
 *
 * @return array{instance_id: int, page_id: int, layout_update_id: int}
 */
function widgetTestInsertStoredInstance(string $type, string $xml): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->insert($resource->getTableName('widget/widget_instance'), [
        'instance_type' => $type,
        'package_theme' => 'base/default',
        'title' => 'Widget cleanup test',
        'store_ids' => '0',
    ]);
    $instanceId = (int) $adapter->lastInsertId($resource->getTableName('widget/widget_instance'));

    $adapter->insert($resource->getTableName('widget/widget_instance_page'), [
        'instance_id' => $instanceId,
        'page_group' => 'all_pages',
        'layout_handle' => 'default',
        'block_reference' => 'content',
        'page_for' => 'all',
        'entities' => '',
        'page_template' => '',
    ]);
    $pageId = (int) $adapter->lastInsertId($resource->getTableName('widget/widget_instance_page'));

    $adapter->insert($resource->getTableName('core/layout_update'), ['handle' => 'default', 'xml' => $xml]);
    $layoutUpdateId = (int) $adapter->lastInsertId($resource->getTableName('core/layout_update'));

    $adapter->insert($resource->getTableName('widget/widget_instance_page_layout'), [
        'page_id' => $pageId,
        'layout_update_id' => $layoutUpdateId,
    ]);
    $adapter->insert($resource->getTableName('core/layout_link'), [
        'store_id' => 0,
        'area' => 'frontend',
        'package' => 'base',
        'theme' => 'default',
        'layout_update_id' => $layoutUpdateId,
    ]);

    return ['instance_id' => $instanceId, 'page_id' => $pageId, 'layout_update_id' => $layoutUpdateId];
}

/**
 * @param array{instance_id: int, page_id: int, layout_update_id: int} $ids
 */
function widgetTestDeleteStoredInstance(array $ids): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $where = ['layout_update_id = ?' => $ids['layout_update_id']];
    $adapter->delete($resource->getTableName('core/layout_link'), $where);
    $adapter->delete($resource->getTableName('widget/widget_instance_page_layout'), $where);
    $adapter->delete($resource->getTableName('core/layout_update'), $where);
    $adapter->delete($resource->getTableName('widget/widget_instance_page'), ['page_id = ?' => $ids['page_id']]);
    $adapter->delete($resource->getTableName('widget/widget_instance'), ['instance_id = ?' => $ids['instance_id']]);
}

function widgetTestLayoutUpdateExists(int $layoutUpdateId): bool
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (bool) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('core/layout_update'), ['layout_update_id'])
            ->where('layout_update_id = ?', $layoutUpdateId),
    );
}

describe('widget type lookup', function () {
    it('finds a type that a widget.xml declares', function () {
        expect(Mage::getSingleton('widget/widget')->getXmlElementByType(WIDGET_TEST_DECLARED_TYPE))->not->toBeNull();
    });

    it('does not put the type into an XPath expression', function (string $type) {
        expect(Mage::getSingleton('widget/widget')->getXmlElementByType($type))->toBeNull();
    })->with([
        'or clause' => ['x" or @type!="'],
        'any node' => ['x"] | //*[@type'],
    ]);
});

describe('widget instance with a type that no widget.xml declares', function () {
    it('cannot be saved', function (string $type) {
        $instance = widgetTestInstance($type);
        try {
            expect(fn() => $instance->save())->toThrow(Mage_Core_Exception::class, 'Wrong widget type specified.');
        } finally {
            if ($instance->getId()) {
                $instance->delete();
            }
        }
    })->with([
        'block type that is not a widget' => ['core/text'],
        'type with markup' => [WIDGET_TEST_DECLARED_TYPE . '"><action method="setText"><text>x</text></action><block type="core/text'],
    ]);

    it('does not pass validation', function () {
        expect(widgetTestInstance('core/text')->validate())->toBe('Wrong widget type specified.');
    });

    it('gets no layout update', function () {
        $type = WIDGET_TEST_DECLARED_TYPE . '" template="page/html/head.phtml';
        expect(widgetTestInstance($type)->generateLayoutUpdateXml('content'))->toBe('');
    });
});

describe('generateLayoutUpdateXml()', function () {
    it('escapes the names and the values of the widget parameters', function () {
        $name = 'a</name></action><action method="setTemplate"><name>b';
        $value = '<script>alert(1)</script>';
        $instance = widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)
            ->setWidgetParameters([$name => $value]);

        $xml = $instance->generateLayoutUpdateXml('content');
        $document = new DOMDocument();

        expect($document->loadXML('<root>' . $xml . '</root>'))->toBeTrue();
        $actions = $document->getElementsByTagName('action');
        expect($actions->length)->toBe(1)
            ->and($actions->item(0)->getAttribute('method'))->toBe('setData')
            ->and($document->getElementsByTagName('name')->item(0)->textContent)->toBe($name)
            ->and($document->getElementsByTagName('value')->item(0)->textContent)->toBe($value)
            ->and($document->getElementsByTagName('block')->item(0)->getAttribute('type'))->toBe(WIDGET_TEST_DECLARED_TYPE);
    });

    it('refuses a block reference with markup characters', function () {
        expect(fn() => widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)->generateLayoutUpdateXml('content"><x a="'))
            ->toThrow(Mage_Core_Exception::class);
    });
});

describe('page group validation', function () {
    it('returns the data of a valid page group', function () {
        $data = ['page_id' => 0, 'for' => 'all', 'block' => 'content', 'template' => ''];
        expect(widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)->validatePageGroup(['page_group' => 'all_pages', 'all_pages' => $data]))
            ->toBe($data);
    });

    it('returns null for an entry without data', function (mixed $pageGroup) {
        expect(widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)->validatePageGroup($pageGroup))->toBeNull();
    })->with([
        'not an array' => ['all_pages'],
        'no code' => [['all_pages' => []]],
        'no data for the code' => [['page_group' => 'all_pages']],
    ]);

    it('refuses a page group that is not valid', function (array $pageGroup, string $message) {
        expect(fn() => widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)->validatePageGroup($pageGroup))
            ->toThrow(Mage_Core_Exception::class, $message);
    })->with([
        'unknown code' => [['page_group' => 'bogus', 'bogus' => ['for' => 'all', 'block' => 'content']], 'Wrong widget display option specified.'],
        'code that is not a string' => [['page_group' => ['x'], 'x' => []], 'Wrong widget display option specified.'],
        'data that is not an array' => [['page_group' => 'all_pages', 'all_pages' => 'x'], 'Wrong widget display option specified.'],
        'unknown value of for' => [['page_group' => 'all_pages', 'all_pages' => ['for' => 'some', 'block' => 'content']], 'Wrong widget display option specified.'],
        'specific entities for all pages' => [['page_group' => 'all_pages', 'all_pages' => ['for' => 'specific', 'block' => 'content']], 'Wrong widget display option specified.'],
        'block with markup' => [['page_group' => 'all_pages', 'all_pages' => ['for' => 'all', 'block' => 'content"><x a="']], 'Templatepath or block reference contain special characters.'],
        'template with markup' => [['page_group' => 'all_pages', 'all_pages' => ['for' => 'all', 'block' => 'content', 'template' => 'a"><x a="']], 'Templatepath or block reference contain special characters.'],
    ]);

    it('refuses an unknown page group on save', function () {
        $instance = widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)
            ->setPageGroups([['page_group' => 'bogus', 'bogus' => ['page_id' => 0, 'for' => 'all', 'block' => 'content', 'template' => '']]]);
        try {
            expect(fn() => $instance->save())->toThrow(Mage_Core_Exception::class, 'Wrong widget display option specified.');
        } finally {
            if ($instance->getId()) {
                $instance->delete();
            }
        }
    });
});

describe('deleteUnexpectedLayoutUpdates()', function () {
    it('deletes the layout updates of an undeclared type and of an unexpected shape, and keeps the generated ones', function () {
        $generated = widgetTestInstance(WIDGET_TEST_DECLARED_TYPE)->generateLayoutUpdateXml('content');
        $undeclared = widgetTestInsertStoredInstance(
            'core/text',
            '<reference name="content"><block type="core/text" name="x"><action method="setText"><text>x</text></action></block></reference>',
        );
        $unexpected = widgetTestInsertStoredInstance(
            WIDGET_TEST_DECLARED_TYPE,
            '<reference name="content"><block type="' . WIDGET_TEST_DECLARED_TYPE . '" name="x"/></reference><reference name="head"><action method="addJs"><script>x.js</script></action></reference>',
        );
        $kept = widgetTestInsertStoredInstance(WIDGET_TEST_DECLARED_TYPE, $generated);

        try {
            $removed = [];
            Mage::getResourceSingleton('widget/widget_instance')->deleteUnexpectedLayoutUpdates(
                function (array $removals) use (&$removed): void {
                    foreach ($removals as $removal) {
                        $removed[$removal['layout_update_id']] = $removal['reason'];
                    }
                },
            );

            expect($removed[$undeclared['layout_update_id']] ?? null)->toBe(Mage_Widget_Model_Widget_Instance::LAYOUT_UPDATE_UNDECLARED_TYPE)
                ->and($removed[$unexpected['layout_update_id']] ?? null)->toBe(Mage_Widget_Model_Widget_Instance::LAYOUT_UPDATE_UNEXPECTED_XML)
                ->and($removed)->not->toHaveKey($kept['layout_update_id'])
                ->and(widgetTestLayoutUpdateExists($undeclared['layout_update_id']))->toBeFalse()
                ->and(widgetTestLayoutUpdateExists($unexpected['layout_update_id']))->toBeFalse()
                ->and(widgetTestLayoutUpdateExists($kept['layout_update_id']))->toBeTrue()
                ->and(Mage::getModel('widget/widget_instance')->load($undeclared['instance_id'])->getId())->not->toBeNull();
        } finally {
            widgetTestDeleteStoredInstance($undeclared);
            widgetTestDeleteStoredInstance($unexpected);
            widgetTestDeleteStoredInstance($kept);
        }
    });
});
