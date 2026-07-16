<?php

/**
 * SPDX-FileCopyrightText: 2026
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\ComposerPlugin\ApiPermissionCompiler;

/**
 * Derives an API resource permission id from its short name.
 *
 * The permission id is baked into vendor/composer/maho_api_permissions.php by
 * ApiPermissionCompiler in the maho-composer-plugin, which is the single source
 * of truth: the scaffolder must emit security expressions against the same id
 * the compiler registers, and the lister must resolve discovered resources back
 * to the same registry keys. We therefore delegate to the compiler's own
 * derivation rather than re-implementing the rule.
 */
trait ApiResourceNaming
{
    /**
     * CamelCase short name → kebab-cased plural permission id ('CmsPage' → 'cms-pages').
     */
    private function deriveApiResourceId(string $shortName): string
    {
        if ($shortName === '') {
            return '';
        }

        return ApiPermissionCompiler::deriveIdFromShortName($shortName);
    }
}
