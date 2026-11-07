<?php

/**
 * Print a conversation of the admin assistant as plain text: the messages, the tool calls and the tokens.
 * Usage, from the root of a Maho checkout: php <skill>/transcript.php <conversation_id>
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

define('MAHO_ROOT_DIR', getcwd());
define('MAHO_PUBLIC_DIR', getcwd() . '/public');
require getcwd() . '/vendor/autoload.php';

Mage::app('admin');

/** @var Maho_Ai_Model_Conversation $conversation */
$conversation = Mage::getModel('ai/conversation')->load((int) ($argv[1] ?? 0));
if (!$conversation->getId()) {
    fwrite(STDERR, "No conversation with this id.\n");
    exit(1);
}

$clip = static fn(?string $text, int $max): string => mb_strlen((string) $text) > $max ? mb_substr((string) $text, 0, $max) . ' [...]' : (string) $text;
$tokensIn = 0;
$tokensOut = 0;

printf("Conversation %d, %s / %s\n\n", $conversation->getId(), $conversation->getData('platform'), $conversation->getData('model'));
foreach ($conversation->messagesCollection() as $message) {
    $tokensIn += (int) $message->getData('input_tokens');
    $tokensOut += (int) $message->getData('output_tokens');
    $role = (string) $message->getData('role');
    if ($role === 'tool') {
        printf(
            "TOOL %s(%s) -> %s%s\n  %s\n\n",
            $message->getData('tool_name'),
            $clip($message->getData('tool_arguments'), 300),
            $message->getData('tool_status') ?: 'done',
            $message->getData('is_write') ? ' [write]' : '',
            str_replace("\n", "\n  ", $clip($message->getData('content'), 600)),
        );
        continue;
    }
    $content = trim((string) $message->getData('content'));
    if ($content !== '') {
        printf("%s:\n%s\n\n", strtoupper($role), $content);
    }
}
printf("Tokens: %d in, %d out\n", $tokensIn, $tokensOut);
