<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

function aiExamples(string $route, int|string|null $id): array
{
    return new Maho_Ai_Block_Adminhtml_Assistant()->getExamples([
        'route' => $route,
        'entity_type' => '',
        'entity_id' => $id,
        'entity_label' => '',
        'store' => '',
    ]);
}

it('shows four examples, the proofread first, on every page', function (): void {
    foreach ([['dashboard/index', null], ['cms_page/edit', 183], ['sales_order/view', 302], ['system_config/edit', 'checkout']] as [$route, $id]) {
        $examples = aiExamples($route, $id);
        expect($examples)->toHaveCount(4);
        expect($examples[0])->toBe('Proofread this page');
    }
    expect(aiExamples('dashboard/index', null)[1])->toBe('Which orders are waiting to be shipped?');
});

it('puts the examples for the record of the page after the proofread', function (): void {
    expect(aiExamples('sales_order/view', 302)[1])->toBe('Summarize this order');
    expect(aiExamples('sales_order/view', 302)[2])->toBe('Add a comment to this order: the parcel left today');
    expect(aiExamples('system_config/edit', 'checkout')[1])->toBe('Explain the settings on this page');
});

it('leaves the record examples out of a list page', function (): void {
    expect(aiExamples('cms_page/index', null))->toBe(aiExamples('dashboard/index', null));
});
