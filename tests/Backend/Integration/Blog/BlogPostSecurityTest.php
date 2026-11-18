<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {});

it('removes dangerous code from blog post content and keeps the safe text', function (string $content, array $removed, array $kept) {
    $post = Mage::getModel('blog/post');
    $post->setTitle('Test Post')
         ->setContent($content)
         ->setIsActive(true)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    foreach ($removed as $text) {
        expect($savedContent)->not->toContain($text);
    }
    foreach ($kept as $text) {
        expect($savedContent)->toContain($text);
    }
})->with([
    'script tag' => ['<p>Hello world <script>alert("XSS")</script></p>', ['<script>', '</script>'], ['Hello world']],
    'iframe and object tags' => [
        '<p>Safe content</p><iframe src="javascript:alert(\'XSS\')"></iframe><object data="malicious.swf"></object>',
        ['<iframe', '<object'],
        ['Safe content'],
    ],
    'CSS expression' => ['<p style="background: expression(alert(\'XSS\'))">Bad style</p>', ['expression(', 'alert(\'XSS\')'], ['Bad style']],
    'data URL' => ['<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgnWFNTJyk8L3NjcmlwdD4=">', ['data:', 'base64'], []],
    'PHP code' => ['<p>Safe content</p><?php echo "dangerous"; ?>', ['<?php', 'echo "dangerous"'], ['Safe content']],
    'javascript URL' => ['<p><a href="javascript:alert(\'XSS\')">Click me</a></p>', ['javascript:'], ['Click me']],
]);

it('adds target and rel attributes to links', function (string $content, array $kept) {
    $post = Mage::getModel('blog/post');
    $post->setTitle('Test Post')
         ->setContent($content)
         ->setIsActive(true)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    foreach ($kept as $text) {
        expect($savedContent)->toContain($text);
    }
})->with([
    'link with target' => [
        '<p><a href="https://example.com" target="_blank">External link</a></p>',
        ['target="_blank"', 'href="https://example.com"', 'External link', 'rel="noopener noreferrer"'],
    ],
    'link without target' => [
        '<p><a href="https://example.com">External link</a></p>',
        ['target="_blank"', 'href="https://example.com"'],
    ],
]);

it('filters out onclick and other JavaScript event handlers', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p><a href="#" onclick="alert(\'XSS\')">Click me</a></p>';

    $post->setTitle('Test Post 2')
         ->setContent($dangerousContent)
         ->setIsActive(true)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('onclick');
    // The link should exist but with security attributes added
    expect($savedContent)->toContain('Click me');
    expect($savedContent)->toContain('target="_blank"');
    expect($savedContent)->toContain('rel="noopener noreferrer"');
});

it('preserves safe HTML content', function () {
    $post = Mage::getModel('blog/post');
    $safeContent = '<h2>Blog Title</h2><p>This is <strong>safe</strong> content with <em>emphasis</em> and a <a href="https://example.com">link</a>.</p><ul><li>Item 1</li><li>Item 2</li></ul>';

    $post->setTitle('Test Post')
         ->setContent($safeContent)
         ->setIsActive(true)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->toContain('<h2>Blog Title</h2>');
    expect($savedContent)->toContain('<strong>safe</strong>');
    expect($savedContent)->toContain('<em>emphasis</em>');
    expect($savedContent)->toContain('<ul><li>Item 1</li><li>Item 2</li></ul>');
});

it('handles multiple malicious attempts in one content block', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p>Safe content</p><script>alert("XSS1")</script><p onclick="alert(\'XSS2\')">Click</p><iframe src="javascript:alert(\'XSS3\')"></iframe>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(true)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    // Verify dangerous tags and attributes are removed
    expect($savedContent)->not->toContain('<script>');
    expect($savedContent)->not->toContain('onclick');
    expect($savedContent)->not->toContain('<iframe');
    expect($savedContent)->not->toContain('javascript:');
    // Verify safe content remains
    expect($savedContent)->toContain('Safe content');
    expect($savedContent)->toContain('Click');
});
