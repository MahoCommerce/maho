<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const ACCORDION_OPEN_BODY = 'Ships within 48 hours';
const ACCORDION_CLOSED_BODY = 'Thirty days to change your mind';
const TABS_FIRST_BODY = 'A description of the product';
const TABS_SECOND_BODY = 'Weighs exactly one kilogram';

/** A CMS page holding both a WYSIWYG accordion and a WYSIWYG tab group. */
function createAccordionPage(): string
{
    $identifier = 'accordion-tabs-' . uniqid();

    Mage::getModel('cms/page')
        ->setTitle('Accordion And Tabs')
        ->setIdentifier($identifier)
        ->setIsActive(1)
        ->setRootTemplate('one_column')
        ->setStores([0])
        ->setContent(
            '<div data-type="maho-accordion" data-style="accordion">'
            . '<details open><summary>Shipping</summary>'
            . '<div data-type="detailsContent"><p>' . ACCORDION_OPEN_BODY . '</p></div></details>'
            . '<details><summary>Returns</summary>'
            . '<div data-type="detailsContent"><p>' . ACCORDION_CLOSED_BODY . '</p></div></details>'
            . '</div>'
            . '<div data-type="maho-accordion" data-style="tabs">'
            . '<details name="tabs-fixture" open><summary>Description</summary>'
            . '<div data-type="detailsContent"><p>' . TABS_FIRST_BODY . '</p></div></details>'
            . '<details name="tabs-fixture"><summary>Specifications</summary>'
            . '<div data-type="detailsContent"><p>' . TABS_SECOND_BODY . '</p></div></details>'
            . '</div>',
        )
        ->save();

    Mage::app()->cleanCache();

    return $identifier;
}

it('opens an accordion section on the storefront', function () {
    $page = visit(MahoServer::baseUrl() . '/' . createAccordionPage());
    waitForPageLoad($page, '[data-style="accordion"]:visible');

    // A closed <details> renders none of its content, so the body text is the signal
    $page->assertSee(ACCORDION_OPEN_BODY)
        ->assertDontSee(ACCORDION_CLOSED_BODY);

    $page->click('[data-style="accordion"] > details:nth-of-type(2) > summary')
        ->assertSee(ACCORDION_CLOSED_BODY)
        ->assertSee(ACCORDION_OPEN_BODY);
});

it('switches tabs on the storefront', function () {
    $page = visit(MahoServer::baseUrl() . '/' . createAccordionPage());

    // The tab strip only exists once app.js has enhanced the group; without it the
    // group stays an accordion, which is the no-JS fallback rather than a failure
    waitForPageLoad($page, '.maho-tabs-strip:visible');

    $page->assertSee(TABS_FIRST_BODY)
        ->assertDontSee(TABS_SECOND_BODY);

    $page->click('.maho-tabs-strip button >> nth=1')
        ->assertSee(TABS_SECOND_BODY)
        ->assertDontSee(TABS_FIRST_BODY);

    // The summaries are hidden in tabs mode, so the strip carries the selected state
    $page->assertAriaAttribute('.maho-tabs-strip button >> nth=1', 'selected', 'true')
        ->assertAriaAttribute('.maho-tabs-strip button >> nth=0', 'selected', 'false');
});
