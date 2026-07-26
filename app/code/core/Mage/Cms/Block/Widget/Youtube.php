<?php

/**
 * Privacy-friendly YouTube embed, rendered at output time so the iframe never has to survive
 * content sanitization.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Block_Widget_Youtube extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    /**
     * youtube-nocookie.com is YouTube's privacy-enhanced host: it does not write tracking cookies
     * until the visitor actually starts the video, which is what makes the embed usable without
     * prior consent in most EU setups.
     */
    public const EMBED_HOST = 'https://www.youtube-nocookie.com/embed/';

    /**
     * A YouTube id is 11 URL-safe base64 characters today. The range is deliberately loose enough
     * to outlive a format change but strict enough that the id can be interpolated into markup.
     */
    public const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{6,20}$/';

    /** Hosts a video id may be extracted from, compared with any leading "www." removed. */
    public const VIDEO_HOSTS = ['youtube.com', 'youtu.be', 'youtube-nocookie.com', 'm.youtube.com'];

    /**
     * Path segments that introduce a video id on a youtube.com host. Only these carry one: a
     * /playlist, /results or /c/SomeChannel URL ends in an id-shaped segment too, and accepting it
     * would turn a merchant pasting the wrong link into a broken embed instead of nothing at all.
     */
    public const VIDEO_PATH_PREFIXES = ['embed', 'shorts', 'live', 'v'];

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('cms/widget/youtube.phtml');
    }

    /**
     * The video id, extracted from whatever the merchant pasted: a watch URL, a share link, an
     * embed URL, a Shorts URL, or the bare id.
     *
     * Returns '' when nothing valid can be extracted. Callers must treat '' as "render nothing" —
     * this is the only validation between merchant input and an iframe src, so it is a strict
     * allowlist rather than an escape.
     */
    public function getVideoId(): string
    {
        $video = trim((string) $this->getData('video'));
        if ($video === '') {
            return '';
        }

        if (preg_match(self::VIDEO_ID_PATTERN, $video)) {
            return $video;
        }

        // Merchants paste scheme-less URLs (youtu.be/xyz) as often as full ones.
        $parts = parse_url(str_contains($video, '//') ? $video : 'https://' . $video);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }

        // Insisting on a real YouTube host keeps arbitrary junk ("../../etc/passwd") from
        // producing an id-shaped fragment that would render as a broken embed.
        $host = strtolower((string) preg_replace('/^www\./', '', $parts['host']));
        if (!in_array($host, self::VIDEO_HOSTS, true)) {
            return '';
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['v']) && is_string($query['v']) && preg_match(self::VIDEO_ID_PATTERN, $query['v'])) {
                return $query['v'];
            }
        }

        // youtu.be share links put the id straight in the path; every other host prefixes it with
        // the kind of player being linked, and a path shaped like neither holds no video id.
        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? '')), static fn(string $segment): bool => $segment !== ''));
        if ($host === 'youtu.be') {
            $segment = count($segments) === 1 ? $segments[0] : '';
        } else {
            $segment = count($segments) === 2 && in_array(strtolower($segments[0]), self::VIDEO_PATH_PREFIXES, true)
                ? $segments[1]
                : '';
        }

        return $segment !== '' && preg_match(self::VIDEO_ID_PATTERN, $segment) ? $segment : '';
    }

    public function getEmbedUrl(): string
    {
        $videoId = $this->getVideoId();
        if ($videoId === '') {
            return '';
        }

        $params = [];
        $start = (int) $this->getData('start');
        if ($start > 0) {
            $params['start'] = $start;
        }

        return self::EMBED_HOST . $videoId . ($params === [] ? '' : '?' . http_build_query($params));
    }

    /**
     * An iframe carries no text, so without a title a screen reader announces only "frame".
     */
    public function getVideoTitle(): string
    {
        $title = trim((string) $this->getData('title'));
        return $title !== '' ? $title : (string) $this->__('YouTube video player');
    }

    /**
     * CSS aspect-ratio value for the responsive wrapper. The YouTube player is always 16:9 except
     * for Shorts, which are 9:16 — anything else (4:3 archive footage, square uploads) is
     * letterboxed by the player inside one of those two, so offering more would only misreserve
     * space. Restricted to the two so a stored value can never reach the style attribute unchecked.
     */
    public function getAspectRatio(): string
    {
        $ratio = str_replace(':', ' / ', trim((string) $this->getData('aspect_ratio')));

        return $ratio === '9 / 16' ? $ratio : '16 / 9';
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if ($this->getVideoId() === '') {
            return '';
        }
        return parent::_toHtml();
    }
}
