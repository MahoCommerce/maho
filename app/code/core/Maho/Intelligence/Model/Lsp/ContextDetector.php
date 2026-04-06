<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Detects what kind of Maho context the cursor is in, enabling
 * context-aware completions, definitions, and hover info.
 *
 * Supports both PHP and XML files. For XML, the detector parses
 * the document's tag ancestry to determine the correct alias type
 * based on the XML path (e.g. global/events/.../observers/.../class
 * resolves as a model alias, while global/models/.../class resolves
 * as a FQCN class prefix).
 */
class Maho_Intelligence_Model_Lsp_ContextDetector
{
    public const CONTEXT_NONE = 'none';
    public const CONTEXT_MODEL_ALIAS = 'model_alias';
    public const CONTEXT_HELPER_ALIAS = 'helper_alias';
    public const CONTEXT_BLOCK_ALIAS = 'block_alias';
    public const CONTEXT_RESOURCE_MODEL_ALIAS = 'resource_model_alias';
    public const CONTEXT_CONFIG_PATH = 'config_path';
    public const CONTEXT_EVENT_NAME = 'event_name';
    public const CONTEXT_XML_METHOD = 'xml_method';
    public const CONTEXT_CRON_RUN_MODEL = 'cron_run_model';
    public const CONTEXT_TEMPLATE_PATH = 'template_path';
    public const CONTEXT_LAYOUT_HANDLE = 'layout_handle';
    public const CONTEXT_FQCN = 'fqcn';
    public const CONTEXT_XML_ELEMENT_NAME = 'xml_element_name';

    /**
     * Structural rules keyed by filename pattern.
     * Each entry: XML parent path pattern => list of valid child element names.
     * Paths use '*' to represent dynamic (user-defined) element names.
     * Checked in order — first match wins within each file group.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const XML_STRUCTURAL_RULES = [
        'system.xml' => [
            'config' => ['tabs', 'sections'],
            'config/tabs/*' => ['label', 'sort_order'],
            'config/sections/*' => ['label', 'tab', 'sort_order', 'show_in_default', 'show_in_website', 'show_in_store', 'groups', 'class'],
            'config/sections/*/groups/*' => ['label', 'sort_order', 'show_in_default', 'show_in_website', 'show_in_store', 'fields', 'frontend_model', 'expanded', 'comment'],
            'config/sections/*/groups/*/fields/*' => ['label', 'frontend_type', 'source_model', 'backend_model', 'frontend_model', 'frontend_class', 'validate', 'sort_order', 'show_in_default', 'show_in_website', 'show_in_store', 'comment', 'tooltip', 'depends', 'config_path'],
        ],
        'config.xml' => [
            'config' => ['modules', 'global', 'frontend', 'adminhtml', 'admin', 'crontab', 'default', 'stores', 'websites'],
            'config/global' => ['models', 'blocks', 'helpers', 'resources', 'events', 'cache', 'template', 'fieldsets', 'sales', 'pdf', 'page'],
            'config/global/models/*' => ['class', 'resourceModel'],
            'config/global/models/*/entities/*' => ['table'],
            'config/global/blocks/*' => ['class'],
            'config/global/helpers/*' => ['class'],
            'config/global/resources/*' => ['connection'],
            'config/global/resources/*/connection' => ['use'],
            'config/global/events/*/observers/*' => ['class', 'method', 'type'],
            'config/global/cache/types/*' => ['label', 'description', 'tags'],
            'config/frontend' => ['routers', 'layout', 'translate', 'events', 'secure_url'],
            'config/frontend/routers/*' => ['use', 'args'],
            'config/frontend/routers/*/args' => ['module', 'frontName'],
            'config/frontend/layout/updates/*' => ['file'],
            'config/frontend/translate/modules/*' => ['files'],
            'config/frontend/translate/modules/*/files' => ['default'],
            'config/frontend/events/*/observers/*' => ['class', 'method', 'type'],
            'config/adminhtml' => ['routers', 'layout', 'translate', 'events', 'menu', 'acl'],
            'config/adminhtml/routers/*' => ['use', 'args'],
            'config/adminhtml/routers/*/args' => ['module', 'frontName'],
            'config/adminhtml/layout/updates/*' => ['file'],
            'config/adminhtml/translate/modules/*' => ['files'],
            'config/adminhtml/translate/modules/*/files' => ['default'],
            'config/adminhtml/events/*/observers/*' => ['class', 'method', 'type'],
            'config/admin/routers/*/args' => ['module', 'frontName'],
            'config/crontab/jobs/*' => ['schedule', 'run'],
            'config/crontab/jobs/*/schedule' => ['cron_expr', 'config_path'],
            'config/crontab/jobs/*/run' => ['model'],
        ],
        'adminhtml.xml' => [
            'config' => ['menu', 'acl'],
            'config/menu/*' => ['title', 'sort_order', 'action', 'children', 'depends'],
            'config/menu/*/children/*' => ['title', 'sort_order', 'action', 'children', 'depends'],
            'config/menu/*/children/*/children/*' => ['title', 'sort_order', 'action', 'children', 'depends'],
            'config/acl/resources/admin/children/*' => ['title', 'sort_order', 'children'],
            'config/acl/resources/admin/children/*/children/*' => ['title', 'sort_order', 'children'],
            'config/acl/resources/admin/children/*/children/*/children/*' => ['title', 'sort_order', 'children'],
        ],
        '*' => [
            'layout/*' => ['block', 'reference', 'remove', 'update', 'label'],
            'layout/*/reference' => ['block', 'action', 'remove'],
            'layout/*/block' => ['block', 'action', 'remove'],
            'layout/*/reference/block' => ['block', 'action', 'remove'],
        ],
    ];

    private const CALL_PATTERNS = [
        self::CONTEXT_MODEL_ALIAS => [
            'Mage::getModel',
            'Mage::getSingleton',
        ],
        self::CONTEXT_HELPER_ALIAS => [
            'Mage::helper',
        ],
        self::CONTEXT_BLOCK_ALIAS => [
            '->createBlock',
            '->getBlockSingleton',
            'getLayout()->createBlock',
        ],
        self::CONTEXT_RESOURCE_MODEL_ALIAS => [
            'Mage::getResourceModel',
            'Mage::getResourceSingleton',
        ],
        self::CONTEXT_CONFIG_PATH => [
            'Mage::getStoreConfig',
            'Mage::getStoreConfigFlag',
        ],
        self::CONTEXT_EVENT_NAME => [
            'Mage::dispatchEvent',
        ],
    ];

    /**
     * Tags whose meaning is unambiguous regardless of XML path.
     */
    private const XML_TAG_TO_CONTEXT = [
        'source_model'  => self::CONTEXT_MODEL_ALIAS,
        'backend_model' => self::CONTEXT_MODEL_ALIAS,
        'frontend_model' => self::CONTEXT_BLOCK_ALIAS,
        'render'        => self::CONTEXT_BLOCK_ALIAS,
        'renderer'      => self::CONTEXT_BLOCK_ALIAS,
        'resourceModel' => self::CONTEXT_RESOURCE_MODEL_ALIAS,
    ];

    /**
     * Attributes whose meaning is unambiguous regardless of XML path.
     */
    private const XML_ATTR_TO_CONTEXT = [
        'type'     => self::CONTEXT_BLOCK_ALIAS,
        'template' => self::CONTEXT_TEMPLATE_PATH,
        'ifconfig' => self::CONTEXT_CONFIG_PATH,
        'handle'   => self::CONTEXT_LAYOUT_HANDLE,
    ];

    /**
     * XML path patterns that determine what a <class> tag means.
     * Checked in order — first match wins. The path is the parent
     * path (excluding the <class> tag itself).
     */
    private const XML_CLASS_PATH_RULES = [
        // Observer class: always a model alias
        '/\/events\/[^\/]+\/observers\/[^\/]+$/' => self::CONTEXT_MODEL_ALIAS,
        // Total collectors: model alias
        '/\/totals\/[^\/]+$/' => self::CONTEXT_MODEL_ALIAS,
        // Global search: model alias
        '/\/global_search\/[^\/]+$/' => self::CONTEXT_MODEL_ALIAS,
        // Model/block/helper group class prefix: FQCN
        '/\/models\/[^\/]+$/' => self::CONTEXT_FQCN,
        '/\/blocks\/[^\/]+$/' => self::CONTEXT_FQCN,
        '/\/helpers\/[^\/]+$/' => self::CONTEXT_FQCN,
        // Resource setup class: FQCN
        '/\/setup$/' => self::CONTEXT_FQCN,
        // Router class: FQCN
        '/\/routers\/[^\/]+$/' => self::CONTEXT_FQCN,
    ];

    /**
     * XML path patterns that determine what a <model> tag means.
     */
    private const XML_MODEL_PATH_RULES = [
        // Cron run callback: alias::method
        '/\/jobs\/[^\/]+\/run$/' => self::CONTEXT_CRON_RUN_MODEL,
    ];

    /** @var array<string, list<string>>|null */
    private ?array $completionPatterns = null;

    /** @var array<string, list<string>>|null */
    private ?array $cursorPatterns = null;

    /**
     * @return array<string, list<string>>
     */
    private function getCompletionPatterns(): array
    {
        if ($this->completionPatterns === null) {
            $this->completionPatterns = [];
            foreach (self::CALL_PATTERNS as $context => $calls) {
                foreach ($calls as $call) {
                    $escaped = preg_quote($call, '/');
                    $this->completionPatterns[$context][] = "/{$escaped}\\(\\s*['\"]([^'\"]*)?$/";
                }
            }
        }
        return $this->completionPatterns;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getCursorPatterns(): array
    {
        if ($this->cursorPatterns === null) {
            $this->cursorPatterns = [];
            foreach (self::CALL_PATTERNS as $context => $calls) {
                foreach ($calls as $call) {
                    $escaped = preg_quote($call, '/');
                    $this->cursorPatterns[$context][] = "/{$escaped}\\(\\s*['\"]([^'\"]+)['\"]\\)/";
                }
            }
        }
        return $this->cursorPatterns;
    }

    public static function isXmlUri(string $uri): bool
    {
        return (bool) preg_match('/\.xml$/i', parse_url($uri, PHP_URL_PATH) ?? '');
    }

    /**
     * Detect what context the cursor is in for completion.
     *
     * @return array{context: string, prefix: string, prefixStart: int}
     */
    public function detect(string $text, int $line, int $character, string $uri = ''): array
    {
        if (self::isXmlUri($uri)) {
            return $this->detectXml($text, $line, $character, $uri);
        }
        return $this->detectPhp($text, $line, $character);
    }

    /**
     * Detect context for an existing alias string at cursor position (for definition/hover).
     *
     * @return array{context: string, alias: string, ...}|null
     */
    public function detectAtCursor(string $text, int $line, int $character, string $uri = ''): ?array
    {
        if (self::isXmlUri($uri)) {
            return $this->detectXmlAtCursor($text, $line, $character);
        }
        return $this->detectPhpAtCursor($text, $line, $character);
    }

    private function detectPhp(string $text, int $line, int $character): array
    {
        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return ['context' => self::CONTEXT_NONE, 'prefix' => '', 'prefixStart' => $character];
        }

        $textBeforeCursor = substr($lines[$line], 0, $character);

        foreach ($this->getCompletionPatterns() as $context => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $textBeforeCursor, $matches)) {
                    $prefix = $matches[1] ?? '';
                    return [
                        'context' => $context,
                        'prefix' => $prefix,
                        'prefixStart' => $character - strlen($prefix),
                    ];
                }
            }
        }

        return ['context' => self::CONTEXT_NONE, 'prefix' => '', 'prefixStart' => $character];
    }

    private function detectPhpAtCursor(string $text, int $line, int $character): ?array
    {
        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return null;
        }

        $lineText = $lines[$line];

        foreach ($this->getCursorPatterns() as $context => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $lineText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($matches as $match) {
                        $aliasStart = $match[1][1];
                        $aliasEnd = $aliasStart + strlen($match[1][0]);
                        if ($character >= $aliasStart && $character <= $aliasEnd) {
                            return [
                                'context' => $context,
                                'alias' => $match[1][0],
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    private function detectXml(string $text, int $line, int $character, string $uri = ''): array
    {
        $none = ['context' => self::CONTEXT_NONE, 'prefix' => '', 'prefixStart' => $character];

        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return $none;
        }

        $textBeforeCursor = substr($lines[$line], 0, $character);
        $parentPath = $this->getXmlParentPath($lines, $line);

        // Attribute value: attrName="partial
        if (preg_match('/(\w+)=["\']([^"\']*)?$/', $textBeforeCursor, $m)) {
            $attrName = $m[1];
            $prefix = $m[2] ?? '';
            $context = $this->resolveXmlAttributeContext($attrName);
            if ($context !== null) {
                return [
                    'context' => $context,
                    'prefix' => $prefix,
                    'prefixStart' => $character - strlen($prefix),
                ];
            }
        }

        // Tag content: <tagName>partial
        if (preg_match('/<(\w+)>([^<]*)?$/', $textBeforeCursor, $m)) {
            $tagName = $m[1];
            $prefix = $m[2] ?? '';
            $context = $this->resolveXmlTagContext($tagName, $prefix, $parentPath);
            if ($context !== self::CONTEXT_NONE) {
                return [
                    'context' => $context,
                    'prefix' => $prefix,
                    'prefixStart' => $character - strlen($prefix),
                ];
            }
        }

        // Structural element name: <partial at the start of a new element
        // No trailing \s* — avoids matching "<block " (attribute context)
        if (preg_match('/<([a-zA-Z_][\w.-]*)?$/', $textBeforeCursor, $m)) {
            $prefix = $m[1] ?? '';
            $suggestions = $this->getStructuralSuggestions($parentPath, $uri);
            if ($suggestions !== []) {
                return [
                    'context' => self::CONTEXT_XML_ELEMENT_NAME,
                    'prefix' => $prefix,
                    'prefixStart' => $character - strlen($prefix),
                    'suggestions' => $suggestions,
                ];
            }
        }

        return $none;
    }

    private function detectXmlAtCursor(string $text, int $line, int $character): ?array
    {
        $lines = explode("\n", $text);
        if (!isset($lines[$line])) {
            return null;
        }

        $lineText = $lines[$line];
        $parentPath = $this->getXmlParentPath($lines, $line);

        // Attribute value: attrName="value"
        if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $lineText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $valueStart = $match[2][1];
                $valueEnd = $valueStart + strlen($match[2][0]);
                if ($character >= $valueStart && $character <= $valueEnd) {
                    $attrName = $match[1][0];
                    $value = $match[2][0];
                    return $this->resolveXmlAttributeAtCursor($attrName, $value, $lines, $line);
                }
            }
        }

        // Tag content: <tagName>value</tagName>
        if (preg_match_all('/<(\w+)>([^<]+)<\/\1>/', $lineText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $valueStart = $match[2][1];
                $valueEnd = $valueStart + strlen($match[2][0]);
                if ($character >= $valueStart && $character <= $valueEnd) {
                    $tagName = $match[1][0];
                    $value = trim($match[2][0]);
                    return $this->resolveXmlTagAtCursor($tagName, $value, $lines, $line, $parentPath);
                }
            }
        }

        // Dynamic tag inside <rewrite>: <alias_suffix>FQCN</alias_suffix>
        if (str_contains($parentPath, '/rewrite')) {
            if (preg_match_all('/<(\w+)>([A-Z][^<]+)<\/\1>/', $lineText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $valueStart = $match[2][1];
                    $valueEnd = $valueStart + strlen($match[2][0]);
                    if ($character >= $valueStart && $character <= $valueEnd) {
                        return [
                            'context' => self::CONTEXT_FQCN,
                            'alias' => trim($match[2][0]),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve context for an XML tag based on its name, value, and parent path.
     */
    private function resolveXmlTagContext(string $tagName, string $value, string $parentPath): string
    {
        return self::XML_TAG_TO_CONTEXT[$tagName] ?? match ($tagName) {
            'class' => $this->resolveClassTagContext($value, $parentPath),
            'model' => $this->resolveModelTagContext($value, $parentPath),
            'block' => str_contains($value, '/') ? self::CONTEXT_BLOCK_ALIAS : self::CONTEXT_NONE,
            'method' => self::CONTEXT_XML_METHOD,
            'helper' => self::CONTEXT_HELPER_ALIAS,
            'template', 'file' => (str_ends_with($value, '.phtml') || str_ends_with($value, '.html'))
                ? self::CONTEXT_TEMPLATE_PATH
                : self::CONTEXT_NONE,
            default => self::CONTEXT_NONE,
        };
    }

    /**
     * Resolve context for an XML tag at cursor position (hover/definition).
     */
    private function resolveXmlTagAtCursor(string $tagName, string $value, array $lines, int $lineNum, string $parentPath): ?array
    {
        if (isset(self::XML_TAG_TO_CONTEXT[$tagName])) {
            return [
                'context' => self::XML_TAG_TO_CONTEXT[$tagName],
                'alias' => $value,
            ];
        }

        return match ($tagName) {
            'class' => [
                'context' => $this->resolveClassTagContext($value, $parentPath),
                'alias' => $value,
            ],

            'model' => $this->resolveModelTagAtCursor($value, $parentPath),

            'block' => str_contains($value, '/')
                ? ['context' => self::CONTEXT_BLOCK_ALIAS, 'alias' => $value]
                : null,

            'helper' => str_contains($value, '/')
                ? ['context' => self::CONTEXT_HELPER_ALIAS, 'alias' => $value]
                : null,

            'method' => [
                'context' => self::CONTEXT_XML_METHOD,
                'alias' => $value,
                'method' => $value,
                'classAlias' => $this->findSiblingTagValue($lines, $lineNum, 'class'),
                'classType' => 'model',
            ],

            'template', 'file' => (str_ends_with($value, '.phtml') || str_ends_with($value, '.html'))
                ? ['context' => self::CONTEXT_TEMPLATE_PATH, 'alias' => $value]
                : null,

            default => null,
        };
    }

    /**
     * Determine what a <class> tag means based on its XML parent path.
     */
    private function resolveClassTagContext(string $value, string $parentPath): string
    {
        foreach (self::XML_CLASS_PATH_RULES as $pattern => $context) {
            if (preg_match($pattern, $parentPath)) {
                return $context;
            }
        }

        // Fallback: if value contains '/' it's an alias, otherwise FQCN
        return str_contains($value, '/') ? self::CONTEXT_MODEL_ALIAS : self::CONTEXT_FQCN;
    }

    /**
     * Determine what a <model> tag means based on its XML parent path.
     */
    private function resolveModelTagContext(string $value, string $parentPath): string
    {
        foreach (self::XML_MODEL_PATH_RULES as $pattern => $context) {
            if (preg_match($pattern, $parentPath)) {
                return $context;
            }
        }

        if (str_contains($value, '::')) {
            return self::CONTEXT_CRON_RUN_MODEL;
        }

        return str_contains($value, '/') ? self::CONTEXT_MODEL_ALIAS : self::CONTEXT_FQCN;
    }

    /**
     * Resolve a <model> tag at cursor for hover/definition.
     */
    private function resolveModelTagAtCursor(string $value, string $parentPath): ?array
    {
        $context = $this->resolveModelTagContext($value, $parentPath);

        if ($context === self::CONTEXT_CRON_RUN_MODEL && str_contains($value, '::')) {
            [$alias, $method] = explode('::', $value, 2);
            return [
                'context' => self::CONTEXT_CRON_RUN_MODEL,
                'alias' => $value,
                'classAlias' => $alias,
                'method' => $method,
            ];
        }

        if ($context === self::CONTEXT_NONE) {
            return null;
        }

        return [
            'context' => $context,
            'alias' => $value,
        ];
    }

    /**
     * Resolve context for an XML attribute (simple, unambiguous).
     */
    private function resolveXmlAttributeContext(string $attrName): ?string
    {
        if ($attrName === 'method') {
            return self::CONTEXT_XML_METHOD;
        }

        return self::XML_ATTR_TO_CONTEXT[$attrName] ?? null;
    }

    /**
     * Resolve an XML attribute at cursor for hover/definition.
     */
    private function resolveXmlAttributeAtCursor(
        string $attrName,
        string $value,
        array $lines,
        int $lineNum,
    ): ?array {
        return match ($attrName) {
            'type' => str_contains($value, '/')
                ? ['context' => self::CONTEXT_BLOCK_ALIAS, 'alias' => $value]
                : null,
            'template' => ['context' => self::CONTEXT_TEMPLATE_PATH, 'alias' => $value],
            'ifconfig' => ['context' => self::CONTEXT_CONFIG_PATH, 'alias' => $value],
            'handle' => ['context' => self::CONTEXT_LAYOUT_HANDLE, 'alias' => $value],
            'method' => [
                'context' => self::CONTEXT_XML_METHOD,
                'alias' => $value,
                'method' => $value,
                'classAlias' => $this->findBlockTypeFromActionContext($lines, $lineNum),
                'classType' => 'block',
            ],
            'helper' => $this->resolveHelperAttribute($value),
            default => null,
        };
    }

    /**
     * Parse layout helper attribute: "module/helper_class/methodName"
     */
    private function resolveHelperAttribute(string $value): array
    {
        $parts = explode('/', $value);
        if (count($parts) < 3) {
            return [
                'context' => self::CONTEXT_HELPER_ALIAS,
                'alias' => $value,
            ];
        }

        $method = array_pop($parts);
        $helperAlias = implode('/', $parts);

        return [
            'context' => self::CONTEXT_XML_METHOD,
            'alias' => $value,
            'method' => $method,
            'classAlias' => $helperAlias,
            'classType' => 'helper',
        ];
    }

    /**
     * Match the current XML parent path against structural rules and return
     * valid child element names. Rules use '*' to match dynamic element names.
     * File-specific rules are checked first, then fallback '*' rules.
     *
     * @return list<string>
     */
    private function getStructuralSuggestions(string $parentPath, string $uri): array
    {
        if ($parentPath === '') {
            return [];
        }

        $filename = basename(parse_url($uri, PHP_URL_PATH) ?? '');
        $parentParts = explode('/', $parentPath);

        $ruleSets = [];
        if ($filename !== '' && isset(self::XML_STRUCTURAL_RULES[$filename])) {
            $ruleSets[] = self::XML_STRUCTURAL_RULES[$filename];
        }
        $ruleSets[] = self::XML_STRUCTURAL_RULES['*'];

        foreach ($ruleSets as $rules) {
            foreach ($rules as $rulePattern => $children) {
                $ruleParts = explode('/', $rulePattern);

                if (count($ruleParts) !== count($parentParts)) {
                    continue;
                }

                $match = true;
                for ($i = 0, $count = count($ruleParts); $i < $count; $i++) {
                    if ($ruleParts[$i] !== '*' && $ruleParts[$i] !== $parentParts[$i]) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    return $children;
                }
            }
        }

        return [];
    }

    /**
     * Build the XML parent path by scanning tags from the start of the
     * document up to (but not including) the target line. Maintains a
     * stack of open tags; inline tags (opened and closed on the same line)
     * are ignored since they are leaf nodes, not parents.
     *
     * Example: for cursor on <class> inside an observer definition,
     * returns "config/global/events/some_event/observers/my_observer"
     */
    private function getXmlParentPath(array $lines, int $targetLine): string
    {
        $stack = [];
        $inComment = false;

        for ($i = 0; $i < $targetLine; $i++) {
            $line = $lines[$i];
            $inComment = $this->processXmlLineForPath($line, $stack, $inComment);
        }

        return implode('/', $stack);
    }

    /**
     * Process a single XML line, updating the tag stack.
     * Handles opening tags, closing tags, inline (self-contained) tags,
     * and multi-line comments.
     *
     * @return bool Whether we are still inside a multi-line comment after this line
     */
    private function processXmlLineForPath(string $line, array &$stack, bool $inComment): bool
    {
        if ($inComment) {
            if (str_contains($line, '-->')) {
                $line = substr($line, strpos($line, '-->') + 3);
                $inComment = false;
            } else {
                return true;
            }
        }

        // Strip inline comments and detect unclosed comment starts
        while (($commentStart = strpos($line, '<!--')) !== false) {
            $commentEnd = strpos($line, '-->', $commentStart + 4);
            if ($commentEnd !== false) {
                $line = substr($line, 0, $commentStart) . substr($line, $commentEnd + 3);
            } else {
                $line = substr($line, 0, $commentStart);
                $inComment = true;
                break;
            }
        }

        // Find all XML tags on this line
        preg_match_all('/<(\/?)([\w:.-]+)([^>]*?)(\/?)\s*>/', $line, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $isClosing = $m[1] === '/';
            $tagName = $m[2];
            $isSelfClosing = $m[4] === '/';

            if ($isSelfClosing) {
                continue;
            }

            if ($isClosing) {
                // Pop matching tag from stack (search from end)
                for ($j = count($stack) - 1; $j >= 0; $j--) {
                    if ($stack[$j] === $tagName) {
                        array_splice($stack, $j);
                        break;
                    }
                }
            } else {
                // Check if the tag is also closed on this same line (inline tag)
                if (preg_match('/<' . preg_quote($tagName, '/') . '(?:\s[^>]*)?>.*<\/' . preg_quote($tagName, '/') . '>/', $line)) {
                    continue;
                }
                $stack[] = $tagName;
            }
        }

        return $inComment;
    }

    /**
     * Search sibling lines for a <tagName>value</tagName> near the current line.
     * Used to find <class> sibling of <method> in observer definitions.
     */
    private function findSiblingTagValue(array $lines, int $lineNum, string $tagName): ?string
    {
        $searchRange = 5;
        $start = max(0, $lineNum - $searchRange);
        $end = min(count($lines) - 1, $lineNum + $searchRange);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $lineNum) {
                continue;
            }
            if (preg_match("/<{$tagName}>([^<]+)<\/{$tagName}>/", $lines[$i], $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Find the block type from the parent <block type="..."> of an <action method="...">.
     */
    private function findBlockTypeFromActionContext(array $lines, int $lineNum): ?string
    {
        for ($i = $lineNum - 1; $i >= max(0, $lineNum - 30); $i--) {
            if (preg_match('/<block\b[^>]*\btype=["\']([^"\']+)["\']/', $lines[$i], $m)) {
                return $m[1];
            }
            if (str_contains($lines[$i], '</block>')) {
                break;
            }
        }

        return null;
    }
}
