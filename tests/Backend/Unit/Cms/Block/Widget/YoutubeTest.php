<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function youtubeWidget(array $data = []): Mage_Cms_Block_Widget_Youtube
{
    $block = new Mage_Cms_Block_Widget_Youtube();
    foreach ($data as $key => $value) {
        $block->setData($key, $value);
    }
    return $block;
}

describe('Mage_Cms_Block_Widget_Youtube::getVideoId', function () {
    it('extracts the id from every form a merchant might paste', function (string $input) {
        expect(youtubeWidget(['video' => $input])->getVideoId())->toBe('dQw4w9WgXcQ');
    })->with([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
        'https://youtu.be/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'youtu.be/dQw4w9WgXcQ',
        'dQw4w9WgXcQ',
        '  dQw4w9WgXcQ  ',
    ]);

    it('rejects anything that is not a youtube video', function (string $input) {
        expect(youtubeWidget(['video' => $input])->getVideoId())->toBe('');
    })->with([
        '"><script>alert(1)</script>',
        'javascript:alert(1)',
        'https://evil.test/embed/dQw4w9WgXcQ',
        // Lookalike hosts must not pass the allowlist.
        'https://youtube.com.evil.test/embed/dQw4w9WgXcQ',
        'https://notyoutube.com/watch?v=dQw4w9WgXcQ',
        '../../etc/passwd',
        'https://www.youtube.com/watch?v=<script>',
        '',
        '   ',
    ]);
});

describe('Mage_Cms_Block_Widget_Youtube rendering', function () {
    it('embeds through the privacy-friendly nocookie host', function () {
        expect(youtubeWidget(['video' => 'dQw4w9WgXcQ'])->getEmbedUrl())
            ->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    });

    it('appends a start time only when one is set', function () {
        expect(youtubeWidget(['video' => 'dQw4w9WgXcQ', 'start' => '90'])->getEmbedUrl())
            ->toContain('?start=90')
            ->and(youtubeWidget(['video' => 'dQw4w9WgXcQ'])->getEmbedUrl())->not->toContain('start');
    });

    it('renders nothing at all when the video is not valid', function () {
        expect(youtubeWidget(['video' => 'javascript:alert(1)'])->toHtml())->toBe('');
    });

    it('renders an iframe with the id and an accessible title', function () {
        $html = youtubeWidget(['video' => 'dQw4w9WgXcQ', 'title' => 'Product demo'])->toHtml();

        expect($html)->toContain('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->and($html)->toContain('title="Product demo"')
            ->and($html)->toContain('loading="lazy"');
    });

    it('escapes a title that contains markup', function () {
        $html = youtubeWidget(['video' => 'dQw4w9WgXcQ', 'title' => '"><img src=x onerror=alert(1)>'])->toHtml();

        // The payload survives as inert text; what matters is that it cannot close the title
        // attribute or open a tag, so no quote or angle bracket reaches the browser unescaped.
        expect($html)->not->toContain('<img')
            ->and($html)->toContain('&lt;img')
            ->and($html)->toContain('&quot;&gt;')
            ->and(substr_count($html, '<iframe'))->toBe(1);
    });

    it('only allows the two aspect ratios YouTube actually serves', function () {
        expect(youtubeWidget(['aspect_ratio' => '9 / 16'])->getAspectRatio())->toBe('9 / 16')
            ->and(youtubeWidget(['aspect_ratio' => '9:16'])->getAspectRatio())->toBe('9 / 16')
            ->and(youtubeWidget(['aspect_ratio' => '16 / 9'])->getAspectRatio())->toBe('16 / 9')
            // Anything else, including an injection attempt, falls back to the default.
            ->and(youtubeWidget(['aspect_ratio' => '"><script>'])->getAspectRatio())->toBe('16 / 9')
            ->and(youtubeWidget([])->getAspectRatio())->toBe('16 / 9');
    });
});
