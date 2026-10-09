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
    expect($prompt)->toContain('Store views, as "code = name (website)"');
    expect($prompt)->toContain(Mage::app()->getDefaultStoreView()->getCode() . ' = ');
    expect($prompt)->not->toContain('api_key');
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

it('tells a scheduled run to send a summary on every run, and a check only when it finds something', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['run_mode' => 'schedule']);

    expect($prompt)->toContain('send it with notify on every run', 'calls notify only when the result needs attention');
});

it('lists the editor layouts from the guide the panel sent', function (): void {
    $admin = aiPromptAdmin();
    $guide = "## Standard HTML the editor keeps\np\n\n## Directives\n{{var}}\n\n## Columns: 2 Columns\n<div></div>\n\n## Bento grid: Mosaic\n<div></div>";
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit', 'editor_guide' => $guide]);

    expect($prompt)->toContain('The content editor offers these layouts: Columns: 2 Columns, Bento grid: Mosaic.');
    expect($prompt)->not->toContain('<div></div>');
    expect(new Maho_Ai_Model_Chat_SystemPrompt()->build($admin, ['route' => 'cms_page/edit']))->not->toContain('The content editor offers');
});

it('stays within the size budget and keeps its sections', function (): void {
    $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build(aiPromptAdmin(), ['route' => 'cms_page/edit', 'entity_type' => 'CMS page', 'entity_id' => 60, 'entity_label' => 'Maho Store', 'store' => '', 'screen' => '']);

    expect(strlen($prompt))->toBeLessThanOrEqual(Maho_Ai_Model_Chat_SystemPrompt::MAX_CHARS);
    expect($prompt)->toContain('How a task runs:', 'Maho in short:', 'Example of a good turn', 'How to answer:');
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
