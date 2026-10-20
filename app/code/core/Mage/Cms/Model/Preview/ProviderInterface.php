<?php

/**
 * Renders the live preview of one type of content.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

/**
 * A module declares its provider under `global/cms/preview/providers/<entity>`, with the
 * model alias in `<model>` and the ACL resource of the editor in `<acl>`.
 *
 * @phpstan-import-type Draft from Mage_Cms_Model_Preview
 */
interface Mage_Cms_Model_Preview_ProviderInterface
{
    /**
     * Turn the posted edit form into the data of a draft. Sanitize it as the save path
     * does, so the preview shows what a save stores. A field that the save would refuse
     * is left out, with a message in `warnings`.
     *
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>, warnings: list<string>}
     */
    public function prepare(array $post, int $storeId): array;

    /**
     * Render the storefront page of $draft through $action. Return false when the page
     * cannot be shown.
     *
     * @param Draft $draft
     */
    public function render(Mage_Core_Controller_Front_Action $action, array $draft): bool;

    /**
     * Tell if $block renders the content of $draft. The preview marks the output of that
     * block, so the editor can keep the two panes on the same passage.
     *
     * @param Draft $draft
     */
    public function isTarget(Mage_Core_Block_Abstract $block, array $draft): bool;

    /**
     * The HTML that the preview shows for the target block, from the HTML it rendered.
     *
     * @param Draft $draft
     */
    public function getTargetHtml(string $html, array $draft): string;
}
