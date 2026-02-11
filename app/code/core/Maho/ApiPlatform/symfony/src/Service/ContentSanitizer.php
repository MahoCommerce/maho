<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * Content Sanitizer for Admin API
 *
 * Sanitizes HTML content and validates Maho directives
 */
final class ContentSanitizer
{
    private \HTMLPurifier $purifier;

    /** @var array<string> Allowed Maho directives */
    private const ALLOWED_DIRECTIVES = [
        'media',    // {{media url="..."}}
        'store',    // {{store url="..."}}
        'config',   // {{config path="..."}} - limited paths
        'youtube',  // {{youtube id="..."}}
        'vimeo',    // {{vimeo id="..."}}
    ];

    /** @var array<string> Safe config paths */
    private const SAFE_CONFIG_PATHS = [
        'general/store_information/',
        'web/unsecure/',
        'web/secure/',
        'design/',
        'trans_email/',
        'contacts/',
        'catalog/seo/',
    ];

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr',
            'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark',
            'a[href|title|target]',
            'img[src|alt|width|height|class]',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div[class]', 'span[class]',
            'blockquote', 'pre', 'code',
            'figure', 'figcaption',
        ]));
        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.SafeObject', false);
        $config->set('HTML.SafeEmbed', false);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', ['http', 'https', 'mailto']);

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Sanitize content for safe storage
     */
    public function sanitize(string $content): string
    {
        // First, extract and validate Maho directives
        $content = $this->processDirectives($content);

        // Then sanitize HTML
        return $this->purifier->purify($content);
    }

    /**
     * Process and validate Maho directives
     */
    private function processDirectives(string $content): string
    {
        // Match all {{...}} directives
        return preg_replace_callback(
            '/\{\{(\w+)([^}]*)\}\}/',
            function ($matches) {
                $directive = strtolower($matches[1]);
                $params = $matches[2];

                if (!in_array($directive, self::ALLOWED_DIRECTIVES, true)) {
                    // Strip dangerous directives (block, widget, layout, etc.)
                    return '';
                }

                // Validate specific directives
                return match ($directive) {
                    'config' => $this->validateConfigDirective($params) ? $matches[0] : '',
                    'youtube' => $this->validateVideoDirective($params, 'youtube') ? $matches[0] : '',
                    'vimeo' => $this->validateVideoDirective($params, 'vimeo') ? $matches[0] : '',
                    default => $matches[0], // media, store are always allowed
                };
            },
            $content,
        );
    }

    /**
     * Validate config directive path
     */
    private function validateConfigDirective(string $params): bool
    {
        if (preg_match('/path=["\']?([^"\'}\s]+)["\']?/', $params, $match)) {
            $path = $match[1];
            foreach (self::SAFE_CONFIG_PATHS as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validate video directive
     */
    private function validateVideoDirective(string $params, string $type): bool
    {
        // Must have id parameter with alphanumeric value
        return (bool) preg_match('/id=["\']?[\w\-]+["\']?/', $params);
    }
}
