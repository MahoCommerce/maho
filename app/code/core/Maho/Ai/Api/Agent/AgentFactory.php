<?php

/**
 * Builds the admin assistant agent on the configured Maho AI provider.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Maho_Ai_Model_Platform_Symfony;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;

final class AgentFactory
{
    public function __construct(
        private readonly McpToolbox $toolbox,
    ) {}

    public function create(string $systemPrompt, ToolExecutorInterface $executor, ?int $storeId = null, ?int $maxToolCalls = null): Agent
    {
        $platformCode = (string) \Mage::getStoreConfig('ai/chat/platform', $storeId) ?: null;
        $provider = \Mage::getSingleton('ai/platform_factory')->create($platformCode, $storeId);
        if (!$provider instanceof Maho_Ai_Model_Platform_Symfony) {
            throw new \Mage_Core_Exception('The assistant needs a provider based on Symfony AI. Select one under System > Configuration > AI > Assistant.');
        }

        $model = (string) \Mage::getStoreConfig('ai/chat/model', $storeId) ?: $provider->getDefaultChatModel();
        if ($model === '') {
            throw new \Mage_Core_Exception('No chat model is configured for the assistant.');
        }

        return new Agent(
            platform: $provider->getPlatform(),
            model: $model,
            inputProcessors: [new SystemPromptInputProcessor($systemPrompt)],
            name: 'admin-assistant',
            toolbox: $this->toolbox,
            toolExecutor: $executor,
            maxToolCalls: $maxToolCalls ?? max(1, (int) \Mage::getStoreConfig('ai/chat/max_tool_calls', $storeId)),
        );
    }

    public function platformCode(?int $storeId = null): string
    {
        $platformCode = (string) \Mage::getStoreConfig('ai/chat/platform', $storeId);

        return $platformCode !== '' ? $platformCode : \Mage::getSingleton('ai/platform_factory')->getDefaultPlatform($storeId);
    }
}
