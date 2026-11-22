<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

class Mage_Page_Block_Html_Head extends Mage_Core_Block_Template
{
    /**
     * Initialize template
     */
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('page/html/head.phtml');
        $this->_addThemeFonts();
        $this->_addConfiguredFonts();
    }

    /**
     * Additive to the skin's own fonts: a store that overrides only the body font still
     * renders headings in the face the theme's stylesheet names.
     */
    protected function _addConfiguredFonts(): void
    {
        $url = trim((string) Mage::getStoreConfig(Mage_Core_Model_Design_Tokens::FONT_STYLESHEET_PATH));
        $parts = $url === '' ? false : parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $this->addItem('link_rel', $this->escapeHtml($origin), 'rel="preconnect" crossorigin');
        $this->addItem('link_rel', $this->escapeHtml($url), 'rel="stylesheet"');
    }

    /**
     * The template renders this right after getCssJsHtml(), the only position that beats
     * the skin's theme.css. addItem() cannot carry it: an inline style has no item type.
     */
    public function getThemeTokensCss(): string
    {
        $css = Mage::getSingleton('core/design_tokens')->toCss();
        return $css === '' ? '' : '<style id="design-tokens">' . $css . '</style>' . PHP_EOL;
    }

    /**
     * A script that sets BREAKPOINTS to the breakpoint presets of the skin theme, such as
     * `{"mobile":770,"tablet":1023}`. app.js reads them, so its layout changes at the same widths as
     * the CSS and the content images. The admin gets no script.
     */
    public function getBreakpointsScript(): string
    {
        if (Mage::getDesign()->getArea() !== Mage_Core_Model_Design_Package::DEFAULT_AREA) {
            return '';
        }
        $json = Mage::helper('core')->jsonEncode(Mage::getSingleton('core/design_breakpoints')->getWidths());
        return '<script>var BREAKPOINTS = ' . $json . ';</script>' . PHP_EOL;
    }

    /**
     * Add the web fonts the active skin identity declares in its etc/theme.xml.
     *
     * Keyed on the skin theme: an identity is skin-only, so a layout file inside
     * it would never load, because layout resolution reads getTheme('layout')
     * instead.
     *
     * A link element, not an @import inside theme.css: the preload scanner cannot
     * see a URL inside a stylesheet, so an @import waits for theme.css to download
     * and parse before the font request even starts.
     */
    protected function _addThemeFonts(): void
    {
        $design = Mage::getDesign();
        $node = Mage::getSingleton('core/design_config')->getNode(implode('/', [
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme('skin'),
            'fonts',
        ]));
        if (!$node) {
            return;
        }

        $preconnect = trim((string) $node->preconnect);
        if ($preconnect !== '') {
            $this->addItem('link_rel', $this->escapeHtml($preconnect), 'rel="preconnect" crossorigin');
        }

        $stylesheet = trim((string) $node->stylesheet);
        if ($stylesheet !== '') {
            $this->addItem('link_rel', $this->escapeHtml($stylesheet), 'rel="stylesheet"');
        }
    }

    /**
     * Add CSS file to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $referenceName
     * @param bool $before
     * @return $this
     */
    public function addCss($name, $params = '', $referenceName = '*', $before = null)
    {
        $this->addItem('skin_css', $name, $params, null, $referenceName, $before);
        return $this;
    }

    /**
     * Add JavaScript file to HEAD entity
     *
     * @param string $name
     * @param string $params
     * @param string $referenceName
     * @param bool $before
     * @return $this
     */
    public function addJs($name, $params = '', $referenceName = '*', $before = null)
    {
        $this->addItem('js', $name, $params, null, $referenceName, $before);
        return $this;
    }

    /**
     * Add Link element to HEAD entity
     *
     * @param string $rel forward link types
     * @param string $href URI for linked resource
     * @return $this
     */
    public function addLinkRel($rel, $href)
    {
        $this->addItem('link_rel', $href, 'rel="' . $rel . '"');
        return $this;
    }

    /**
     * Add HEAD Item
     *
     * Allowed types:
     *  - js
     *  - js_css
     *  - skin_js
     *  - skin_css
     *  - rss
     *
     * @param string $type
     * @param string $name
     * @param string $params
     * @param string $cond
     * @param string $referenceName name of the item to insert the element before. If name is not found, insert at the end, * has special meaning (before all / before all)
     * @param string|bool $before If true insert before the $referenceName instead of after
     * @return $this
     */
    public function addItem($type, $name, $params = null, $cond = null, $referenceName = '*', $before = false)
    {
        // Layout XML skips a parameter with an empty element, which arrives as an object, not as ''
        $params = $params === null || (string) $params === '' ? null : (string) $params;
        $cond = $cond === null || (string) $cond === '' ? null : (string) $cond;

        if ($type === 'skin_css' && empty($params)) {
            $params = 'media="all"';
        }

        // Store flag to indicate minification should be applied later
        $shouldMinify = !Mage::app()->getStore()->isAdmin()
                       && in_array($type, ['skin_css', 'js', 'skin_js', 'js_css']);

        $this->_data['items'][$type . '/' . $name] = [
            'type' => $type,
            'name' => $name,
            'params' => $params,
            'cond' => $cond,
            'should_minify' => $shouldMinify, // Flag for deferred minification
        ];

        // that is the standard behaviour
        if ($referenceName === '*' && $before === false) {
            return $this;
        }

        $this->_sortItems($referenceName, $before, $type);

        return $this;
    }

    /**
     * Remove Item from HEAD entity
     *
     * @param string $type
     * @param string $name
     * @return $this
     */
    public function removeItem($type, $name)
    {
        unset($this->_data['items'][$type . '/' . $name]);
        return $this;
    }

    /**
     * Get HEAD HTML with CSS/JS/RSS definitions
     * (actually it also renders other elements, TODO: fix it up or rename this method)
     *
     * @return string
     */
    public function getCssJsHtml()
    {
        $items = $this->_groupItems();

        // prepare HTML
        // static and skin css
        $html = $this->_prepareStaticAndSkinElements(
            '<link rel="stylesheet" href="%s"%s >' . PHP_EOL,
            empty($items['js_css']) ? [] : $items['js_css'],
            empty($items['skin_css']) ? [] : $items['skin_css'],
        );

        // Before the scripts, also when a theme has its own head template
        $html .= $this->getBreakpointsScript();

        // static and skin javascripts
        $html .= $this->_prepareStaticAndSkinElements(
            '<script src="%s"%s></script>' . PHP_EOL,
            empty($items['js']) ? [] : $items['js'],
            empty($items['skin_js']) ? [] : $items['skin_js'],
        );
        Mage::helper('core/minify')->sendEarlyHints();

        // other stuff
        if (!empty($items['other'])) {
            $html .= $this->_prepareOtherHtmlHeadElements($items['other']) . PHP_EOL;
        }
        return $html;
    }

    /**
     * The stylesheets, the link elements (such as the web fonts) and the theme tokens of
     * this head, without the scripts. The content editor of the admin shows storefront
     * HTML with them.
     */
    public function getStylesheetHtml(): string
    {
        $items = $this->_groupItems();
        $html = $this->_prepareStaticAndSkinElements(
            '<link rel="stylesheet" href="%s"%s >' . PHP_EOL,
            $items['js_css'] ?? [],
            $items['skin_css'] ?? [],
        );
        if (!empty($items['other'])) {
            $html .= $this->_prepareOtherHtmlHeadElements($items['other']) . PHP_EOL;
        }
        return $html . $this->getThemeTokensCss();
    }

    /**
     * The items of this head whose condition is true, grouped by type, in the order of the items.
     * Types other than the CSS and JS files go to the 'other' group as HTML.
     */
    protected function _groupItems(): array
    {
        $items = [];
        foreach ($this->_data['items'] as $item) {
            if (!is_null($item['cond']) && !$this->getData($item['cond']) || !isset($item['name'])) {
                continue;
            }
            $params = empty($item['params']) ? '' : $item['params'];
            switch ($item['type']) {
                case 'js':        // js/*.js
                case 'skin_js':   // skin/*/*.js
                case 'js_css':    // js/*.css
                case 'skin_css':  // skin/*/*.css
                    $items[$item['type']][$item['name']] = $item;
                    break;
                default:
                    $this->_separateOtherHtmlHeadElements($items, $item['type'], $params, $item['name'], $item);
                    break;
            }
        }
        return $items;
    }

    /**
     * Render one HTML element for each static item, then one for each skin item, in the order of the items.
     *
     * @param string $format - HTML element format for sprintf('<element src="%s"%s>', $src, $params)
     * @param array $staticItems - items of files in the js/ folder, keyed by name
     * @param array $skinItems - items of files in the skin of the design, keyed by name
     * @return string
     */
    protected function &_prepareStaticAndSkinElements(
        $format,
        array $staticItems,
        array $skinItems,
    ) {
        $designPackage = Mage::getDesign();
        $baseJsUrl = Mage::getBaseUrl('js');
        $items = [];

        $minifyHelper = Mage::helper('core/minify');

        // get static files from the js folder, no need in lookups
        foreach ($staticItems as $name => $itemData) {
            $url = $baseJsUrl . $name;

            // Apply minification if needed
            if (!empty($itemData['should_minify'])) {
                $type = $itemData['type'];
                if ($type === 'js' && $minifyHelper->isJsMinificationEnabled()) {
                    $url = $minifyHelper->minifyJs($url);
                } elseif ($type === 'js_css' && $minifyHelper->isCssMinificationEnabled()) {
                    $url = $minifyHelper->minifyCss($url);
                }
            }
            if ($url === $baseJsUrl . $name) {
                $url = $this->_versionedUrl($url, Mage::getBaseDir('public') . DS . 'js' . DS . $name);
            }

            $items[] = [$url, $itemData['params'] ?? ''];
        }

        // lookup each file basing on current theme configuration
        foreach ($skinItems as $name => $itemData) {
            $contentUrl = $name === Mage_Core_Model_Design_Content_Bundle::STYLES_FILE
                ? Mage::getSingleton('core/design_content_bundle')->getUrl((string) $designPackage->getFilename($name, ['_type' => 'skin']))
                : null;
            $url = $skinUrl = $contentUrl ?? $designPackage->getSkinUrl($name, []);

            // Apply minification if needed
            if (!empty($itemData['should_minify'])) {
                $type = $itemData['type'];
                if ($type === 'skin_css' && $minifyHelper->isCssMinificationEnabled()) {
                    $url = $minifyHelper->minifyCss($url);
                } elseif ($type === 'skin_js' && $minifyHelper->isJsMinificationEnabled()) {
                    $url = $minifyHelper->minifyJs($url);
                }
            }
            if ($url === $skinUrl && $contentUrl === null) {
                $url = $this->_versionedUrl($url, (string) $designPackage->getFilename($name, ['_type' => 'skin']));
            }

            $items[] = [$url, $itemData['params'] ?? ''];
        }

        $html = '';
        foreach ($items as [$src, $params]) {
            $params = trim((string) $params);
            $html .= sprintf($format, $src, $params === '' ? '' : ' ' . $params);
        }
        return $html;
    }

    /**
     * Add the file modification time to the URL of a file served as it is, so a browser
     * loads a changed file instead of a cached copy. A minified file carries its time in its name.
     */
    protected function _versionedUrl(string $url, string $path): string
    {
        if (str_contains($url, '?') || !is_file($path)) {
            return $url;
        }

        return $url . '?v=' . filemtime($path);
    }

    /**
     * Classify HTML head item and queue it into "lines" array
     *
     * @see self::getCssJsHtml()
     * @param array $lines
     * @param string $itemType
     * @param string $itemParams
     * @param string $itemName
     * @param array $itemThe
     */
    protected function _separateOtherHtmlHeadElements(&$lines, $itemType, $itemParams, $itemName, $itemThe): void
    {
        $params = $itemParams ? ' ' . $itemParams : '';
        $href   = $itemName;
        switch ($itemType) {
            case 'rss':
                $lines['other'][] = sprintf(
                    '<link href="%s"%s rel="alternate" type="application/rss+xml">',
                    $href,
                    $params,
                );
                break;
            case 'link_rel':
                $lines['other'][] = sprintf('<link%s href="%s">', $params, $href);
                break;
        }
    }

    /**
     * Render arbitrary HTML head items
     *
     * @see self::getCssJsHtml()
     * @param array $items
     * @return string
     */
    protected function _prepareOtherHtmlHeadElements($items)
    {
        return implode(PHP_EOL, $items);
    }

    /**
     * Retrieve Chunked Items
     *
     * @param array $items
     * @param string $prefix
     * @param int $maxLen
     * @return array
     */
    public function getChunkedItems($items, $prefix = '', $maxLen = 450)
    {
        $chunks = [];
        $chunk  = $prefix;
        foreach ($items as $item) {
            if (strlen($chunk . ',' . $item) > $maxLen) {
                $chunks[] = $chunk;
                $chunk = $prefix;
            }
            $chunk .= ',' . $item;
        }
        $chunks[] = $chunk;
        return $chunks;
    }

    /**
     * Retrieve Content Type
     *
     * @return string
     */
    public function getContentType()
    {
        if (empty($this->_data['content_type'])) {
            $this->_data['content_type'] = $this->getMediaType() . '; charset=' . $this->getCharset();
        }
        return $this->_data['content_type'];
    }

    /**
     * Retrieve Media Type
     *
     * @return string
     */
    public function getMediaType()
    {
        if (empty($this->_data['media_type'])) {
            $this->_data['media_type'] = Mage::getStoreConfig('design/head/default_media_type');
        }
        return $this->_data['media_type'];
    }

    /**
     * Retrieve Charset
     *
     * @return string
     */
    public function getCharset()
    {
        if (empty($this->_data['charset'])) {
            $this->_data['charset'] = Mage::getStoreConfig('design/head/default_charset');
        }
        return $this->_data['charset'];
    }

    /**
     * Set title element text
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->_data['title'] = Mage::getStoreConfig('design/head/title_prefix') . ' ' . $title
            . ' ' . Mage::getStoreConfig('design/head/title_suffix');
        return $this;
    }

    /**
     * Retrieve title element text (encoded)
     *
     * @return string
     */
    public function getTitle()
    {
        if (empty($this->_data['title'])) {
            $this->_data['title'] = $this->getDefaultTitle();
        }
        return htmlspecialchars(html_entity_decode(trim($this->_data['title']), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Retrieve default title text
     *
     * @return string
     */
    public function getDefaultTitle()
    {
        return Mage::getStoreConfig('design/head/default_title');
    }

    /**
     * Retrieve content for description tag
     *
     * @return string
     */
    public function getDescription()
    {
        if (empty($this->_data['description'])) {
            $this->_data['description'] = Mage::getStoreConfig('design/head/default_description');
        }
        return $this->_data['description'];
    }

    /**
     * Retrieve content for keyvords tag
     *
     * @return string
     */
    public function getKeywords()
    {
        if (empty($this->_data['keywords'])) {
            $this->_data['keywords'] = Mage::getStoreConfig('design/head/default_keywords');
        }
        return $this->_data['keywords'];
    }

    /**
     * Retrieve URL to robots file
     *
     * @return string
     */
    public function getRobots()
    {
        if (empty($this->_data['robots'])) {
            // Check for product page
            if ($product = Mage::registry('current_product')) {
                $robots = $product->getMetaRobots();
                if ($robots) {
                    $this->_data['robots'] = $robots;
                    return $this->_data['robots'];
                }
            }

            // Check for category page. The primary category of a product page does not set the robots.
            if (($category = Mage::registry('current_category')) && $product?->getUsesPrimaryCategory() !== true) {
                $robots = $category->getMetaRobots();
                if ($robots) {
                    $this->_data['robots'] = $robots;
                    return $this->_data['robots'];
                }
            }

            // Check for CMS page
            if ($page = Mage::getSingleton('cms/page')) {
                $robots = $page->getMetaRobots();
                if ($robots) {
                    $this->_data['robots'] = $robots;
                    return $this->_data['robots'];
                }
            }

            // Fall back to default
            $this->_data['robots'] = Mage::getStoreConfig('design/head/default_robots');
        }
        return $this->_data['robots'];
    }

    public function getHreflang(): string
    {
        if (empty($this->_data['hreflang'])) {
            $this->_data['hreflang'] = [];
            $currentStoreId = Mage::app()->getStore()->getId();
            /** @var Mage_Core_Model_Resource_Store_Collection $activeStores */
            $activeStores = Mage::app()->getWebsite()->getStoreCollection()->addFieldToFilter('is_active', 1);
            if (count($activeStores) > 1) {
                $currentUrl = Mage::app()->getRequest()->getQuery('resource') ?? '';
                $currentIdPath = Mage::getModel('core/url_rewrite')
                    ->setStoreId($currentStoreId)
                    ->loadByRequestPath($currentUrl)
                    ->getIdPathForOtherStores();

                /** @var Mage_Core_Model_Store $store */
                foreach ($activeStores as $store) {
                    if ($store->getId() === $currentStoreId) {
                        continue;
                    }

                    $language = explode('_', $store->getConfig('general/locale/code'))[0];
                    $targetRequestPath = Mage::getModel('core/url_rewrite')
                        ->setStoreId($store->getId())
                        ->loadByIdPath($currentIdPath)
                        ->getRequestPath();

                    $targetUrl = $store->getUrl($targetRequestPath, [
                        '_use_rewrite' => true,
                        '_secure' => Mage::app()->getFrontController()->getRequest()->isSecure(),
                    ]);
                    if (!$targetUrl) {
                        continue;
                    }

                    $this->_data['hreflang'][] = "<link rel=\"alternate\" hreflang=\"$language\" href=\"$targetUrl\">\n";
                }
            }
        }

        return implode('', $this->_data['hreflang']);
    }

    /**
     * Get miscellaneous scripts/styles to be included in head before head closing tag
     *
     * @return string
     */
    public function getIncludes()
    {
        if (empty($this->_data['includes'])) {
            $this->_data['includes'] = Mage::getStoreConfig('design/head/includes');
        }
        return $this->_data['includes'];
    }

    /**
     * If DB file storage is on - find there, otherwise - just file_exists
     *
     * @param string $filename
     * @return bool
     */
    protected function _isFile($filename)
    {
        return is_file($filename);
    }

    /**
     * @param string $referenceName
     * @param string|bool $before
     * @param string $type
     */
    protected function _sortItems($referenceName, $before, $type): void
    {
        // Convert string values ("true"/"false") to bool
        $before = filter_var($before, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $items = $this->_data['items'];
        $newKey = array_key_last($items);
        $newVal = array_pop($items);

        $newItems = [];

        if ($referenceName === '*' && $before === true) {
            $newItems[$newKey] = $newVal;
        }

        $referenceName = $type . '/' . $referenceName;
        foreach ($items as $key => $value) {
            if ($key === $referenceName && $before === true) {
                $newItems[$newKey] = $newVal;
            }

            $newItems[$key] = $value;

            if ($key === $referenceName && $before === false) {
                $newItems[$newKey] = $newVal;
            }
        }

        // replace items only if the reference was found (otherwise insert as last item)
        if (isset($newItems[$newKey])) {
            $this->_data['items'] = $newItems;
        }
    }

    public function setCanLoadCalendarJs(?bool $value = true): static
    {
        return $this->setData('can_load_calendar_js', $value);
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function setKeywords(?string $value): static
    {
        return $this->setData('keywords', $value);
    }

    public function getCanLoadWysiwyg(): ?bool
    {
        $value = $this->getData('can_load_wysiwyg');
        return $value === null ? null : (bool) $value;
    }

    public function setCanLoadWysiwyg(?bool $value = true): static
    {
        return $this->setData('can_load_wysiwyg', $value);
    }
}
