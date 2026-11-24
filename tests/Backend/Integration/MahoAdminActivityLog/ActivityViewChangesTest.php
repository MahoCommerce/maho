<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The view of an activity shows each changed field with the shared diff template.
 */
function renderActivityChanges(string $actionType, array $oldData, array $newData): string
{
    $activity = Mage::getModel('adminactivitylog/activity')->setData([
        'action_type' => $actionType,
        'entity_type' => 'cms/page',
        'entity_id' => 1,
        'old_data' => Mage::helper('core')->jsonEncode($oldData),
        'new_data' => Mage::helper('core')->jsonEncode($newData),
    ])->save();

    $design = Mage::getDesign();
    $previousDesign = $design->setAllGetOld([
        'area' => Mage_Core_Model_App_Area::AREA_ADMINHTML,
        'package' => Mage_Core_Model_Design_Package::DEFAULT_PACKAGE,
    ]);
    Mage::register('current_activity', $activity);

    try {
        $layout = Mage::app()->getLayout();
        $layout->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
        return $layout->createBlock('adminactivitylog/adminhtml_activity_view_form')->toHtml();
    } finally {
        Mage::unregister('current_activity');
        $design->setAllGetOld($previousDesign);
        $activity->delete();
    }
}

it('shows a value of more lines in a merge view', function (): void {
    $html = renderActivityChanges('update', ['content' => "<p>One</p>\n<p>Two</p>"], ['content' => "<p>One</p>\n<p>Three</p>"]);

    expect($html)->toContain('data-merge-view')
        ->and($html)->toContain('data-old="&lt;p&gt;One&lt;/p&gt;' . "\n" . '&lt;p&gt;Two&lt;/p&gt;"')
        ->and($html)->toContain('data-new="&lt;p&gt;One&lt;/p&gt;' . "\n" . '&lt;p&gt;Three&lt;/p&gt;"');
});

it('shows a value of one line as escaped text, before and after', function (): void {
    $html = renderActivityChanges('update', ['title' => 'Old <b>title</b>'], ['title' => 'New title']);

    expect($html)->not->toContain('data-merge-view')
        ->and($html)->not->toContain('<b>title</b>')
        ->and($html)->toContain('Old &lt;b&gt;title&lt;/b&gt;')
        ->and($html)->toContain('New title')
        ->and($html)->toContain('Before')
        ->and($html)->toContain('After');
});

it('shows an empty value before a create', function (): void {
    $html = renderActivityChanges('create', ['title' => 'Ignored'], ['title' => 'Created title']);

    expect($html)->toContain('(empty)')
        ->and($html)->toContain('Created title')
        ->and($html)->not->toContain('Ignored');
});
