<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\Ai\Api\Chat\Assistant;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

uses(Tests\MahoBackendTestCase::class);

afterEach(function (): void {
    foreach (Mage::app()->getStores(true) as $store) {
        foreach (['ai/general/anthropic_api_key', 'ai/general/anthropic_model', 'ai/general/default_platform', 'ai/chat/platform', 'ai/chat/model'] as $path) {
            $store->setConfig($path, null);
        }
    }
});

function aiModelStores(array $values): void
{
    foreach (Mage::app()->getStores(true) as $store) {
        foreach ($values as $path => $value) {
            $store->setConfig($path, $value);
        }
    }
}

it('registers the model of the assistant in the catalog of its provider', function (): void {
    aiModelStores([
        'ai/general/anthropic_api_key' => Mage::helper('core')->encrypt('sk-test'),
        'ai/general/anthropic_model' => 'claude-future-general',
        'ai/chat/platform' => 'anthropic',
        'ai/chat/model' => 'claude-future-assistant',
    ]);

    $catalog = Maho_Ai_Model_Platform_Symfony::createForAnthropic(null)->getPlatform()->getModelCatalog();

    expect($catalog->getModel('claude-future-general')->getName())->toBe('claude-future-general');
    expect($catalog->getModel('claude-future-assistant')->getName())->toBe('claude-future-assistant');
});

it('leaves the model of the assistant out of the catalog of another provider', function (): void {
    aiModelStores([
        'ai/general/anthropic_api_key' => Mage::helper('core')->encrypt('sk-test'),
        'ai/chat/platform' => 'openai',
        'ai/chat/model' => 'claude-future-assistant',
    ]);

    $catalog = Maho_Ai_Model_Platform_Symfony::createForAnthropic(null)->getPlatform()->getModelCatalog();

    expect(fn() => $catalog->getModel('claude-future-assistant'))->toThrow(Throwable::class);
});

it('counts the cached input tokens of Anthropic, and leaves the other providers as they report', function (): void {
    $usage = new TokenUsage(promptTokens: 24, completionTokens: 500, cachedTokens: 9000, cacheCreationTokens: 1000, cacheReadTokens: 8000);

    expect(Assistant::tokenUsage(['token_usage' => $usage], Maho_Ai_Model_Platform::ANTHROPIC))->toBe([9024, 500]);
    expect(Assistant::tokenUsage(['token_usage' => $usage], Maho_Ai_Model_Platform::OPENAI))->toBe([24, 500]);
    expect(Assistant::tokenUsage([], Maho_Ai_Model_Platform::ANTHROPIC))->toBe([0, 0]);
});
