<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Builds raw HTML shaped like Mage_Core_Block_Template's template-hint output
 * (a position:relative dotted-border wrapper whose first child is a
 * position:absolute z-index:998 label carrying the .phtml path in title)
 * and asserts snippets resolve to the innermost enclosing template.
 */
function a11yHintedHtml(): string
{
    return '<html><body>'
        . '<div style="position:relative; border:1px dotted red; margin:6px 2px; padding:18px 2px 2px 2px; zoom:1;">'
        . '<div style="position:absolute; left:0; top:0; padding:2px 5px; font:normal 11px Arial; background:red; left:2px; top:2px; z-index:998;" title="app/design/frontend/base/default/template/page/outer.phtml">page/outer.phtml</div>'
        . '<div class="outer-content"><p class="intro">Hello</p>'
        . '<div style="position:relative; border:1px dotted red; margin:6px 2px; padding:18px 2px 2px 2px; zoom:1;">'
        . '<div style="position:absolute; left:0; top:0; padding:2px 5px; font:normal 11px Arial; background:red; left:2px; top:2px; z-index:998;" title="app/design/frontend/base/default/template/page/inner.phtml">page/inner.phtml</div>'
        . '<a class="broken-link" href="#"></a>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</body></html>';
}

describe('AccessibilityScan template mapper', function () {
    it('maps a snippet to the innermost enclosing template', function () {
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper(a11yHintedHtml());

        [$template, $line] = $mapper->mapSnippet('<a class="broken-link" href="#"></a>');
        expect($template)->toBe('app/design/frontend/base/default/template/page/inner.phtml');
        // The template file does not exist on disk, so no line can be resolved
        expect($line)->toBeNull();
    });

    it('maps a snippet in the outer template to the outer template', function () {
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper(a11yHintedHtml());

        [$template] = $mapper->mapSnippet('<p class="intro">Hello</p>');
        expect($template)->toBe('app/design/frontend/base/default/template/page/outer.phtml');
    });

    it('falls back to the opening tag when the full snippet is not found verbatim', function () {
        // axe reports the element with its children, but in the raw HTML the
        // children are interleaved with hint markup - only the opening tag matches
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper(a11yHintedHtml());

        [$template] = $mapper->mapSnippet('<div class="outer-content"><p>rewritten children</p></div>');
        expect($template)->toBe('app/design/frontend/base/default/template/page/outer.phtml');
    });

    it('returns nulls for snippets that are not in the page', function () {
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper(a11yHintedHtml());

        expect($mapper->mapSnippet('<span class="not-there">x</span>'))->toBe([null, null]);
    });

    it('returns nulls for empty snippets and empty documents', function () {
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper(a11yHintedHtml());
        expect($mapper->mapSnippet(null))->toBe([null, null]);
        expect($mapper->mapSnippet('   '))->toBe([null, null]);

        $empty = new Maho_AccessibilityScan_Model_TemplateMapper('');
        expect($empty->mapSnippet('<a class="broken-link" href="#"></a>'))->toBe([null, null]);
    });

    it('returns nulls when the page carries no hint markup', function () {
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper('<html><body><a class="broken-link" href="#"></a></body></html>');
        expect($mapper->mapSnippet('<a class="broken-link" href="#"></a>'))->toBe([null, null]);
    });

    it('resolves the line number when the template exists on disk', function () {
        $template = 'app/design/adminhtml/default/default/template/accessibilityscan/dashboard.phtml';
        $html = '<html><body>'
            . '<div style="position:relative; border:1px dotted red; zoom:1;">'
            . '<div style="position:absolute; z-index:998;" title="' . $template . '">hint</div>'
            . '<div class="a11yscan-form-row">content</div>'
            . '</div>'
            . '</body></html>';
        $mapper = new Maho_AccessibilityScan_Model_TemplateMapper($html);

        [$mapped, $line] = $mapper->mapSnippet('<div class="a11yscan-form-row">content</div>');
        expect($mapped)->toBe($template);
        expect($line)->toBeInt();
        expect($line)->toBeGreaterThan(0);
    });
});
