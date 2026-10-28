<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 customAttributes of CMS pages
 *
 * @group write
 */

const CMS_FIELD_TEXT_CODE = 'pest_api_campaign';
const CMS_FIELD_MULTI_CODE = 'pest_api_seasons';

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
    $typeId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY)->getId();

    Mage::getModel('cms/attribute')->setData([
        'entity_type_id' => $typeId,
        'attribute_code' => CMS_FIELD_TEXT_CODE,
        'backend_type' => 'static',
        'frontend_input' => 'text',
        'frontend_label' => 'Campaign',
        'is_user_defined' => 1,
    ])->save();

    Mage::getModel('cms/attribute')->setData([
        'entity_type_id' => $typeId,
        'attribute_code' => CMS_FIELD_MULTI_CODE,
        'backend_type' => 'static',
        'frontend_input' => 'multiselect',
        'frontend_label' => 'Seasons',
        'is_user_defined' => 1,
        'option' => ['value' => ['o1' => ['Spring'], 'o2' => ['Summer']], 'order' => ['o1' => 1, 'o2' => 2]],
    ])->save();
    Mage::app()->cleanCache();
    Mage::getSingleton('eav/config')->clear();

    $attribute = Mage::getModel('cms/attribute')->loadByCode(Mage_Cms_Model_Page::ENTITY, CMS_FIELD_MULTI_CODE);
    $GLOBALS['_cms_field_season_ids'] = array_map(strval(...), array_column($attribute->getSource()->getAllOptions(false), 'value'));
});

afterAll(function (): void {
    cleanupTestData();
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('cms/page');
        foreach ([CMS_FIELD_TEXT_CODE, CMS_FIELD_MULTI_CODE] as $code) {
            $attribute = Mage::getModel('cms/attribute')->loadByCode(Mage_Cms_Model_Page::ENTITY, $code);
            if ($attribute->getId()) {
                $attribute->delete();
            }
            if ($write->tableColumnExists($table, $code)) {
                $write->dropColumn($table, $code);
            }
        }
        $write->resetDdlCache($table);
        Mage::app()->cleanCache();
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('CMS page customAttributes (REST)', function (): void {

    it('writes the fields of a page, reads them back, and hides them from a guest', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/read']);
        $seasons = $GLOBALS['_cms_field_season_ids'];

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'pest-cms-field-' . uniqid(),
            'title' => 'Pest CMS Field Page',
            'content' => '<p>Body</p>',
            'isActive' => true,
            'stores' => ['all'],
            'customAttributes' => [CMS_FIELD_TEXT_CODE => 'SPRING26', CMS_FIELD_MULTI_CODE => $seasons],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = (int) $create['json']['id'];
        trackCreated('cms_page', $pageId);

        $read = apiGet("/api/rest/v2/cms-pages/{$pageId}", $token);
        expect($read['json']['customAttributes'][CMS_FIELD_TEXT_CODE] ?? null)->toBe('SPRING26')
            ->and((string) ($read['json']['customAttributes'][CMS_FIELD_MULTI_CODE] ?? ''))->toBe(implode(',', $seasons));

        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", ['customAttributes' => [CMS_FIELD_TEXT_CODE => null]], $token);
        expect($update['status'])->toBe(200)
            ->and($update['json']['customAttributes'] ?? [])->not->toHaveKey(CMS_FIELD_TEXT_CODE)
            ->and($update['json']['customAttributes'] ?? [])->toHaveKey(CMS_FIELD_MULTI_CODE);

        $guest = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($guest['json']['customAttributes'] ?? null)->toBeNull();
    });

    it('refuses a code that is not a page attribute, and a value that is not an option', function (): void {
        $response = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'pest-cms-field-bad-' . uniqid(),
            'title' => 'Pest CMS Field Bad Page',
            'content' => '<p>Body</p>',
            'stores' => ['all'],
            'customAttributes' => ['title' => 'x', CMS_FIELD_MULTI_CODE => ['999999']],
        ], serviceToken(['cms-pages/write']));
        if (in_array($response['status'], [200, 201], true)) {
            trackCreated('cms_page', (int) $response['json']['id']);
        }

        $fields = array_column($response['json']['details']['errors'] ?? [], 'field');
        expect($response['status'])->toBe(422)
            ->and($fields)->toContain('customAttributes.title')
            ->and($fields)->toContain('customAttributes.' . CMS_FIELD_MULTI_CODE);
    });

});
