<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Provider;
use Symfony\AI\Platform\Test\MockModelClient;
use Symfony\AI\Platform\Test\MockResultConverter;
use Symfony\Component\EventDispatcher\EventDispatcher;

uses(Tests\MahoBackendTestCase::class);

/**
 * Send one chat request through the provider and return the options that reach the bridge.
 *
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function aiSentChatOptions(string $platformCode, array $options, ?EventDispatcher $dispatcher = null): array
{
    $client = new MockModelClient('OK');
    $platform = new Platform([new Provider($platformCode, [$client], [new MockResultConverter()], new FallbackModelCatalog(), null, $dispatcher)]);
    new Maho_Ai_Model_Platform_Symfony($platform, $platformCode, 'pest-model')
        ->complete([['role' => 'user', 'content' => 'Hello']], $options);

    return $client->getCalls()[0]['options'];
}

it('sends the output token limit as the option that every bridge translates', function (string $platformCode): void {
    // Gemini and Anthropic reject an unknown field, so the provider-specific max_tokens must not go out.
    expect(aiSentChatOptions($platformCode, ['max_tokens' => 500]))->toBe([PlatformOptions::MAX_OUTPUT_TOKENS => 500]);
})->with([
    Maho_Ai_Model_Platform::OPENAI,
    Maho_Ai_Model_Platform::ANTHROPIC,
    Maho_Ai_Model_Platform::GOOGLE,
    Maho_Ai_Model_Platform::MISTRAL,
    Maho_Ai_Model_Platform::OPENROUTER,
    Maho_Ai_Model_Platform::GENERIC,
]);

it('gives an Anthropic request the output token limit of the caller, or 32000 when the caller gives none', function (array $options, int $limit): void {
    $sent = aiSentChatOptions(Maho_Ai_Model_Platform::ANTHROPIC, $options, Maho_Ai_Model_Platform_Symfony::anthropicEventDispatcher());

    // The Anthropic bridge sends an explicit max_tokens before the canonical option.
    expect($sent['max_tokens'] ?? $sent[PlatformOptions::MAX_OUTPUT_TOKENS] ?? null)->toBe($limit);
})->with([
    'a limit of the caller' => [['max_tokens' => 500], 500],
    'no limit' => [[], 32000],
]);
