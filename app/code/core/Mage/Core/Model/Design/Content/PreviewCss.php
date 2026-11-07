<?php

/**
 * The stylesheet of the Tailwind browser build in the preview of the content editor.
 *
 * The preview page already loads css/styles.css, or its copy with the classes of the saved
 * content. The browser build adds the utilities of the classes that only the draft has. It
 * imports the utilities only, and it takes the theme tokens as references: the values stay
 * the ones of the loaded stylesheets, so css/theme.css and the theme settings still apply.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Design_Content_PreviewCss
{
    public const SCRIPT = 'tailwindcss-browser.js';

    /**
     * The text/tailwindcss source for the theme of $stylesFile, or null when no Tailwind theme
     * compiles that file. $stylesFile is the absolute path of a resolved css/styles.css.
     */
    public function getCss(string $stylesFile): ?string
    {
        $entry = null;
        foreach (Mage::getSingleton('core/design_content_bundle')->getThemes() as $paths) {
            if ($paths['styles'] === $stylesFile) {
                $entry = $paths['entry'];
            }
        }
        if ($entry === null) {
            return null;
        }

        // The imports come after the @theme block: the preload scanner of Chrome reads the leading
        // @import rules of every style element, also of this type, and requests them from the store
        return "@theme reference {\n" . implode("\n", $this->collectDeclarations($entry)) . "\n}\n"
            . '@import "tailwindcss/theme.css" theme(reference);' . "\n"
            . '@import "tailwindcss/utilities.css" layer(utilities);' . "\n";
    }

    /**
     * The custom properties of the @theme blocks and of the DaisyUI themes in $file and in the
     * local files that it imports, by name. A later file wins, as in the build.
     *
     * @param array<string, true> $visited
     * @return array<string, string>
     */
    public function collectDeclarations(string $file, array &$visited = []): array
    {
        $file = (string) realpath($file);
        if ($file === '' || isset($visited[$file])) {
            return [];
        }
        $visited[$file] = true;

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
        $declarations = [];
        preg_match_all('/@import\s+["\'](\.{1,2}\/[^"\']+)["\']|@theme\b[^{;]*\{([^{}]*)\}|@plugin\s+["\']daisyui\/theme["\']\s*\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] !== '') {
                $declarations = [...$declarations, ...$this->collectDeclarations(dirname($file) . '/' . $match[1], $visited)];
                continue;
            }
            $body = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');
            preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $body, $properties, PREG_SET_ORDER);
            foreach ($properties as $property) {
                $declarations[$property[1]] = '    ' . $property[1] . ': ' . trim($property[2]) . ';';
            }
        }

        return $declarations;
    }
}
