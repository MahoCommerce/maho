<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Adminhtml_Template_Filter extends Mage_Cms_Model_Template_Filter
{
    /** Marks a path that filter() returns for {{media}}: a path on the media mount, not on disk. */
    public const MEDIA_PREFIX = 'media://';

    /**
     * Decode the image that the one {{media}} or {{skin}} directive of the editor preview names,
     * and encode it again in the format of its file extension.
     *
     * @throws Mage_Core_Exception
     */
    public function encodeDirectiveImage(string $directive): \Intervention\Image\Interfaces\EncodedImageInterface
    {
        $path = $this->filter($directive);
        if (str_starts_with($path, self::MEDIA_PREFIX)) {
            $path = substr($path, strlen(self::MEDIA_PREFIX));
            $image = Maho::getImageManager()->decodeBinary(Mage::getStorage('media')->read($path));
        } else {
            $image = Maho::getImageManager()->decodePath($path);
        }

        return $image->encodeUsingPath($path);
    }

    /**
     * Read the SVG file that the one {{media}} or {{skin}} directive of the editor preview names,
     * and remove its scripts. Return null when the file is not an SVG. GD cannot decode an SVG,
     * so encodeDirectiveImage() cannot send one.
     *
     * @throws Mage_Core_Exception
     * @throws RuntimeException when the file is not SVG that the sanitizer can make safe
     */
    public function readDirectiveSvg(string $directive): ?string
    {
        $path = $this->filter($directive);
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
            return null;
        }

        $content = str_starts_with($path, self::MEDIA_PREFIX)
            ? Mage::getStorage('media')->read(substr($path, strlen(self::MEDIA_PREFIX)))
            : (string) file_get_contents($path);

        return new \Maho\Security\SvgDocumentSanitizer()->sanitize($content);
    }

    /**
     * Resolve the one image directive the editor preview may carry: a path on the media mount
     * with MEDIA_PREFIX for {{media}}, a local file path for {{skin}}.
     *
     * The preview only serves {{media}} and {{skin}}. Anything else is refused before any
     * directive runs, so the request cannot reach {{block}}, {{config}} or the other CMS directives.
     * The parent filter swallows exceptions, so the directive method is called directly and a
     * bad path reaches the caller as an exception.
     *
     * @param string $value
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function filter($value)
    {
        $value = trim((string) $value);
        if (!preg_match_all(self::CONSTRUCTION_PATTERN, $value, $constructions, PREG_SET_ORDER)
            || count($constructions) !== 1
            || $constructions[0][0] !== $value
        ) {
            Mage::throwException(Mage::helper('cms')->__('Invalid directive.'));
        }

        $construction = $constructions[0];
        $name = strtolower($construction[1]);
        if (!in_array($name, ['media', 'skin'], true)) {
            Mage::throwException(Mage::helper('cms')->__('Invalid directive.'));
        }

        return (string) $this->{$name . 'Directive'}($construction);
    }

    /**
     * Retrieve the path of the file on the media mount with MEDIA_PREFIX, instead of its URL
     *
     * @param array $construction
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function mediaDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for media directive.');
        }

        $path = \Maho\Io::getPathWithinMount(Mage::getStorage('media'), '', (string) $params['url']);
        if ($path === null) {
            Mage::throwException('Invalid url parameter for media directive.');
        }

        return self::MEDIA_PREFIX . $path;
    }

    /**
     * Retrieve skin file local path instead of URL, so it can be read by Intervention Image
     *
     * Only url, _area, _package and _theme are used. Each of the last three must be one directory name,
     * and the file must be inside a skin directory.
     *
     * @param array $construction
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function skinDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for skin directive.');
        }

        $designParams = ['_type' => 'skin'];
        foreach (['_area', '_package', '_theme'] as $key) {
            if (!isset($params[$key])) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $params[$key])) {
                Mage::throwException('Invalid ' . $key . ' parameter for skin directive.');
            }
            $designParams[$key] = (string) $params[$key];
        }

        try {
            $path = (string) Mage::getDesign()->getFilename((string) $params['url'], $designParams);
        } catch (Exception) {
            $path = '';
        }
        if (!$this->isSkinFile($path)) {
            Mage::throwException('Invalid url parameter for skin directive.');
        }

        return $path;
    }

    /**
     * Return true when $path is inside the skin directory of Maho or of an installed package.
     */
    protected function isSkinFile(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $skinDirs = [Mage::getBaseDir('skin')];
        foreach (Maho::getInstalledPackages() as $package) {
            $skinDirs[] = $package['path'] . '/public/skin';
        }
        return array_any(array_unique($skinDirs), fn($skinDir) => \Maho\Io::getPathWithinDir($skinDir, $path) !== null);
    }
}
