<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

function aiPromptAdmin(): Mage_Admin_Model_User
{
    /** @var Mage_Admin_Model_User $admin */
    $admin = Mage::getModel('admin/user');
    $admin->setData(['username' => 'prompt_admin', 'firstname' => 'Pat', 'lastname' => 'Prompt']);

    return $admin;
}

it('describes the store and the administrator', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());

    expect($prompt)->toContain('Maho admin assistant');
    expect($prompt)->toContain('prompt_admin');
    expect($prompt)->toContain('Store views, as "code = name (website, locale)"');
    expect($prompt)->toContain(' (' . Mage::app()->getDefaultStoreView()->getWebsite()->getName() . ', ' . Mage::getStoreConfig('general/locale/code', Mage::app()->getDefaultStoreView()->getId()) . ')');
    expect($prompt)->toContain(Mage::app()->getDefaultStoreView()->getCode() . ' = ');
    expect($prompt)->toContain('Without that field, it is in ' . Mage::app()->getDefaultStoreView()->getBaseCurrencyCode());
    expect($prompt)->not->toContain('api_key');
});

it('gives the offset of the store timezone and its changes in the last year', function (): void {
    $zone = new DateTimeZone((string) Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE) ?: 'UTC');
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $changes = count($zone->getTransitions($now->modify('-1 year')->getTimestamp(), $now->getTimestamp())) - 1;

    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());

    expect($prompt)->toMatch('/- Store timezone: ' . preg_quote($zone->getName(), '/') . ', now UTC[+-]\\d{2}:\\d{2}\\. Current time: [\\d-]+ [\\d:]+ UTC, ' . $now->setTimezone($zone)->format('Y-m-d') . ' [\\d:]+ in the store\\./');
    expect(substr_count($prompt, ' UTC to UTC'))->toBe($changes);
});

it('names the record the admin page shows', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), [
        'route' => 'catalog_product/edit',
        'entity_type' => 'product',
        'entity_id' => 123,
        'entity_label' => 'Blue Shirt',
        'store' => 'default',
    ]);

    expect($prompt)->toContain('admin page "catalog_product/edit"');
    expect($prompt)->toContain('product with ID 123: "Blue Shirt"');
    expect($prompt)->toContain('store view "default"');
});

it('names the page of the last message when the administrator opened another page since', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), [
        'route' => 'sales_order_invoice/view',
        'entity_type' => 'invoice',
        'entity_id' => 274,
        'previous_page' => ['route' => 'sales_order/view', 'entity_type' => 'order', 'entity_id' => 302],
    ]);

    expect($prompt)->toContain('The last message came from the admin page "sales_order/view", which shows order with ID 302.');
    expect($prompt)->toContain('A different page now does not make an earlier answer wrong.');
    expect(new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['route' => 'sales_order_invoice/view']))->not->toContain('The last message came from');
});

it('leaves the page section out when no route is known', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['entity_type' => 'product', 'entity_id' => 1]);

    expect($prompt)->not->toContain('admin page');
    expect($prompt)->not->toContain('with ID 1');
});

it('describes what the administrator sees when the panel sends a screen digest', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), [
        'route' => 'cms_block/edit',
        'entity_type' => 'CMS block',
        'entity_id' => 7,
        'entity_label' => 'Footer',
        'store' => '',
        'screen' => "Page: Edit Block 'Footer'\nFields: Block Title = Footer; Content = <p>Hello</p> (rich text editor)\nEditor toolbar buttons: Bold, Insert Slideshow, Insert Widget",
    ]);

    expect($prompt)->toContain('What the administrator sees on that page now');
    expect($prompt)->toContain('Insert Slideshow');
});

it('points to the form tools when the panel sent the text fields of the open form', function (): void {
    $admin = aiPromptAdmin();
    $form = [['id' => 'page_content', 'name' => 'content', 'label' => 'Content', 'value' => '<p>Secret draft</p>', 'changed' => true]];
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit', 'form' => $form]);

    expect($prompt)->toContain('admin_read_form', 'admin_edit_text');
    expect($prompt)->not->toContain('Secret draft');
    expect(new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit']))->not->toContain('admin_read_form');
});

it('tells a scheduled run that its final answer goes to the admin inbox', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['run_mode' => 'schedule']);

    expect($prompt)->toContain('Your final answer goes to the admin inbox of the audience', 'Call notify only for a problem');
});

it('lists the editor layouts from the guide the panel sent', function (): void {
    $admin = aiPromptAdmin();
    $guide = "## Standard HTML the editor keeps\np\n\n## Directives\n{{var}}\n\n## Columns: 2 Columns\n<div></div>\n\n## Bento grid: Mosaic\n<div></div>";
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit', 'editor_guide' => $guide]);

    expect($prompt)->toContain('The content editor offers these layouts: Columns: 2 Columns, Bento grid: Mosaic.');
    expect($prompt)->not->toContain('<div></div>');
    expect(new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit']))->not->toContain('The content editor offers');
});

it('keeps its sections', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['route' => 'cms_page/edit', 'entity_type' => 'CMS page', 'entity_id' => 60, 'entity_label' => 'Maho Store', 'store' => '', 'screen' => '']);

    expect($prompt)->toContain('How a task runs:', 'Maho in short:', 'Example of a good turn', 'How to answer:');
});

it('names a field only when the model saw it, and never concludes that a field does not exist', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());

    expect($prompt)->toContain('Name a field, a tab, a button or a type of value only when you saw it');
    expect($prompt)->toContain('A field that you did not see can still exist');
});

it('answers for every website, or asks for the scope, only on an installation with several store views', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());
    $rule = 'a table with one row for each website';

    if (count(Mage::app()->getStores()) > 1) {
        expect($prompt)->toContain($rule);
    } else {
        expect($prompt)->not->toContain($rule);
    }
});

it('adds the custom instructions of the store owner before the answer rules, cut to their limit', function (): void {
    $store = Mage::app()->getStore();
    $previous = $store->getConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS);
    $store->setConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS, "  Always write product texts in a formal tone.\n" . str_repeat('x', Maho_Ai_Model_Chat_SystemPrompt::MAX_CUSTOM_CHARS));
    try {
        $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());
    } finally {
        $store->setConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS, $previous);
    }

    expect($prompt)->toContain("Instructions from the store owner, to follow in every conversation:\nAlways write product texts in a formal tone.");
    expect(strpos($prompt, 'Instructions from the store owner'))->toBeLessThan(strpos($prompt, 'How to answer:'));
    expect($prompt)->toContain(str_repeat('x', 1900));
    expect($prompt)->not->toContain(str_repeat('x', Maho_Ai_Model_Chat_SystemPrompt::MAX_CUSTOM_CHARS));
});

it('leaves the custom instructions out when the store owner wrote none', function (): void {
    $store = Mage::app()->getStore();
    $previous = $store->getConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS);
    $store->setConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS, "  \n ");
    try {
        $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());
    } finally {
        $store->setConfig(Maho_Ai_Model_Chat_SystemPrompt::XML_PATH_CUSTOM_INSTRUCTIONS, $previous);
    }

    expect($prompt)->not->toContain('Instructions from the store owner');
});

it('builds the prompt through the model factory, so a module can override it', function (): void {
    expect(Mage::getModel('ai/chat_systemPrompt'))->toBeInstanceOf(Maho_Ai_Model_Chat_SystemPrompt::class);
});

it('names the pages of the user guide that explain the open admin page', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['route' => 'catalog_product/edit', 'entity_type' => 'product', 'entity_id' => 3]);

    expect($prompt)->toContain('The user guide explains this admin page in:', 'core/user-guide/configurable-product-prices.md (Configurable Product Prices)');
    expect($prompt)->toContain('read the page that fits with docs_read before you read the record');
    expect(new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['route' => 'dashboard/index']))->not->toContain('The user guide explains this admin page');
});

it('asks for a source for a cause, keeps the labels of the admin, and converts dates', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin());

    expect($prompt)->toContain('Give a cause, a rule or a limit of the store only when a page of the user guide or a tool result states it');
    expect($prompt)->toContain('What you know of Magento 2 or of other platforms is not a source');
    expect($prompt)->toContain('Write each menu, tab, field, button and value label exactly as the admin shows it');
    expect($prompt)->toContain('Never translate store data');
    expect($prompt)->toContain('Dates and times in tool results are in UTC');
    expect($prompt)->toContain('A call with a click waits for the confirmation of the administrator');
});
