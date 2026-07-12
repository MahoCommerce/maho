// Playwright + axe-core accessibility scanner.
//
// Reads a JSON config file (path given as the only CLI argument), scans a single
// URL and prints a JSON result to stdout. Template-hint wrappers rendered by
// Maho (enabled via the scan cookie) are captured in rawHtml for source mapping,
// then stripped from the live DOM before axe-core runs so they cannot cause
// false positives.
//
// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from 'playwright';
import { AxeBuilder } from '@axe-core/playwright';

const config = JSON.parse(readFileSync(process.argv[2], 'utf8'));

const browser = await chromium.launch({ headless: true });
try {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 1280, height: 1024 },
    });

    if (config.scanCookie?.name) {
        await context.addCookies([{
            name: config.scanCookie.name,
            value: config.scanCookie.value,
            url: config.url,
        }]);
    }

    const page = await context.newPage();
    await page.goto(config.url, {
        waitUntil: 'networkidle',
        timeout: config.timeout ?? 30000,
    });

    const title = await page.title();

    let screenshotPath = null;
    if (config.screenshotDir) {
        screenshotPath = join(config.screenshotDir, config.screenshotName ?? 'scan.png');
        await page.screenshot({ path: screenshotPath, fullPage: true });
    }

    // Capture the HTML with template hints intact for the source-mapping step
    const rawHtml = await page.evaluate(() => document.documentElement.outerHTML);

    // Strip template hints from the live DOM: remove the label divs, then
    // unwrap the dotted-border wrapper divs (deepest first) so axe-core sees
    // the page as it would render without hints
    await page.evaluate(() => {
        for (const el of document.querySelectorAll('div[style]')) {
            const style = el.getAttribute('style') ?? '';
            if (style.includes('position:absolute') && style.includes('z-index:998')) {
                el.remove();
            }
        }
        const wrappers = [...document.querySelectorAll('div[style]')].filter((el) => {
            const style = el.getAttribute('style') ?? '';
            return style.includes('position:relative') && style.includes('1px dotted');
        });
        for (const wrapper of wrappers.reverse()) {
            wrapper.replaceWith(...wrapper.childNodes);
        }
    });

    const results = await new AxeBuilder({ page })
        .withTags(config.wcagTags ?? ['wcag2a', 'wcag2aa'])
        .analyze();

    const output = {
        url: config.url,
        title,
        screenshotPath,
        rawHtml,
        violations: results.violations.map((violation) => ({
            ruleId: violation.id,
            impact: violation.impact,
            description: violation.description,
            help: violation.help,
            helpUrl: violation.helpUrl,
            wcagTags: violation.tags.filter((tag) => tag.startsWith('wcag')),
            nodes: violation.nodes.map((node) => ({
                cssSelector: Array.isArray(node.target) ? node.target.join(' ') : String(node.target),
                html: node.html,
                failureSummary: node.failureSummary,
            })),
        })),
    };

    process.stdout.write(JSON.stringify(output));
} catch (error) {
    process.stderr.write(String(error?.stack ?? error));
    process.exitCode = 1;
} finally {
    await browser.close();
}
