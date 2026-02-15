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

        // Trust admin API content - allow most HTML with specific dangerous elements removed
        // This is appropriate because admin API requires authentication and specific permissions
        $config->set('HTML.Trusted', true);
        $config->set('CSS.Trusted', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);

        // Remove dangerous elements (scripts, objects, etc.)
        $config->set('HTML.ForbiddenElements', ['script', 'object', 'embed', 'applet', 'iframe', 'frame', 'frameset', 'base', 'meta', 'link']);
        $config->set('HTML.ForbiddenAttributes', ['*@on*']); // Remove all event handlers

        // Add HTML5 elements - must be done last before creating purifier
        $config->set('HTML.DefinitionID', 'maho-admin-api');
        $config->set('HTML.DefinitionRev', 1);
        $config->set('Cache.DefinitionImpl', null); // Disable caching for development
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('section', 'Block', 'Flow', 'Common');
            $def->addElement('article', 'Block', 'Flow', 'Common');
            $def->addElement('header', 'Block', 'Flow', 'Common');
            $def->addElement('footer', 'Block', 'Flow', 'Common');
            $def->addElement('nav', 'Block', 'Flow', 'Common');
            $def->addElement('aside', 'Block', 'Flow', 'Common');
            $def->addElement('main', 'Block', 'Flow', 'Common');
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addElement('time', 'Inline', 'Inline', 'Common', ['datetime' => 'Text']);
        }

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Sanitize content for safe storage
     */
    public function sanitize(string $content): string
    {
        // First, extract and validate Maho directives, storing them for later restoration
        $directives = [];
        $content = $this->extractDirectives($content, $directives);

        // Process (validate) directives that remain in the content
        $content = $this->processDirectives($content);

        // Protect <style> tags from HTMLPurifier (it doesn't handle them well)
        $styles = [];
        $content = $this->extractStyles($content, $styles);

        // Sanitize HTML
        $content = $this->purifier->purify($content);

        // Restore protected elements
        $content = $this->restoreDirectives($content, $directives);
        return $this->restoreStyles($content, $styles);
    }

    /**
     * Extract <style> tags before HTMLPurifier processes them
     */
    private function extractStyles(string $content, array &$styles): string
    {
        return preg_replace_callback(
            '/<style[^>]*>.*?<\/style>/is',
            function ($matches) use (&$styles) {
                $placeholder = '___MAHO_STYLE_' . count($styles) . '___';
                $styles[$placeholder] = $matches[0];
                return $placeholder;
            },
            $content,
        );
    }

    /**
     * Restore <style> tags after HTMLPurifier has processed the content
     */
    private function restoreStyles(string $content, array $styles): string
    {
        return str_replace(array_keys($styles), array_values($styles), $content);
    }

    /**
     * Extract directives from content before HTMLPurifier processes it
     * This prevents HTMLPurifier from URL-encoding the directives
     */
    private function extractDirectives(string $content, array &$directives): string
    {
        return preg_replace_callback(
            '/\{\{(\w+)([^}]*)\}\}/',
            function ($matches) use (&$directives) {
                $directive = strtolower($matches[1]);

                // Only protect allowed directives
                if (!in_array($directive, self::ALLOWED_DIRECTIVES, true)) {
                    return $matches[0]; // Let processDirectives handle stripping
                }

                // Generate placeholder and store directive
                $placeholder = '___MAHO_DIRECTIVE_' . count($directives) . '___';
                $directives[$placeholder] = $matches[0];
                return $placeholder;
            },
            $content,
        );
    }

    /**
     * Restore directives after HTMLPurifier has processed the content
     */
    private function restoreDirectives(string $content, array $directives): string
    {
        return str_replace(array_keys($directives), array_values($directives), $content);
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
