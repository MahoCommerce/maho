<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {});

it('filters out dangerous JavaScript code from blog post content', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p>Hello world <script>alert("XSS")</script></p>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('<script>');
    expect($savedContent)->not->toContain('</script>');
    // Note: The alert content might remain but without script tags it's harmless
    expect($savedContent)->toContain('Hello world');
});

it('filters out onclick and other JavaScript event handlers', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p><a href="#" onclick="alert(\'XSS\')">Click me</a></p>';

    $post->setTitle('Test Post 2')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('onclick');
    // The link should exist but with security attributes added
    expect($savedContent)->toContain('Click me');
    expect($savedContent)->toContain('target="_blank"');
    expect($savedContent)->toContain('rel="noopener noreferrer"');
});

it('filters out iframe and object tags', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p>Safe content</p><iframe src="javascript:alert(\'XSS\')"></iframe><object data="malicious.swf"></object>';

    $post->setTitle('Test Post 3')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('<iframe');
    expect($savedContent)->not->toContain('<object');
    expect($savedContent)->toContain('Safe content');
});

it('filters out CSS expressions and behaviors', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p style="background: expression(alert(\'XSS\'))">Bad style</p>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('expression(');
    expect($savedContent)->not->toContain('alert(\'XSS\')');
    expect($savedContent)->toContain('Bad style');
});

it('filters out data: URLs and base64 content', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgnWFNTJyk8L3NjcmlwdD4=">';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('data:');
    expect($savedContent)->not->toContain('base64');
});

it('preserves target="_blank" in links', function () {
    $post = Mage::getModel('blog/post');
    $content = '<p><a href="https://example.com" target="_blank">External link</a></p>';

    $post->setTitle('Test Post')
         ->setContent($content)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->toContain('target="_blank"');
    expect($savedContent)->toContain('href="https://example.com"');
    expect($savedContent)->toContain('External link');
});

it('adds target="_blank" to links without it', function () {
    $post = Mage::getModel('blog/post');
    $content = '<p><a href="https://example.com">External link</a></p>';

    $post->setTitle('Test Post')
         ->setContent($content)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->toContain('target="_blank"');
    expect($savedContent)->toContain('href="https://example.com"');
});

it('adds security attributes to all links', function () {
    $post = Mage::getModel('blog/post');
    $content = '<p><a href="https://example.com" target="_blank">External link</a></p>';

    $post->setTitle('Test Post')
         ->setContent($content)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->toContain('rel="noopener noreferrer"');
    expect($savedContent)->toContain('target="_blank"');
});

it('preserves safe HTML content', function () {
    $post = Mage::getModel('blog/post');
    $safeContent = '<h2>Blog Title</h2><p>This is <strong>safe</strong> content with <em>emphasis</em> and a <a href="https://example.com">link</a>.</p><ul><li>Item 1</li><li>Item 2</li></ul>';

    $post->setTitle('Test Post')
         ->setContent($safeContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->toContain('<h2>Blog Title</h2>');
    expect($savedContent)->toContain('<strong>safe</strong>');
    expect($savedContent)->toContain('<em>emphasis</em>');
    expect($savedContent)->toContain('<ul><li>Item 1</li><li>Item 2</li></ul>');
});

it('filters out PHP code', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p>Safe content</p><?php echo "dangerous"; ?>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('<?php');
    expect($savedContent)->not->toContain('echo "dangerous"');
    expect($savedContent)->toContain('Safe content');
});

it('filters out javascript: URLs', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p><a href="javascript:alert(\'XSS\')">Click me</a></p>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
         ->setPublishDate('2025-01-01');
    $post->save();

    $savedContent = $post->getContent();
    expect($savedContent)->not->toContain('javascript:');
    // The main point is that javascript: is removed, content text should remain
    expect($savedContent)->toContain('Click me');
});

it('handles multiple malicious attempts in one content block', function () {
    $post = Mage::getModel('blog/post');
    $dangerousContent = '<p>Safe content</p><script>alert("XSS1")</script><p onclick="alert(\'XSS2\')">Click</p><iframe src="javascript:alert(\'XSS3\')"></iframe>';

    $post->setTitle('Test Post')
         ->setContent($dangerousContent)
         ->setIsActive(1)
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
