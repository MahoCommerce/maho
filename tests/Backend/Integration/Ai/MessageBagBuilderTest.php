<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
|--------------------------------------------------------------------------
| Message bag builder
|--------------------------------------------------------------------------
|
| Stored rows become the Symfony AI message bag of the next request. A tool round
| is one assistant message with tool calls followed by one tool message per call.
|
*/

function aiMessage(array $data): Maho_Ai_Model_Conversation_Message
{
    /** @var Maho_Ai_Model_Conversation_Message $message */
    $message = Mage::getModel('ai/conversation_message');
    $message->setData($data);

    return $message;
}

function aiToolRound(string $id, string $name, string $status, ?string $content): array
{
    return [
        aiMessage([
            'role' => 'assistant',
            'content' => 'Let me check.',
            'tool_calls' => Mage::helper('core')->jsonEncode([['id' => $id, 'name' => $name, 'arguments' => ['id' => 5]]]),
        ]),
        aiMessage([
            'role' => 'tool',
            'tool_call_id' => $id,
            'tool_name' => $name,
            'tool_arguments' => Mage::helper('core')->jsonEncode(['id' => 5]),
            'tool_status' => $status,
            'content' => $content,
        ]),
    ];
}

it('maps user, assistant and tool rows to the matching message types', function (): void {
    $rows = [
        aiMessage(['role' => 'user', 'content' => 'How many products?']),
        ...aiToolRound('call_1', 'catalog_products_list', 'done', '{"total":3}'),
        aiMessage(['role' => 'assistant', 'content' => 'There are 3 products.']),
    ];

    $messages = new Maho_Ai_Model_Chat_MessageBagBuilder()->fromMessages($rows)->getMessages();

    expect($messages)->toHaveCount(4);
    expect($messages[0])->toBeInstanceOf(UserMessage::class);
    expect($messages[1])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[1]->getToolCalls())->toHaveCount(1);
    expect($messages[1]->getToolCalls()[0]->getName())->toBe('catalog_products_list');
    expect($messages[1]->getToolCalls()[0]->getArguments())->toBe(['id' => 5]);
    expect($messages[2])->toBeInstanceOf(ToolCallMessage::class);
    expect($messages[2]->getToolCall()->getId())->toBe('call_1');
    expect($messages[2]->asText())->toBe('{"total":3}');
    expect($messages[3])->toBeInstanceOf(AssistantMessage::class);
    expect($messages[3]->asText())->toBe('There are 3 products.');
});

it('tells the model when the administrator declined or superseded a write', function (): void {
    $builder = new Maho_Ai_Model_Chat_MessageBagBuilder();

    $denied = $builder->fromMessages(aiToolRound('call_2', 'catalog_products_update', 'denied', null))->getMessages();
    expect($denied[1]->asText())->toContain('declined');

    $cancelled = $builder->fromMessages(aiToolRound('call_3', 'catalog_products_delete', 'cancelled', null))->getMessages();
    expect($cancelled[1]->asText())->toContain('not performed');

    $pending = $builder->fromMessages(aiToolRound('call_4', 'catalog_products_delete', 'pending', null))->getMessages();
    expect($pending[1]->asText())->toContain('not performed');
});

it('never cuts the history window inside a tool round', function (): void {
    $rows = [
        aiMessage(['role' => 'user', 'content' => 'first']),
        ...aiToolRound('call_1', 'catalog_products_list', 'done', '{}'),
        aiMessage(['role' => 'assistant', 'content' => 'answer one']),
        aiMessage(['role' => 'user', 'content' => 'second']),
    ];

    $window = new Maho_Ai_Model_Chat_MessageBagBuilder()->window($rows, 3);

    // The window of 3 would start at the tool row; it starts after the round instead.
    expect(array_map(static fn($m) => $m->getRole(), $window))->toBe(['assistant', 'user']);
    expect(new Maho_Ai_Model_Chat_MessageBagBuilder()->window($rows, 10))->toHaveCount(5);
});

it('keeps the whole current turn in the window, even when its tool rounds pass the limit', function (): void {
    $rows = [aiMessage(['role' => 'user', 'content' => 'old question']), aiMessage(['role' => 'assistant', 'content' => 'old answer'])];
    $rows[] = aiMessage(['role' => 'user', 'content' => 'List the pending reviews']);
    for ($i = 1; $i <= 5; $i++) {
        array_push($rows, ...aiToolRound('call_' . $i, 'catalog_reviews_list', 'done', '{}'));
    }

    $window = new Maho_Ai_Model_Chat_MessageBagBuilder()->window($rows, 4);

    expect($window)->toHaveCount(11);
    expect($window[0]->getContent())->toBe('List the pending reviews');
});
