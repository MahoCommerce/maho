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

import { lookup } from 'node:dns/promises';
import { readFileSync } from 'node:fs';
import net from 'node:net';
import { join } from 'node:path';
import { chromium } from 'playwright';
import { AxeBuilder } from '@axe-core/playwright';

const config = JSON.parse(readFileSync(process.argv[2], 'utf8'));

const browser = await chromium.launch({ headless: true });
try {
    const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: {
            width: config.viewport?.width ?? 1280,
            height: config.viewport?.height ?? 1024,
        },
        isMobile: config.viewport?.mobile ?? false,
        hasTouch: config.viewport?.mobile ?? false,
        // Service workers can issue fetches that bypass page.route(), which
        // would sidestep the SSRF guards below
        serviceWorkers: 'block',
    });

    if (config.scanCookie?.name) {
        await context.addCookies([{
            name: config.scanCookie.name,
            value: config.scanCookie.value,
            url: config.url,
        }]);
    }

    const page = await context.newPage();

    // SSRF guards. The target URL was validated against the store base URLs
    // before this process started, but page content could still steer the
    // server-side browser at the internal network:
    // - Navigations (redirects, meta refresh, JS, iframes) are pinned to the
    //   target host in every frame: only http(s) on the original port (or
    //   the standard 80/443, so http -> https upgrades keep working).
    // - Subresources (images, scripts, fetch, ...) may legitimately load
    //   from CDNs and other third-party hosts, but never from private,
    //   loopback, link-local or cloud-metadata addresses, except the target
    //   host itself, which is often private and was validated upstream.
    // Residual risk: hostnames are vetted with our own DNS lookup, while
    // Chromium resolves them again itself, so a fast-flux DNS-rebinding
    // response window remains; raw WebSocket connections are not intercepted
    // by page.route().
    const targetUrl = new URL(config.url);
    const defaultPort = (protocol) => (protocol === 'https:' ? '443' : '80');
    const targetPort = targetUrl.port || defaultPort(targetUrl.protocol);
    // Allow the target's own port, plus the opposite scheme's default port only
    // when the target is itself on a default port, so a plain http -> https
    // upgrade keeps working without opening arbitrary ports on the same host.
    const allowedPorts = new Set([targetPort]);
    if (targetPort === '80') allowedPorts.add('443');
    if (targetPort === '443') allowedPorts.add('80');
    const isAllowedNavigation = (url) => (url.protocol === 'http:' || url.protocol === 'https:')
        && url.hostname === targetUrl.hostname
        && allowedPorts.has(url.port || defaultPort(url.protocol));

    // Expand any valid IPv6 literal to its eight 16-bit groups, first converting
    // a trailing embedded IPv4 (::ffff:1.2.3.4) into two hex groups.
    const expandIPv6 = (ip) => {
        let s = ip.split('%')[0]; // drop any zone id
        const v4 = s.match(/(\d{1,3}(?:\.\d{1,3}){3})$/);
        if (v4) {
            const o = v4[1].split('.').map(Number);
            if (o.some((n) => n > 255)) return null;
            s = s.slice(0, -v4[1].length)
                + (((o[0] << 8) | o[1]).toString(16)) + ':'
                + (((o[2] << 8) | o[3]).toString(16));
        }
        const halves = s.split('::');
        if (halves.length > 2) return null;
        const head = halves[0] ? halves[0].split(':') : [];
        const tail = halves.length === 2 ? (halves[1] ? halves[1].split(':') : []) : null;
        const groups = tail === null
            ? head
            : [...head, ...Array(8 - head.length - tail.length).fill('0'), ...tail];
        if (groups.length !== 8) return null;
        return groups.map((g) => Number.parseInt(g, 16) & 0xffff);
    };

    const isPrivateAddress = (ip) => {
        if (net.isIPv4(ip)) {
            const [a, b] = ip.split('.').map(Number);
            return a === 0 || a === 10 || a === 127
                || (a === 100 && b >= 64 && b <= 127)   // 100.64.0.0/10 CGNAT
                || (a === 169 && b === 254)             // link-local / cloud metadata
                || (a === 172 && b >= 16 && b <= 31)
                || (a === 192 && b === 168);
        }
        if (net.isIPv6(ip)) {
            const g = expandIPv6(ip);
            if (g === null) return false;
            // IPv4-mapped (::ffff:a.b.c.d) and IPv4-compatible (::a.b.c.d, which
            // also covers :: and ::1): the low 32 bits are an IPv4 address, so
            // re-check them through the IPv4 rules. The WHATWG URL parser
            // canonicalizes these to hex-group form (e.g. ::ffff:7f00:1), so a
            // dotted-decimal string match would never fire, so parse the groups.
            if (g[0] === 0 && g[1] === 0 && g[2] === 0 && g[3] === 0 && g[4] === 0
                && (g[5] === 0 || g[5] === 0xffff)) {
                const v4 = `${g[6] >> 8}.${g[6] & 0xff}.${g[7] >> 8}.${g[7] & 0xff}`;
                return isPrivateAddress(v4);
            }
            return (g[0] & 0xfe00) === 0xfc00     // fc00::/7 unique local
                || (g[0] & 0xffc0) === 0xfe80;    // fe80::/10 link-local
        }
        return false;
    };

    const privateHostCache = new Map();
    const isPrivateHost = async (hostname) => {
        const bare = hostname.replace(/^\[|\]$/g, '').replace(/\.$/, '').toLowerCase();
        if (net.isIP(bare)) {
            return isPrivateAddress(bare);
        }
        if (bare === 'localhost' || bare.endsWith('.localhost') || bare.endsWith('.internal')) {
            return true;
        }
        if (!privateHostCache.has(bare)) {
            privateHostCache.set(bare, lookup(bare, { all: true }).then(
                (addresses) => addresses.some(({ address }) => isPrivateAddress(address)),
                () => true, // unresolvable for us -> block (Chromium could not load it anyway)
            ));
        }
        return privateHostCache.get(bare);
    };

    // Same-host subresources are pinned to the target's own port(s): the
    // target host may itself be a private address (validated upstream), so
    // without a port check page content could reach other services bound to
    // that private IP (Redis, Elasticsearch, internal admin panels) on
    // arbitrary ports.
    const isAllowedSubresource = async (url) => {
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return false;
        }
        if (url.hostname === targetUrl.hostname) {
            return allowedPorts.has(url.port || defaultPort(url.protocol));
        }
        return !(await isPrivateHost(url.hostname));
    };

    await page.route('**/*', async (route) => {
        const request = route.request();
        let url;
        try {
            url = new URL(request.url());
        } catch {
            return route.abort('blockedbyclient');
        }
        if (request.isNavigationRequest()) {
            // Main-frame redirect hops are re-checked after page.goto() below
            return isAllowedNavigation(url) ? route.continue() : route.abort('blockedbyclient');
        }
        // page.route() is not re-invoked for server redirect hops, so a public
        // host could 302 a subresource straight into the private network:
        // follow redirects manually, vetting every hop against the same policy
        try {
            for (let hop = 0; hop < 5; hop++) {
                if (!(await isAllowedSubresource(url))) {
                    break;
                }
                const response = await route.fetch({ url: url.toString(), maxRedirects: 0 });
                const location = response.headers()['location'];
                if (response.status() >= 300 && response.status() < 400 && location) {
                    url = new URL(location, url);
                    continue;
                }
                return await route.fulfill({ response });
            }
        } catch {
            // route.fetch() rejects e.g. on cross-protocol redirects or network errors
        }
        return route.abort('blockedbyclient');
    });

    await page.goto(config.url, {
        waitUntil: 'load',
        timeout: config.timeout ?? 30000,
    });

    // Best-effort settle for lazy-loaded content. Pages with persistent
    // connections (chat widgets, analytics beacons, websockets) never reach
    // network idle, so cap the wait and continue instead of failing the scan.
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    if (!isAllowedNavigation(new URL(page.url()))) {
        throw new Error(`Navigation escaped the target host: ${page.url()}`);
    }

    // The route handler vets only the first request of a redirect chain, so a
    // same-host iframe URL could still 302 into the private network, and
    // axe-core reads every frame's content into the results: drop any frame
    // whose final http(s) URL escaped the target host (about:, data: and
    // blob: frames carry page-authored content and stay)
    for (const frame of page.frames()) {
        if (frame === page.mainFrame()) {
            continue;
        }
        let frameUrl;
        try {
            frameUrl = new URL(frame.url());
        } catch {
            continue;
        }
        if ((frameUrl.protocol === 'http:' || frameUrl.protocol === 'https:') && !isAllowedNavigation(frameUrl)) {
            try {
                await (await frame.frameElement()).evaluate((el) => el.remove());
            } catch {
                // frame already detached
            }
        }
    }

    const title = await page.title();

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

    // Screenshot after the hint strip so the stored image shows the page
    // as visitors see it, not the debug overlays
    let screenshotPath = null;
    if (config.screenshotDir) {
        screenshotPath = join(config.screenshotDir, config.screenshotName ?? 'scan.png');
        await page.screenshot({ path: screenshotPath, fullPage: true });
    }

    const results = await new AxeBuilder({ page })
        .withTags(config.wcagTags ?? ['wcag2a', 'wcag2aa'])
        .analyze();

    // Measure each violating element so the UI can highlight it on the
    // screenshot. Coordinates are absolute page CSS pixels in the same DOM
    // state the screenshot captured; multi-step targets (iframe/shadow DOM
    // chains) cannot be resolved with querySelector and are skipped.
    const nodeRects = await page.evaluate(
        (targets) => targets.map((target) => {
            if (!Array.isArray(target) || target.length !== 1 || typeof target[0] !== 'string') {
                return null;
            }
            try {
                const el = document.querySelector(target[0]);
                if (!el) {
                    return null;
                }
                const rect = el.getBoundingClientRect();
                if (rect.width < 1 || rect.height < 1) {
                    return null;
                }
                return {
                    x: Math.round(rect.x + window.scrollX),
                    y: Math.round(rect.y + window.scrollY),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                };
            } catch {
                return null;
            }
        }),
        results.violations.flatMap((violation) => violation.nodes.map((node) => node.target)),
    );

    const pageSize = await page.evaluate(() => ({
        width: Math.max(document.documentElement.scrollWidth, document.documentElement.clientWidth),
        height: Math.max(document.documentElement.scrollHeight, document.documentElement.clientHeight),
    }));

    let rectIndex = 0;
    const output = {
        url: config.url,
        title,
        screenshotPath,
        pageWidth: pageSize.width,
        pageHeight: pageSize.height,
        rawHtml,
        // Checks axe-core could not decide on its own (e.g. contrast over an
        // image); they need a human review and are reported as a counter
        incompleteCount: results.incomplete.reduce((count, item) => count + item.nodes.length, 0),
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
                boundingBox: nodeRects[rectIndex++] ?? null,
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
