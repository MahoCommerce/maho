<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The rich text editor keeps only the markup that its schema declares. Before this change it
 * removed the rest without a message. The editor now compares the two documents. It stays
 * closed and reports what it cannot keep.
 *
 * The fixture uses <section> and <figure>. The report named <svg>, but the editor and the save
 * both keep an <svg> now, so it no longer shows a loss. An <iframe> takes its place where a test
 * needs markup that the save removes. A test of what the editor removes cannot use an <iframe>,
 * because xssFilter() strips one before the editor reads the content.
 * tests/Backend/Integration/Cms/ContentSanitizationNoticeTest.php covers that part.
 */

const WYSIWYG_LOSS_ADMIN_USER = 'wysiwyg-loss-admin';
const WYSIWYG_LOSS_ADMIN_PASSWORD = 'Password123!';
const WYSIWYG_LOSS_UNSUPPORTED = '<section class="hero"><figure><img src="/media/a.jpg" alt="A"><figcaption>Cap</figcaption></figure></section>';
// An author writes block tags on separate lines. textContent joins the elements together.
// An earlier text comparison read this as a loss.
const WYSIWYG_LOSS_RICH_TEXT = "<h2>The boring stuff, done right</h2>\n"
    . "<p>Braided cables, chargers that do not get hot, and stands that do not wobble.</p>\n"
    . "<div class=\"promo\">\n    <h3>Free shipping</h3>\n    <p>Every order over <strong>50</strong> euro.</p>\n</div>";

beforeEach(function () {
    createWysiwygLossAdmin();
});

afterEach(function () {
    deleteWysiwygLossAdmin();
});

function deleteWysiwygLossAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(WYSIWYG_LOSS_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** Create an admin with the full-access role. The password is known and the ACL allows all. */
function createWysiwygLossAdmin(): void
{
    deleteWysiwygLossAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(WYSIWYG_LOSS_ADMIN_USER)
        ->setFirstname('Wysiwyg')
        ->setLastname('Loss')
        ->setEmail('wysiwyg-loss@example.test')
        ->setPassword(WYSIWYG_LOSS_ADMIN_PASSWORD)
        ->setIsActive(1)
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

function createWysiwygLossProduct(): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('wysiwyg-loss-' . uniqid())
        ->setName('Wysiwyg Loss')
        ->setDescription('<p>A plain description.</p>')
        ->setShortDescription('<p>Short.</p>')
        ->setPrice(9.99)
        ->setWebsiteIds([1])
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->save();
}

function createWysiwygLossBlock(string $content): Mage_Cms_Model_Block
{
    return Mage::getModel('cms/block')
        ->setTitle('Wysiwyg Loss')
        ->setIdentifier('wysiwyg-loss-' . uniqid())
        ->setStores([0])
        ->setIsActive(1)
        ->setContent($content)
        ->save();
}

/** Open the edit screen of $block. Wait for the first parse of the editor. */
function visitWysiwygLossBlock(Mage_Cms_Model_Block $block): object
{
    $page = adminLoginAndVisit(
        WYSIWYG_LOSS_ADMIN_USER,
        WYSIWYG_LOSS_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $block->getId(),
        '#block_content',
    );

    $deadline = microtime(true) + 10;
    while ($page->script('window.tiptapEditors?.get("block_content")?.editor ? 1 : 0') !== 1) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The WYSIWYG editor did not initialize within 10s');
        }
        usleep(100_000);
    }

    return $page;
}

/** Wait for the answer of the sanitizer check. */
function waitForSanitizerVerdict(object $page, bool $expectWarning): void
{
    $deadline = microtime(true) + 15;
    while ($page->script('window.tiptapEditors.get("block_content").sanitizePreview?.removed ? 1 : 0') !== (int) $expectWarning) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The sanitizer preflight did not answer within 15s');
        }
        usleep(200_000);
    }
}

it('opens the rich text editor for content it can keep', function () {
    $page = visitWysiwygLossBlock(createWysiwygLossBlock(WYSIWYG_LOSS_RICH_TEXT));

    expect($page->script('window.tiptapEditors.get("block_content").isTiptapActive() ? 1 : 0'))->toBe(1)
        ->and($page->script('document.querySelectorAll(".tiptap-content-notice").length'))->toBe(0);
});

it('names the markup it cannot keep instead of dropping it in silence', function () {
    $page = visitWysiwygLossBlock(createWysiwygLossBlock('<p>Lead</p>' . WYSIWYG_LOSS_UNSUPPORTED));

    // The plain textarea stays in front. The field still holds the source.
    expect($page->script('window.tiptapEditors.get("block_content").isTiptapActive() ? 1 : 0'))->toBe(0)
        ->and($page->script('document.getElementById("block_content").value'))->toContain('<section')
        ->and($page->script('document.querySelector(".tiptap-content-notice")?.textContent ?? ""'))
        ->toContain('<section>');
});

it('warns before the save about the markup the sanitizer will remove', function () {
    // A notice after the save comes too late, so the editor asks the server first.
    // The test types the iframe. A stored iframe does not survive the save that creates the block.
    $page = visitWysiwygLossBlock(createWysiwygLossBlock(WYSIWYG_LOSS_RICH_TEXT));

    $page->script('
        const setup = window.tiptapEditors.get("block_content");
        setup.turnOff();
        const textarea = document.getElementById("block_content");
        textarea.value = "<p>Video</p><iframe src=\'https://example.com/v\'></iframe>";
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    ');

    waitForSanitizerVerdict($page, true);

    expect($page->script('document.querySelector(".sanitize-preview-notice")?.textContent ?? ""'))
        ->toContain('Saving removes this HTML')
        ->toContain('<iframe>');
});

it('clears the warning once the editor has cleaned the content', function () {
    // The confirmation removes the iframe. The warning then describes markup that is gone.
    $page = visitWysiwygLossBlock(createWysiwygLossBlock(WYSIWYG_LOSS_RICH_TEXT));

    $page->script('
        const setup = window.tiptapEditors.get("block_content");
        setup.turnOff();
        const textarea = document.getElementById("block_content");
        textarea.value = "<p>Video</p><iframe src=\'https://example.com/v\'></iframe>";
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    ');
    waitForSanitizerVerdict($page, true);

    // window.confirm blocks the page. Answer it before the toggle asks.
    $page->script('window.confirm = () => true; window.tiptapEditors.get("block_content").turnOn();');
    waitForSanitizerVerdict($page, false);

    expect($page->script('window.tiptapEditors.get("block_content").isTiptapActive() ? 1 : 0'))->toBe(1)
        ->and($page->script('document.querySelectorAll(".sanitize-preview-notice").length'))->toBe(0);
});

it('warns on a product description, which has no inline editor at all', function () {
    // The product field is a plain textarea. Its editor opens in a popup, so the WYSIWYG
    // setup does not carry the warning as it does on a CMS field.
    $product = createWysiwygLossProduct();

    $page = adminLoginAndVisit(
        WYSIWYG_LOSS_ADMIN_USER,
        WYSIWYG_LOSS_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $product->getId(),
        '#description',
    );

    $page->script('
        const textarea = document.getElementById("description");
        textarea.value = "<p>Video</p><iframe src=\'https://example.com/v\'></iframe>";
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    ');

    $deadline = microtime(true) + 15;
    while ($page->script('document.querySelectorAll(".sanitize-preview-notice").length') !== 1) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The product description warning did not appear within 15s');
        }
        usleep(200_000);
    }

    expect($page->script('document.querySelector(".sanitize-preview-notice").textContent'))
        ->toContain('Saving removes this HTML')
        ->toContain('<iframe>');

    // A product delete needs the admin area. This process runs outside it.
    Mage::register('isSecureArea', true, true);
    $product->delete();
    Mage::unregister('isSecureArea');
});

it('asks before the product popup editor drops what it cannot keep', function () {
    $product = createWysiwygLossProduct();

    $page = adminLoginAndVisit(
        WYSIWYG_LOSS_ADMIN_USER,
        WYSIWYG_LOSS_ADMIN_PASSWORD,
        '/admin/catalog_product/edit/id/' . $product->getId(),
        '#description',
    );

    // Not an <iframe> here. xssFilter() removes one before convertFromPlain() returns, so the
    // editor compares two documents that never held it and reports no loss. <section> reaches
    // the editor and the schema has no node for it.
    // window.confirm blocks the page. Record the question and refuse it.
    $page->script('
        window.__confirmed = null;
        window.confirm = (message) => { window.__confirmed = message; return false; };
        const textarea = document.getElementById("description");
        textarea.value = "<p>Promo</p><section class=\'hero\'><p>Free shipping</p></section>";
        document.querySelector("button.btn-wysiwyg").click();
    ');

    $deadline = microtime(true) + 15;
    while ($page->script('window.__confirmed ? 1 : 0') !== 1) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('The popup editor did not ask before dropping the section');
        }
        usleep(200_000);
    }

    // A refusal closes the popup. The field keeps its content.
    expect($page->script('window.__confirmed'))->toContain('<section>')
        ->and($page->script('document.getElementById("description").value'))->toContain('<section');

    Mage::register('isSecureArea', true, true);
    $product->delete();
    Mage::unregister('isSecureArea');
});

it('keeps an inline svg through the editor and warns about nothing', function () {
    // The editor holds a node for <svg>, and the save keeps the same markup. An author can
    // therefore paste an icon into the source view and lose nothing.
    $page = visitWysiwygLossBlock(createWysiwygLossBlock(WYSIWYG_LOSS_RICH_TEXT));

    $page->script('
        const setup = window.tiptapEditors.get("block_content");
        setup.turnOff();
        const textarea = document.getElementById("block_content");
        textarea.value = "<p>Rating</p><svg viewBox=\'0 0 24 24\' class=\'icon\'><path d=\'M4 4L20 20\'></path></svg>";
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    ');

    waitForSanitizerVerdict($page, false);

    // No question, because the editor drops nothing.
    $page->script('window.confirm = () => false; window.tiptapEditors.get("block_content").turnOn();');
    $page->wait(1);

    expect($page->script('window.tiptapEditors.get("block_content").isTiptapActive() ? 1 : 0'))->toBe(1)
        ->and($page->script('document.querySelectorAll(".sanitize-preview-notice").length'))->toBe(0)
        ->and($page->script('document.querySelector(".tiptap-content")?.querySelectorAll("svg[viewBox]").length ?? 0'))
        ->toBeGreaterThan(0);

    // Back to source: the icon is still there, with its class.
    $page->script('window.tiptapEditors.get("block_content").turnOff();');
    expect($page->script('document.getElementById("block_content").value'))
        ->toContain('<svg')
        ->toContain('M4 4L20 20')
        ->toContain('icon');
});

it('keeps unsupported markup through a save that changes nothing', function () {
    $block = createWysiwygLossBlock('<p>Lead</p>' . WYSIWYG_LOSS_UNSUPPORTED);
    $page = visitWysiwygLossBlock($block);

    $page->click('Save Block')->wait(2);
    waitForPageLoad($page, '#cmsBlockGrid');

    $saved = Mage::getModel('cms/block')->load($block->getId())->getContent();

    expect($saved)->toContain('<section')
        ->and($saved)->toContain('<figcaption');
});
