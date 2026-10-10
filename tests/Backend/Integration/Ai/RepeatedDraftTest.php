<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps only the answer when a provider streams a draft of it first', function (string $text, string $answer): void {
    expect(Maho_Ai_Model_Chat_RepeatedDraft::remove($text))->toBe($answer);
})->with([
    'a question asked twice' => [
        "Which website should I check: Maho Store, one specific website, or all websites? Customer registrations are scoped by website.\nWhich website should I check: Maho Store, a specific website, or all websites? Registrations are scoped by website.",
        'Which website should I check: Maho Store, a specific website, or all websites? Registrations are scoped by website.',
    ],
    'a statement made twice' => [
        "There are 0 new customers this week across all websites, from October 5 to October 8.\nThere are 0 new customers this week across all websites, from October 5 through October 8.",
        'There are 0 new customers this week across all websites, from October 5 through October 8.',
    ],
]);

it('keeps a real answer of two lines', function (string $text): void {
    expect(Maho_Ai_Model_Chat_RepeatedDraft::remove($text))->toBe($text);
})->with([
    'one line for each website' => "Maho Store had 3 new customers this week, from Monday to today.\nMaison Maho had 4 new customers this week, from Monday to today.",
    'the same number for each website' => "Maho Store had 3 new customers this week, from Monday to today.\nMaison Maho had 3 new customers this week, from Monday to today.",
    'a list' => "- Maho Store: 3\n- Maison Maho: 4",
    'short lines' => "I found 12 orders.\nAll of them are paid.",
    'one paragraph' => 'Which website should I check: Maho Store, a specific website, or all websites?',
]);
