<?php

/**
 * Keeps the drafts that the live preview of the content editor shows.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

/**
 * @phpstan-type Draft array{
 *     entity: string,
 *     entity_id: ?int,
 *     store_id: int,
 *     admin_user_id: int,
 *     admin_origin: string,
 *     context_page_id: ?int,
 *     data: array<string, mixed>,
 * }
 */
class Mage_Cms_Model_Preview
{
    public const CACHE_TAG = 'CMS_PREVIEW';

    /** A draft lives this long after the last change in the editor. */
    public const LIFETIME = 1800;

    public const XML_PATH_PROVIDERS = 'global/cms/preview/providers';

    /** The registry key of the draft that the current storefront request shows. */
    public const REGISTRY_KEY = 'cms_preview';

    /** The comments around the previewed content. The frame script measures the content between them. */
    public const MARKER_START = '<!--cms-preview-start-->';
    public const MARKER_END = '<!--cms-preview-end-->';

    /**
     * Store $draft and return its id. The id is random and is the only key to the draft.
     * An id that another admin user owns gets a new id, so a user never overwrites the
     * draft of another user.
     *
     * @param Draft $draft
     */
    public function saveDraft(array $draft, ?string $draftId = null): string
    {
        $existing = $draftId !== null ? $this->loadDraft($draftId) : null;
        if ($existing === null || $existing['admin_user_id'] !== $draft['admin_user_id']) {
            $draftId = bin2hex(random_bytes(16));
        }

        Mage::app()->saveCache(
            Mage::helper('core')->jsonEncode($draft),
            $this->cacheId($draftId),
            [self::CACHE_TAG],
            self::LIFETIME,
        );

        return $draftId;
    }

    /**
     * @return Draft|null
     */
    public function loadDraft(string $draftId): ?array
    {
        if (!self::isValidId($draftId)) {
            return null;
        }
        $json = Mage::app()->loadCache($this->cacheId($draftId));
        if (!is_string($json) || $json === '') {
            return null;
        }
        try {
            $draft = Mage::helper('core')->jsonDecode($json);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($draft) || !is_string($draft['entity'] ?? null) || !is_array($draft['data'] ?? null)) {
            return null;
        }

        return [
            'entity' => $draft['entity'],
            'entity_id' => isset($draft['entity_id']) ? (int) $draft['entity_id'] : null,
            'store_id' => (int) ($draft['store_id'] ?? 0),
            'admin_user_id' => (int) ($draft['admin_user_id'] ?? 0),
            'admin_origin' => (string) ($draft['admin_origin'] ?? ''),
            'context_page_id' => isset($draft['context_page_id']) ? (int) $draft['context_page_id'] : null,
            'data' => $draft['data'],
        ];
    }

    public static function isValidId(string $draftId): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $draftId) === 1;
    }

    /**
     * The provider that renders the drafts of $entity, as `global/cms/preview/providers` declares it.
     */
    public function getProvider(string $entity): ?Mage_Cms_Model_Preview_ProviderInterface
    {
        $node = Mage::getConfig()->getNode(self::XML_PATH_PROVIDERS . '/' . $entity . '/model');
        if (!$node || !preg_match('/^[a-z_]+$/', $entity)) {
            return null;
        }
        $provider = Mage::getSingleton((string) $node);

        return $provider instanceof Mage_Cms_Model_Preview_ProviderInterface ? $provider : null;
    }

    /**
     * The ACL resource that an admin user needs to preview $entity.
     */
    public function getAclResource(string $entity): ?string
    {
        $node = Mage::getConfig()->getNode(self::XML_PATH_PROVIDERS . '/' . $entity . '/acl');

        return $node ? (string) $node : null;
    }

    /**
     * The field of the edit form that holds the id of the record of $entity.
     */
    public function getIdField(string $entity): string
    {
        $node = Mage::getConfig()->getNode(self::XML_PATH_PROVIDERS . '/' . $entity . '/id_field');

        return $node ? (string) $node : 'id';
    }

    private function cacheId(string $draftId): string
    {
        return 'cms_preview_' . $draftId;
    }
}
