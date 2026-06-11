<?php

/**
 * SPDX-FileCopyrightText: 2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_Purifier extends Mage_Core_Helper_Abstract
{
    public const CACHE_DEFINITION = 'Cache.DefinitionImpl';

    protected ?HTMLPurifier $purifier;

    /**
     * Purifier Constructor Call
     */
    public function __construct(
        ?HTMLPurifier $purifier = null,
    ) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set(self::CACHE_DEFINITION, null);
        $this->purifier = $purifier ?? new HTMLPurifier($config);
    }

    /**
     * Purify Html Content
     *
     * @param array|string $content
     * @return array|string
     */
    public function purify($content)
    {
        return is_array($content) ? $this->purifier->purifyArray($content) : $this->purifier->purify($content);
    }
}
