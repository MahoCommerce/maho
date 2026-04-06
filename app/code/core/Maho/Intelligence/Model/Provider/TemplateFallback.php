<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_TemplateFallback
{
    /**
     * Get the theme fallback chain for a template, showing all locations
     * where the template could exist and which one is actually resolved.
     */
    public function getOverrides(string $templatePath, string $area = 'frontend'): array
    {
        $designPackage = Mage::getSingleton('core/design_package');
        $resolvedFile = $designPackage->getTemplateFilename($templatePath, ['_area' => $area]);

        $locations = $this->buildFallbackLocations($templatePath, $area);

        return [
            'template' => $templatePath,
            'area' => $area,
            'resolved_file' => file_exists($resolvedFile) ? $this->relativePath($resolvedFile) : null,
            'fallback_chain' => $locations,
        ];
    }

    private function buildFallbackLocations(string $templatePath, string $area): array
    {
        $designRoot = Mage::getBaseDir('design');
        $locations = [];

        if ($area === 'frontend') {
            $package = Mage::getSingleton('core/design_package')->getPackageName();
            $theme = Mage::getSingleton('core/design_package')->getTheme('template');

            $paths = [
                "{$area}/{$package}/{$theme}" => "{$designRoot}/{$area}/{$package}/{$theme}/template/{$templatePath}",
                "{$area}/{$package}/default" => "{$designRoot}/{$area}/{$package}/default/template/{$templatePath}",
                "{$area}/base/default" => "{$designRoot}/{$area}/base/default/template/{$templatePath}",
            ];
        } else {
            $paths = [
                "{$area}/default/default" => "{$designRoot}/{$area}/default/default/template/{$templatePath}",
            ];
        }

        foreach ($paths as $label => $fullPath) {
            $exists = file_exists($fullPath);
            $locations[] = [
                'theme' => $label,
                'path' => $this->relativePath($fullPath),
                'exists' => $exists,
            ];
        }

        return $locations;
    }

    /**
     * Get all templates that are overridden in a custom theme (not base/default).
     */
    public function getThemeOverrides(string $area = 'frontend'): array
    {
        $designRoot = Mage::getBaseDir('design');
        $overrides = [];

        if ($area === 'frontend') {
            $package = Mage::getSingleton('core/design_package')->getPackageName();
            $theme = Mage::getSingleton('core/design_package')->getTheme('template');

            if ($package === 'base' && $theme === 'default') {
                return [];
            }

            $themeDir = "{$designRoot}/{$area}/{$package}/{$theme}/template";
            $baseDir = "{$designRoot}/{$area}/base/default/template";
        } else {
            return [];
        }

        if (!is_dir($themeDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!in_array($file->getExtension(), ['phtml', 'html'], true)) {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen($themeDir) + 1);
            $baseExists = file_exists("{$baseDir}/{$relativePath}");

            $overrides[] = [
                'template' => $relativePath,
                'theme_file' => $this->relativePath($file->getPathname()),
                'overrides_base' => $baseExists,
            ];
        }

        usort($overrides, fn($a, $b) => strcmp($a['template'], $b['template']));

        return $overrides;
    }

    private function relativePath(string $path): string
    {
        return str_replace(BP . '/', '', $path);
    }
}
