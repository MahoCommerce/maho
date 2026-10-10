<?php

/**
 * Remove a draft that a provider streams before the final answer, in the same response.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * Some OpenAI-compatible proxies join the output messages of one model response with a line break,
 * so a short message before the answer and the answer itself arrive as one text. The two are then two
 * wordings of the same paragraph, with the same numbers. Lists, tables, short lines and lines with other
 * numbers never match, so a real answer stays, such as one line for each website.
 */
final class Maho_Ai_Model_Chat_RepeatedDraft
{
    private const MIN_CHARS = 60;

    private const MIN_SIMILARITY = 90.0;

    /** Two wordings of one question differ more, and an answer never asks the same question twice. */
    private const MIN_QUESTION_SIMILARITY = 75.0;

    /**
     * Return the second paragraph when the text is two long, almost equal paragraphs with the same numbers and one line break between them.
     */
    public static function remove(string $text): string
    {
        $parts = explode("\n", trim($text));
        if (count($parts) !== 2) {
            return $text;
        }
        [$draft, $answer] = array_map(trim(...), $parts);
        foreach ([$draft, $answer] as $part) {
            if (mb_strlen($part) < self::MIN_CHARS || preg_match('/^([-*+>#|]|\d+[.)])/u', $part)) {
                return $text;
            }
        }
        if (self::numbers($draft) !== self::numbers($answer)) {
            return $text;
        }
        similar_text(mb_strtolower($draft), mb_strtolower($answer), $percent);
        $questions = str_ends_with($draft, '?') && str_ends_with($answer, '?');

        return $percent >= ($questions ? self::MIN_QUESTION_SIMILARITY : self::MIN_SIMILARITY) ? $answer : $text;
    }

    /**
     * @return list<string>
     */
    private static function numbers(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)*/', $text, $matches);
        $numbers = $matches[0];
        sort($numbers);

        return $numbers;
    }
}
