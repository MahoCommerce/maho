<?php

/**
 * The documentation pages of the core and of the enabled modules, by audience.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Docs;

use Composer\InstalledVersions;

/**
 * The core keeps its pages in the docs folder at the root of the Maho package. A module declares
 * its folders in its config.xml, one for each audience, with a path from the module folder:
 *
 *     <global><docs><user>docs/user</user></docs></global>
 *
 * Mage_Core_Model_Config moves each declaration under the name of its module when it loads the
 * module, so a disabled module has no pages. A page is a .md file: an optional front matter with a
 * description line, then a "#" title.
 */
final class Library
{
    public const AUDIENCE_USER = 'user';
    public const AUDIENCE_DEVELOPER = 'developer';
    public const AUDIENCE_SYSADMIN = 'sysadmin';
    public const AUDIENCES = [self::AUDIENCE_USER, self::AUDIENCE_DEVELOPER, self::AUDIENCE_SYSADMIN];

    public const XML_PATH_DOCS = 'global/docs';
    public const CORE_SOURCE = 'core';
    public const CORE_PACKAGE = 'mahocommerce/maho';
    public const MAX_PAGE_CHARS = 40000;

    /** The folders of the core, under docs/ in the Maho package, with their audience. */
    private const CORE_FOLDERS = [
        'user-guide' => self::AUDIENCE_USER,
        'hosting' => self::AUDIENCE_SYSADMIN,
        'developer' => self::AUDIENCE_DEVELOPER,
        'api' => self::AUDIENCE_DEVELOPER,
    ];

    /** @var array<string, list<Page>> */
    private array $pages = [];

    /**
     * @param list<Source>|null $sources null reads the core folders and the folders that the modules declare
     */
    public function __construct(private ?array $sources = null) {}

    /**
     * @return list<Source>
     */
    public function sources(): array
    {
        return $this->sources ??= [...self::coreSources(), ...self::moduleSources()];
    }

    /**
     * The pages for the audiences, in the order of the sources, and in path order inside a source.
     *
     * @param list<string> $audiences
     * @return list<Page>
     */
    public function pages(array $audiences): array
    {
        $key = implode(',', $audiences);
        if (isset($this->pages[$key])) {
            return $this->pages[$key];
        }
        $pages = [];
        foreach ($this->sources() as $source) {
            if (!in_array($source->audience, $audiences, true)) {
                continue;
            }
            foreach (self::markdownFiles($source->dir) as $path) {
                $pages[] = self::describe($source, $path);
            }
        }

        return $this->pages[$key] = $pages;
    }

    /**
     * @param list<string> $audiences
     */
    public function page(string $id, array $audiences): ?Page
    {
        foreach ($this->pages($audiences) as $page) {
            if ($page->id === $id) {
                return $page;
            }
        }

        return null;
    }

    public static function stripFrontMatter(string $text): string
    {
        return preg_replace('/\A---\R.*?\R---\R/s', '', $text, 1) ?? $text;
    }

    /**
     * @return list<Source>
     */
    private static function coreSources(): array
    {
        $root = InstalledVersions::isInstalled(self::CORE_PACKAGE) ? InstalledVersions::getInstallPath(self::CORE_PACKAGE) : null;
        if ($root === null) {
            return [];
        }
        $sources = [];
        foreach (self::CORE_FOLDERS as $folder => $audience) {
            $dir = realpath("$root/docs/$folder");
            if ($dir !== false && is_dir($dir)) {
                $sources[] = new Source(self::CORE_SOURCE . '/' . $folder, $audience, $dir);
            }
        }

        return $sources;
    }

    /**
     * @return list<Source>
     */
    private static function moduleSources(): array
    {
        $docs = \Mage::getConfig()->getNode(self::XML_PATH_DOCS);
        if ($docs === false) {
            return [];
        }
        $sources = [];
        foreach ($docs->children() as $module => $folders) {
            $moduleDir = realpath((string) \Maho::findFile(\Mage::getConfig()->getModuleDir('', $module)));
            if ($moduleDir === false) {
                continue;
            }
            foreach ($folders->children() as $audience => $path) {
                $dir = realpath($moduleDir . '/' . trim((string) $path, '/'));
                // The folder must stay inside the module, also through ".." or a symbolic link.
                if (!in_array($audience, self::AUDIENCES, true) || $dir === false || !is_dir($dir) || !str_starts_with($dir, $moduleDir . '/')) {
                    continue;
                }
                $sources[] = new Source($module, $audience, $dir);
            }
        }

        return $sources;
    }

    /**
     * @return list<string> the .md files under $dir, as paths from $dir, sorted
     */
    private static function markdownFiles(string $dir): array
    {
        $files = [];
        $root = realpath($dir) ?: $dir;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            // A symbolic link must not lead the tool to a file outside the folder.
            if ($file->isFile() && $file->getExtension() === 'md' && !str_starts_with($file->getFilename(), '.') && str_starts_with((string) $file->getRealPath(), $root . '/')) {
                $files[] = substr($file->getPathname(), strlen($dir) + 1);
            }
        }
        sort($files);

        return $files;
    }

    private static function describe(Source $source, string $path): Page
    {
        $file = $source->dir . '/' . $path;
        $text = (string) file_get_contents($file);
        $description = '';
        if (preg_match('/\A---\R(.*?)\R---\R/s', $text, $frontMatter) && preg_match('/^description:\s*(.+)$/m', $frontMatter[1], $match)) {
            $description = trim($match[1], " \t\"'");
        }
        // A "#" line inside a code block is a comment, not the title.
        $title = preg_match('/^#\s+(.+)$/m', (string) preg_replace('/^(```|~~~).*?^\1/ms', '', self::stripFrontMatter($text)), $match)
            ? trim(strip_tags($match[1]))
            : basename($path, '.md');

        return new Page($source->name . '/' . $path, $title, $description, $source, $file);
    }
}
