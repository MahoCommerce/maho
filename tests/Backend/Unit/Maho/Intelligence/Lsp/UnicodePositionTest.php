<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

function lspUnicodeFixture(string $annotation, int $annotationUnits, string $language, bool $multiline): array
{
    $comment = $language === 'php' ? "/* {$annotation} */ " : "<!-- {$annotation} -->";
    $call = $language === 'php' ? "Mage::getModel('" : '<block type="';
    $end = $language === 'php' ? "');" : '" />';
    $start = strlen($language === 'php' ? '/*  */ ' : '<!--  -->') + $annotationUnits + strlen($call);
    $header = $language === 'php' ? ($multiline ? "<?php\n" : '<?php ') : ($multiline ? "<?xml version=\"1.0\"?>\n" : '');
    if ($language === 'php' && !$multiline) {
        $start += 6;
    }

    return [
        'uri' => 'file:///owned/fixture.' . $language,
        'text' => $header . $comment . $call . 'catalog/pr' . $end,
        'completed' => $header . $comment . $call . 'catalog/product' . $end,
        'line' => $multiline ? 1 : 0,
        'start' => $start,
    ];
}

dataset('lsp annotations', [
    'ASCII' => ['plain', 5],
    'accent' => ['café', 4],
    'CJK' => ['商品', 2],
    'non-BMP' => ['😀', 2],
]);

dataset('lsp languages', ['PHP' => ['php'], 'XML' => ['xml']]);
dataset('lsp lines', ['first' => [false], 'second' => [true]]);

it('defaults to UTF-16 positions in the initialize response', function (): void {
    $method = new ReflectionMethod(Maho_Intelligence_Model_Lsp_Server::class, 'handleInitialize');
    $response = $method->invoke(new Maho_Intelligence_Model_Lsp_Server());

    expect($response['capabilities']['positionEncoding'] ?? 'utf-16')->toBe('utf-16');
});

it('completes and replaces an alias at a UTF-16 position', function (string $annotation, int $units, string $language, bool $multiline): void {
    $fixture = lspUnicodeFixture($annotation, $units, $language, $multiline);
    $documents = new Maho_Intelligence_Model_Lsp_DocumentStore();
    $documents->open($fixture['uri'], $fixture['text']);
    $registry = $this->createMock(Maho_Intelligence_Model_Registry::class);
    $registry->method('get')->willReturn(['catalog/product' => ['class' => 'Owned_Product']]);
    $handler = new Maho_Intelligence_Model_Lsp_Handler_Completion($registry, new Maho_Intelligence_Model_Lsp_ContextDetector(), $documents);

    $result = $handler->handle([
        'textDocument' => ['uri' => $fixture['uri']],
        'position' => ['line' => $fixture['line'], 'character' => $fixture['start'] + 10],
    ]);

    expect($result['items'])->toHaveCount(1);
    $edit = $result['items'][0]['textEdit'];
    expect($edit)->toBe([
        'range' => [
            'start' => ['line' => $fixture['line'], 'character' => $fixture['start']],
            'end' => ['line' => $fixture['line'], 'character' => $fixture['start'] + 10],
        ],
        'newText' => 'catalog/product',
    ]);
    $lines = explode("\n", $fixture['text']);
    $encoded = mb_convert_encoding($lines[$fixture['line']], 'UTF-16LE', 'UTF-8');
    $lines[$fixture['line']] = mb_convert_encoding(
        substr($encoded, 0, $edit['range']['start']['character'] * 2)
            . mb_convert_encoding($edit['newText'], 'UTF-16LE', 'UTF-8')
            . substr($encoded, $edit['range']['end']['character'] * 2),
        'UTF-8',
        'UTF-16LE',
    );
    expect(implode("\n", $lines))->toBe($fixture['completed']);
})->with('lsp annotations')->with('lsp languages')->with('lsp lines');

it('finds hover and definition at the beginning of an alias after Unicode', function (string $annotation, int $units, string $language, bool $multiline): void {
    $fixture = lspUnicodeFixture($annotation, $units, $language, $multiline);
    $documents = new Maho_Intelligence_Model_Lsp_DocumentStore();
    $documents->open($fixture['uri'], $fixture['text']);
    $registry = $this->createMock(Maho_Intelligence_Model_Registry::class);
    $registry->method('get')->willReturn(['class' => 'Owned_Product', 'file' => BP . '/owned.php', 'rewritten_by' => null]);
    $detector = new Maho_Intelligence_Model_Lsp_ContextDetector();
    $params = [
        'textDocument' => ['uri' => $fixture['uri']],
        'position' => ['line' => $fixture['line'], 'character' => $fixture['start']],
    ];

    $hover = new Maho_Intelligence_Model_Lsp_Handler_Hover($registry, $detector, $documents)->handle($params);
    $definition = new Maho_Intelligence_Model_Lsp_Handler_Definition($registry, $detector, $documents)->handle($params);

    expect($hover['contents']['value'] ?? '')->toContain('catalog/pr');
    expect($definition['uri'] ?? null)->toBe('file://' . BP . '/owned.php');
})->with('lsp annotations')->with('lsp languages')->with('lsp lines');

it('reports an unresolved alias with a UTF-16 diagnostic range', function (string $annotation, int $units, string $language, bool $multiline): void {
    $fixture = lspUnicodeFixture($annotation, $units, $language, $multiline);
    $registry = $this->createMock(Maho_Intelligence_Model_Registry::class);
    $registry->method('get')->willReturn(['class' => null, 'file' => null]);
    $diagnostics = new Maho_Intelligence_Model_Lsp_Handler_Diagnostic($registry)->diagnose($fixture['uri'], $fixture['text']);

    expect($diagnostics)->toHaveCount(1);
    expect($diagnostics[0]['range'])->toBe([
        'start' => ['line' => $fixture['line'], 'character' => $fixture['start']],
        'end' => ['line' => $fixture['line'], 'character' => $fixture['start'] + 10],
    ]);
})->with('lsp annotations')->with('lsp languages')->with('lsp lines');
