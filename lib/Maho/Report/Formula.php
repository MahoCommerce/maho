<?php

/**
 * Parse and evaluate a metric formula: metric codes, numbers, + - * / and parentheses.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

final class Formula
{
    /** @param array<int|string, mixed> $ast */
    private function __construct(private readonly array $ast) {}

    /** @throws \InvalidArgumentException when the formula has a syntax error */
    public static function parse(string $formula): self
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($formula);
        while ($offset < $length) {
            if (preg_match('/\s*(?:(\d+(?:\.\d+)?)|([a-z_][a-z0-9_]*)|([-+*\/()]))\s*/Ai', $formula, $m, 0, $offset) !== 1) {
                throw new \InvalidArgumentException("Unexpected character at position {$offset} in formula: {$formula}");
            }
            $offset += strlen($m[0]);
            if ($m[1] !== '') {
                $tokens[] = ['num', (float) $m[1]];
            } elseif (isset($m[2]) && $m[2] !== '') {
                $tokens[] = ['ref', $m[2]];
            } else {
                $tokens[] = ['sym', $m[3]];
            }
        }

        $position = 0;
        $ast = self::parseSum($tokens, $position, $formula);
        if ($position !== count($tokens)) {
            throw new \InvalidArgumentException("Unexpected token in formula: {$formula}");
        }
        return new self($ast);
    }

    /** @return list<string> the metric codes that the formula reads */
    public function references(): array
    {
        $references = [];
        $walk = static function (array $node) use (&$walk, &$references): void {
            if ($node[0] === 'ref') {
                $references[$node[1]] = $node[1];
            } elseif ($node[0] === 'neg') {
                $walk($node[1]);
            } elseif ($node[0] === 'op') {
                $walk($node[2]);
                $walk($node[3]);
            }
        };
        $walk($this->ast);
        return array_values($references);
    }

    /** Whether the formula only adds and subtracts metrics, so that its value over two groups is the sum of the two. */
    public function isLinear(): bool
    {
        $linear = static function (array $node) use (&$linear): bool {
            return match ($node[0]) {
                'ref' => true,
                'neg' => $linear($node[1]),
                'op' => in_array($node[1], ['+', '-'], true) && $linear($node[2]) && $linear($node[3]),
                default => false,
            };
        };
        return $linear($this->ast);
    }

    /**
     * Calculate the formula. A division by zero, or a null operand, gives null.
     *
     * @param \Closure(string): ?float $value the value of a metric code
     */
    public function evaluate(\Closure $value): ?float
    {
        $eval = static function (array $node) use (&$eval, $value): ?float {
            if ($node[0] === 'num') {
                return $node[1];
            }
            if ($node[0] === 'ref') {
                return $value($node[1]);
            }
            if ($node[0] === 'neg') {
                $inner = $eval($node[1]);
                return $inner === null ? null : -$inner;
            }
            $left = $eval($node[2]);
            $right = $eval($node[3]);
            if ($left === null || $right === null) {
                return null;
            }
            return match ($node[1]) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0 ? null : $left / $right,
                default => throw new \LogicException('Unknown operator in formula'),
            };
        };
        return $eval($this->ast);
    }

    /**
     * Render the formula as SQL. A division multiplies by 1.0 first, so that two counts divide as decimals on every
     * engine, and divides by NULLIF(..., 0), so that a division by zero gives NULL.
     *
     * @param \Closure(string): string $sql the SQL of a metric code
     */
    public function toSql(\Closure $sql): string
    {
        $render = static function (array $node) use (&$render, $sql): string {
            return match ($node[0]) {
                'num' => rtrim(rtrim(number_format($node[1], 6, '.', ''), '0'), '.'),
                'ref' => '(' . $sql($node[1]) . ')',
                'neg' => '(-' . $render($node[1]) . ')',
                'op' => $node[1] === '/'
                    ? '(' . $render($node[2]) . ' * 1.0 / NULLIF(' . $render($node[3]) . ', 0))'
                    : '(' . $render($node[2]) . ' ' . $node[1] . ' ' . $render($node[3]) . ')',
                default => throw new \LogicException('Unknown node in formula'),
            };
        };
        return $render($this->ast);
    }

    /**
     * @param list<array{0: string, 1: string|float}> $tokens
     * @return array<int|string, mixed>
     */
    private static function parseSum(array $tokens, int &$position, string $formula): array
    {
        $node = self::parseProduct($tokens, $position, $formula);
        while (isset($tokens[$position]) && $tokens[$position][0] === 'sym' && in_array($tokens[$position][1], ['+', '-'], true)) {
            $operator = $tokens[$position++][1];
            $node = ['op', $operator, $node, self::parseProduct($tokens, $position, $formula)];
        }
        return $node;
    }

    /**
     * @param list<array{0: string, 1: string|float}> $tokens
     * @return array<int|string, mixed>
     */
    private static function parseProduct(array $tokens, int &$position, string $formula): array
    {
        $node = self::parseUnary($tokens, $position, $formula);
        while (isset($tokens[$position]) && $tokens[$position][0] === 'sym' && in_array($tokens[$position][1], ['*', '/'], true)) {
            $operator = $tokens[$position++][1];
            $node = ['op', $operator, $node, self::parseUnary($tokens, $position, $formula)];
        }
        return $node;
    }

    /**
     * @param list<array{0: string, 1: string|float}> $tokens
     * @return array<int|string, mixed>
     */
    private static function parseUnary(array $tokens, int &$position, string $formula): array
    {
        $token = $tokens[$position] ?? throw new \InvalidArgumentException("Unexpected end of formula: {$formula}");
        $position++;
        if ($token[0] === 'num' || $token[0] === 'ref') {
            return $token;
        }
        if ($token[1] === '-') {
            return ['neg', self::parseUnary($tokens, $position, $formula)];
        }
        if ($token[1] === '(') {
            $node = self::parseSum($tokens, $position, $formula);
            if (($tokens[$position] ?? null) !== ['sym', ')']) {
                throw new \InvalidArgumentException("Missing closing parenthesis in formula: {$formula}");
            }
            $position++;
            return $node;
        }
        throw new \InvalidArgumentException("Unexpected token {$token[1]} in formula: {$formula}");
    }
}
