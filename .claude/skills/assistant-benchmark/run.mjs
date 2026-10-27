// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0
//
// Run one benchmark case in the real admin panel and print the conversation id as JSON.
//
// Usage, from the root of a Maho checkout (Playwright comes from its node_modules):
//   node <skill>/run.mjs <case.json>
//
// The case file:
//   {
//     "admin": "http://127.0.0.1:8123/admin",   the admin base URL, with the admin frontName
//     "user": "bench", "password": "...",
//     "steps": [
//       { "open": "catalog_product_attribute/edit", "params": { "attribute_id": "92" } },
//       { "tab": "Manage Label / Options" },
//       { "say": "How do I manage colour swatches?" },
//       { "approve": true }, { "deny": true }, { "undo": true }
//     ]
//   }
//
// After each turn, the log holds the page URL and the form fields that the panel changed.

import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';

const require = createRequire(join(process.cwd(), 'package.json'));
const { chromium } = require('playwright');

const spec = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const admin = spec.admin.replace(/\/+$/, '');
const turnTimeout = (spec.timeoutSeconds ?? 300) * 1000;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
const log = [];

// The secret key of an admin URL is md5(controller + action + form key), all lower case.
async function adminUrl(path, params = {}) {
    const [controller, action = 'index'] = path.split('/');
    const formKey = await page.evaluate(() => window.FORM_KEY);
    const key = createHash('md5').update(`${controller.toLowerCase()}${action.toLowerCase()}${formKey}`).digest('hex');
    const query = Object.entries(params).map(([name, value]) => `${encodeURIComponent(name)}/${encodeURIComponent(value)}/`).join('');

    return `${admin}/${controller}/${action}/${query}key/${key}/`;
}

// The panel holds an abort controller while a turn runs. A turn can open another page, which
// replaces the panel object, so a lost page context also ends the wait.
async function turn(start) {
    const began = Date.now();
    await start();
    const running = () => page.waitForFunction(() => window.mahoAiAssistant?.abortController !== null, null, { timeout: 15000, polling: 200 });
    await running().catch(() => {});
    try {
        await page.waitForFunction(() => window.mahoAiAssistant && window.mahoAiAssistant.abortController === null, null, { timeout: turnTimeout, polling: 500 });
    } catch (error) {
        if (Date.now() - began >= turnTimeout) {
            throw error;
        }
        await page.waitForLoadState('load');
    }
    // The panel performs page actions and text edits 800 ms after the turn.
    await page.waitForTimeout(1500);
    const changed = await page.evaluate(() => [...(window.mahoAiAssistant?.changedFields ?? [])]
        .map((id) => document.getElementById(id))
        .filter(Boolean)
        .map((field) => ({ field: window.mahoAiAssistant.fieldLabel(field), value: String(field.value ?? '').slice(0, 2000) }))).catch(() => []);
    // The start of the last message of the panel shows an error that never reached the database.
    const last = await page.evaluate(() => {
        const message = document.getElementById('ai-chat')?.getMessages().at(-1);
        const html = document.createElement('div');
        html.innerHTML = message?.html ?? '';
        return (message?.text ?? html.textContent ?? '').trim().slice(0, 300);
    }).catch(() => '');

    return { seconds: Math.round((Date.now() - began) / 1000), url: page.url(), changed, last };
}

// A turn can end without a confirmation card, for example when it fills the open form instead.
async function panelButton(selector) {
    const button = page.locator(selector).last();
    try {
        await button.waitFor({ timeout: 10000 });
    } catch {
        return null;
    }
    return button;
}

try {
    await page.goto(`${admin}/`);
    await page.fill('#username', spec.user);
    await page.fill('#login', spec.password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('button[type="submit"]')]);
    if (await page.locator('#login').count()) {
        throw new Error('The login failed.');
    }

    let panelReady = false;
    for (const step of spec.steps) {
        if (step.open) {
            const url = await adminUrl(step.open, step.params);
            await page.goto(url, { waitUntil: 'load' });
            await page.waitForFunction(() => window.mahoAiAssistant !== undefined, null, { timeout: 15000 });
            if (await page.locator('#login').count()) {
                throw new Error(`The admin refused ${step.open}: the login page opened.`);
            }
            log.push({ step: 'open', url: page.url() });
            continue;
        }
        if (step.tab) {
            await page.locator('a.tab-item-link', { hasText: step.tab }).first().click();
            await page.waitForTimeout(1000);
            log.push({ step: 'tab', tab: step.tab });
            continue;
        }
        if (!panelReady) {
            if (await page.locator('#ai-chat-panel').isHidden()) {
                await page.click('#ai-chat-toggle');
            }
            await page.click('#ai-chat-new');
            await page.waitForTimeout(1000);
            panelReady = true;
        }
        if (step.say) {
            const say = () => turn(() => page.evaluate((text) => document.getElementById('ai-chat').submitUserMessage({ text }), step.say));
            let result = await say();
            // On SQLite, a first message can fail with a lock before the conversation exists: send it again once.
            const conversation = () => page.evaluate(() => Number(sessionStorage.getItem('maho_ai_chat_conversation') ?? 0)).catch(() => 0);
            if (result.last.includes('An internal error occurred') && await conversation() === 0) {
                log.push({ step: 'say', text: step.say, ...result, retried: true });
                await page.waitForTimeout(3000);
                result = await say();
            }
            log.push({ step: 'say', text: step.say, ...result });
        } else if (step.approve || step.deny || step.undo) {
            const name = step.approve ? 'approve' : (step.deny ? 'deny' : 'undo');
            const button = await panelButton(`.ai-chat-${name}`);
            log.push(button === null ? { step: name, found: false } : { step: name, ...(await turn(() => button.click())) });
            // After an error, the model can retry the write with a new card: approve it too, twice at most.
            for (let retry = 0; step.approve && button !== null && retry < 2; retry++) {
                if (!(await page.evaluate(() => Boolean(window.mahoAiAssistant?.pendingCard)).catch(() => false))) {
                    break;
                }
                const again = page.locator('.ai-chat-approve').last();
                log.push({ step: 'approve', retry: retry + 1, ...(await turn(() => again.click())) });
            }
        }
    }

    const conversationId = await page.evaluate(() => Number(sessionStorage.getItem('maho_ai_chat_conversation') ?? 0));
    console.log(JSON.stringify({ conversation_id: conversationId, steps: log }, null, 2));
} catch (error) {
    console.log(JSON.stringify({ error: String(error?.message ?? error), url: page.url(), steps: log }, null, 2));
    process.exitCode = 1;
} finally {
    await browser.close();
}
