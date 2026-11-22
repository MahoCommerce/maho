<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const USE_SYSTEM_VALUE_PATH = 'crawlers/robots/base_rules';

function shippedBaseRules(): string
{
    $file = Maho::findFile(Mage::getModuleDir('etc', 'Mage_Sitemap') . '/config.xml');
    return (string) simplexml_load_file($file)->default->crawlers->robots->base_rules;
}

function defaultScopeBaseRulesRows(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return $adapter->fetchCol(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core/config_data'), ['value'])
            ->where('path = ?', USE_SYSTEM_VALUE_PATH)
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0),
    );
}

function baseRulesElement(string $website = ''): ?Maho\Data\Form\Element\AbstractElement
{
    $request = Mage::app()->getRequest();
    $request->setParam('section', 'crawlers');
    $request->setParam('website', $website);
    $request->setParam('store', '');
    Mage::getSingleton('adminhtml/config_data')
        ->setSection('crawlers')
        ->setWebsite($website)
        ->setStore('');

    $action = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Core_Controller_Varien_Action {};
    Mage::app()->getFrontController()->setAction($action);

    $form = new Mage_Adminhtml_Block_System_Config_Form();
    $form->setLayout($action->getLayout());
    return $form->initForm()->getForm()->getElement('crawlers_robots_base_rules');
}

afterEach(function (): void {
    Mage::app()->getFrontController()->setAction(null);
    Mage::getConfig()->deleteConfig(USE_SYSTEM_VALUE_PATH);
});

it('returns the shipped value when the default scope has a saved value', function (): void {
    Mage::getConfig()->saveConfig(USE_SYSTEM_VALUE_PATH, '');
    Mage::getConfig()->reinit();

    expect(Mage::getStoreConfig(USE_SYSTEM_VALUE_PATH))->toBe('')
        ->and((string) Mage::getConfig()->getShippedDefaults()->descend(USE_SYSTEM_VALUE_PATH))
        ->toBe(shippedBaseRules());
});

it('offers the system value only at default scope, for a shipped value that is not empty, and without a backend model', function (): void {
    $sections = Mage::getSingleton('adminhtml/config')->getSections();
    $baseRules = $sections->descend('crawlers/groups/robots/fields/base_rules');
    $allowedCurrencies = $sections->descend('currency/groups/options/fields/allow');
    $llmsDescription = $sections->descend('crawlers/groups/llms/fields/description');
    $form = new Mage_Adminhtml_Block_System_Config_Form();

    expect((string) Mage::getConfig()->getShippedDefaults()->descend('currency/options/allow'))->not->toBe('')
        ->and($form->setScope('default')->canUseSystemValue(USE_SYSTEM_VALUE_PATH, $baseRules))->toBeTrue()
        ->and($form->canUseSystemValue('crawlers/robots/not_shipped', $baseRules))->toBeFalse()
        ->and($form->canUseSystemValue('currency/options/allow', $allowedCurrencies))->toBeFalse()
        ->and(Mage::getConfig()->getShippedDefaults()->descend('crawlers/llms/description'))->not->toBeFalse()
        ->and($form->canUseSystemValue('crawlers/llms/description', $llmsDescription))->toBeFalse()
        ->and($form->setScope('websites')->canUseSystemValue(USE_SYSTEM_VALUE_PATH, $baseRules))->toBeFalse();
});

it('checks Use Default at default scope when the default scope has no saved value', function (): void {
    $element = baseRulesElement();
    $html = $element->toHtml();

    expect($element->getCanUseSystemValue())->toBeTrue()
        ->and($element->getInherit())->toBeTrue()
        ->and($html)->toContain('Use Default')
        ->and($html)->toMatch('/id="crawlers_robots_base_rules_inherit"[^>]*checked="checked"/')
        ->and($html)->toMatch('/<textarea[^>]*disabled/');
});

it('clears Use Default at default scope when the default scope has a saved value', function (): void {
    Mage::getConfig()->saveConfig(USE_SYSTEM_VALUE_PATH, 'Disallow: /private/');
    $element = baseRulesElement();

    expect($element->getInherit())->toBeFalse()
        ->and($element->toHtml())->not->toMatch('/id="crawlers_robots_base_rules_inherit"[^>]*checked="checked"/');
});

it('keeps Use Default at website scope', function (): void {
    $element = baseRulesElement('base');

    expect($element->getCanUseSystemValue())->toBeFalse()
        ->and($element->getCanUseDefaultValue())->toBeTrue()
        ->and($element->toHtml())->toContain('Use Default');
});

it('deletes the saved default value when Use Default is saved at default scope', function (): void {
    Mage::getConfig()->saveConfig(USE_SYSTEM_VALUE_PATH, '');
    expect(defaultScopeBaseRulesRows())->toBe(['']);

    $data = new Mage_Adminhtml_Model_Config_Data();
    $data->setSection('crawlers')
        ->setWebsite('')
        ->setStore('')
        ->setGroups(['robots' => ['fields' => ['base_rules' => ['inherit' => 1]]]]);
    $data->save();

    expect(defaultScopeBaseRulesRows())->toBe([]);
});
