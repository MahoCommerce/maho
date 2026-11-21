<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('renders an email whether or not the theme has an inline stylesheet', function (string $area, bool $hasCss): void {
    $template = Mage::getModel('core/email_template');
    $template->setDesignConfig(['area' => $area, 'store' => Mage::app()->getDefaultStoreView()->getId()]);
    $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
    $template->setTemplateText('{{inlinecss file="email-inline.css"}}<p>Example order</p>');

    $html = $template->getProcessedTemplate();

    expect($html)->toContain('Example order')->not->toContain('CSS inlining error')
        ->and(str_contains($html, 'font-family: Verdana'))->toBe($hasCss);
})->with([
    'admin theme with no stylesheet' => ['adminhtml', false],
    'storefront theme with a stylesheet' => ['frontend', true],
]);
