<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The label of a rule condition shows the chosen value as text. A value that holds markup
 * must not become an element of the page.
 */

const RULE_LABEL_ADMIN_USERNAME = 'pest_rule_label';
const RULE_LABEL_ADMIN_PASSWORD = 'PestRuleLabel2026!';
const RULE_LABEL_MARKUP = '<img src=x onerror="window.__ruleXss=1">';

beforeEach(function () {
    deleteRuleLabelAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(RULE_LABEL_ADMIN_USERNAME)
        ->setFirstname('Rule')
        ->setLastname('Label')
        ->setEmail('rule-label@example.test')
        ->setPassword(RULE_LABEL_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
});

afterEach(function () {
    deleteRuleLabelAdmin();
});

function deleteRuleLabelAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(RULE_LABEL_ADMIN_USERNAME);
    if ($user->getId()) {
        $user->delete();
    }
}

/** Build a condition container from $innerHtml, call hideParamInputField() on it, and return the text of its label. */
function ruleLabelText(object $page, string $innerHtml, string $prepare): string
{
    return $page->script("(() => {
        const c = document.createElement('span');
        c.innerHTML = '{$innerHtml}';
        {$prepare}
        document.body.append(c);
        rule_conditions_fieldset.hideParamInputField(c, null);
        return c.querySelector('.label').textContent;
    })()");
}

it('shows the typed value of a condition as text', function () {
    $page = adminLoginAndVisit(RULE_LABEL_ADMIN_USERNAME, RULE_LABEL_ADMIN_PASSWORD, '/admin/promo_quote/new', '#rule_conditions_fieldset');

    $text = ruleLabelText(
        $page,
        '<a class="label">x</a><span class="element"><input class="input-text" id="pest_rule_input"></span>',
        "c.querySelector('input').value = " . json_encode(RULE_LABEL_MARKUP) . ';',
    );

    expect($text)->toBe(RULE_LABEL_MARKUP);
});

it('shows the chosen option of a condition as text', function () {
    $page = adminLoginAndVisit(RULE_LABEL_ADMIN_USERNAME, RULE_LABEL_ADMIN_PASSWORD, '/admin/promo_quote/new', '#rule_conditions_fieldset');

    $text = ruleLabelText(
        $page,
        '<a class="label">x</a><select class="element-value-changer"><option selected></option></select>',
        "c.querySelector('option').text = " . json_encode(RULE_LABEL_MARKUP) . ';',
    );

    expect($text)->toBe(RULE_LABEL_MARKUP);
});
