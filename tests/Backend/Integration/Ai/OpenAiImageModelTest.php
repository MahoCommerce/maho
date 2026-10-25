<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\AI\Platform\Bridge\OpenAi\Image;
use Symfony\AI\Platform\Capability;

uses(Tests\MahoBackendTestCase::class);

/**
 * The configured OpenAI image model replaces the catalog entry with the same name.
 * It pointed at the DallE class, which the bridge no longer has, so image generation failed.
 */
it('resolves the configured OpenAI image model to the bridge image class', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('ai/general/openai_api_key', Mage::helper('core')->encrypt('sk-test'));
    $store->setConfig('ai/image/openai_model', 'gpt-image-1');

    $model = Maho_Ai_Model_Platform_Symfony::createForOpenAi(null)
        ->getPlatform()
        ->getModelCatalog()
        ->getModel('gpt-image-1');

    expect($model)->toBeInstanceOf(Image::class)
        ->and($model->supports(Capability::OUTPUT_IMAGE))->toBeTrue();
});
