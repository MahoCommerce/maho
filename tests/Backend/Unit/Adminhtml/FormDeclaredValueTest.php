<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Source guard for a silent, recurring admin-form bug.
 *
 * Maho\Data\Form::setValues() clears every element the data array doesn't
 * mention, so a field declared as
 *
 *     $fieldset->addField('website_ids', 'hidden', ['value' => $websiteId]);
 *
 * loses that value the moment the block runs its usual
 * `$form->setValues($model->getData())` and the model has no 'website_ids' key -
 * which is exactly the case for a *new* record. The value never reaches the
 * browser, so it never comes back on POST. For a hidden field or a checkbox
 * nothing on screen hints at it: the admin sees a form that refuses to save
 * (customer segments in single-store mode rejected every new segment with
 * "select at least one website", with no field to fix) or a control that
 * silently does nothing (the API user "Regenerate Credentials" checkbox posted
 * an empty string, so `!empty($data[...])` was never true).
 *
 * The fix at each call site is to seed the same key on the model - `$model->
 * setWebsiteIds($websiteId)` next to the addField() - so setValues() restores
 * it instead of wiping it. That is what the core adminhtml blocks do
 * (Mage_Adminhtml_Block_Cms_Page_Edit_Tab_Main, ..._Promo_Quote_Edit_Tab_Main).
 *
 * The scan is deliberately narrow, to stay actionable rather than chatty:
 *   - only 'hidden' and 'checkbox' fields, where a lost value is invisible and
 *     the admin has no way to re-enter it;
 *   - only values that can't come back from the data itself (a literal empty
 *     value is a no-op; a value read off the very object whose getData() feeds
 *     setValues() comes back on its own);
 *   - anything already seeded, re-set after setValues(), or listed below.
 */

/**
 * Blocks that legitimately declare a value the scan can't prove safe.
 *
 * Keyed by "<path relative to app/code>:<element id>". Add an entry only with a
 * reason that says where the value comes back from at runtime.
 *
 * @return array<string, string>
 */
function declaredValueAllowlist(): array
{
    return [
        'core/Mage/Adminhtml/Block/System/Email/Template/Edit/Form.php:variables' =>
            'setValues() only runs for session form data, which is the POST - and the POST carries this hidden field back.',
        'core/Mage/Payment/Block/Adminhtml/Payment/Restriction/Edit/Form.php:type' =>
            'setValues() is skipped entirely while the registry model is empty (new restriction); once populated, type is a column of it.',
        'core/Maho/CustomerSegmentation/Block/Adminhtml/Segment/Sequence/Edit/Form.php:segment_id' =>
            'editSequenceAction() seeds segment_id on the sequence model before rendering, so getData() carries it.',
    ];
}

/**
 * Every "<file>:<id>" the scan flags, with the details needed for the message.
 *
 * @return array<int, array{file: string, id: string, type: string, line: int, expr: string}>
 */
function declaredValueFindings(): array
{
    $findings = [];
    foreach (declaredValueBlockFiles() as $file) {
        foreach (declaredValueScanFile($file) as $finding) {
            $findings[] = $finding;
        }
    }
    return $findings;
}

/**
 * @return array<int, string>
 */
function declaredValueBlockFiles(): array
{
    $files = [];
    $dir = new RecursiveDirectoryIterator(Mage::getBaseDir() . '/app/code');
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        if (!str_contains($file->getPathname(), '/Block/')) {
            continue;
        }
        $files[] = $file->getPathname();
    }
    sort($files);
    return $files;
}

/**
 * @return array<int, array{file: string, id: string, type: string, line: int, expr: string}>
 */
function declaredValueScanFile(string $file): array
{
    $source = (string) file_get_contents($file);
    if (!str_contains($source, 'addField') || !str_contains($source, 'setValues(')) {
        return [];
    }

    $relative = str_replace(Mage::getBaseDir() . '/app/code/', '', $file);
    $tokens = token_get_all($source);
    $declarations = [];
    $setValuesLines = [];

    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }
        if ($token[1] === 'setValues') {
            $setValuesLines[] = $token[2];
            continue;
        }
        if ($token[1] !== 'addField') {
            continue;
        }
        $call = declaredValueParseAddField($tokens, $index);
        if ($call === null) {
            continue;
        }
        $declarations[] = $call + ['file' => $relative];
    }

    if ($declarations === [] || $setValuesLines === []) {
        return [];
    }

    $sources = declaredValueSetValuesSources($source);
    $findings = [];
    foreach ($declarations as $declaration) {
        // A value declared after the last setValues() call is never touched by it.
        if (!array_filter($setValuesLines, fn(int $line): bool => $line > $declaration['line'])) {
            continue;
        }
        // Only fields whose value the admin can neither see nor retype.
        if (!in_array($declaration['type'], ['hidden', 'checkbox'], true)) {
            continue;
        }
        // Clearing an already-empty value is a no-op.
        if (in_array($declaration['expr'], ['', "''", '""', 'null'], true)) {
            continue;
        }
        // A value read off the object whose data feeds setValues() comes back on its own.
        foreach ($sources as $variable) {
            if (str_contains($declaration['expr'], '$' . $variable . '->')) {
                continue 2;
            }
        }
        if (declaredValueIsSeeded($source, $declaration['id'])) {
            continue;
        }
        $findings[] = $declaration;
    }

    return $findings;
}

/**
 * Parse `addField('<id>', '<type>', [...])` starting at the addField token.
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
 * @return array{id: string, type: string, line: int, expr: string}|null
 */
function declaredValueParseAddField(array $tokens, int $index): ?array
{
    $count = count($tokens);
    $line = is_array($tokens[$index]) ? $tokens[$index][2] : 0;

    $cursor = $index + 1;
    while ($cursor < $count && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], [T_WHITESPACE, T_COMMENT], true)) {
        $cursor++;
    }
    if (($tokens[$cursor] ?? null) !== '(') {
        return null;
    }

    // Split the call into top-level arguments.
    $arguments = [];
    $current = [];
    $depth = 0;
    for (; $cursor < $count; $cursor++) {
        $token = $tokens[$cursor];
        $text = is_array($token) ? $token[1] : $token;
        if (in_array($text, ['(', '[', '{'], true)) {
            $depth++;
            if ($depth === 1) {
                continue;
            }
        } elseif (in_array($text, [')', ']', '}'], true)) {
            $depth--;
            if ($depth === 0) {
                $arguments[] = $current;
                break;
            }
        }
        if ($depth === 1 && $text === ',') {
            $arguments[] = $current;
            $current = [];
            continue;
        }
        $current[] = $token;
    }
    if (count($arguments) < 3) {
        return null;
    }

    $id = declaredValueStringLiteral($arguments[0]);
    $type = declaredValueStringLiteral($arguments[1]);
    if ($id === null || $type === null) {
        return null;
    }

    $expr = declaredValueConfigValue($arguments[2]);
    if ($expr === null) {
        return null;
    }

    return ['id' => $id, 'type' => $type, 'line' => $line, 'expr' => $expr];
}

/**
 * @param array<int, array{0: int, 1: string, 2: int}|string> $argument
 */
function declaredValueStringLiteral(array $argument): ?string
{
    foreach ($argument as $token) {
        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            return trim($token[1], "'\"");
        }
        return null;
    }
    return null;
}

/**
 * Source text of the top-level 'value' entry of a field config array, if any.
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $argument
 */
function declaredValueConfigValue(array $argument): ?string
{
    $depth = 0;
    $collecting = false;
    $expr = '';

    for ($i = 0, $count = count($argument); $i < $count; $i++) {
        $token = $argument[$i];
        $text = is_array($token) ? $token[1] : $token;

        if (in_array($text, ['[', '(', '{'], true)) {
            $depth++;
            if ($collecting) {
                $expr .= $text;
            }
            continue;
        }
        if (in_array($text, [']', ')', '}'], true)) {
            $depth--;
            if ($collecting && $depth >= 1) {
                $expr .= $text;
                continue;
            }
            if ($collecting) {
                return trim($expr);
            }
            continue;
        }
        if ($collecting) {
            if ($depth === 1 && $text === ',') {
                return trim($expr);
            }
            $expr .= $text;
            continue;
        }
        if ($depth === 1 && is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && trim($token[1], "'\"") === 'value') {
            for ($j = $i + 1; $j < $count; $j++) {
                $next = $argument[$j];
                if (is_array($next) && $next[0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($next) && $next[0] === T_DOUBLE_ARROW) {
                    $collecting = true;
                    $i = $j;
                }
                break;
            }
        }
    }

    return $collecting ? trim($expr) : null;
}

/**
 * Variables whose data ends up in setValues(): `setValues($model->getData())`,
 * plus one hop through `$data = $model->getData(); ... setValues($data)`.
 *
 * @return array<int, string>
 */
function declaredValueSetValuesSources(string $source): array
{
    preg_match_all('/->setValues\(\s*\$(\w+)/', $source, $direct);
    $variables = $direct[1];

    foreach ($direct[1] as $variable) {
        preg_match_all('/\$' . preg_quote($variable, '/') . '\s*=\s*\$(\w+)->getData\(/', $source, $indirect);
        foreach ($indirect[1] as $origin) {
            $variables[] = $origin;
        }
    }

    return array_values(array_unique($variables));
}

/**
 * Whether the block puts the same key back where setValues() will find it, or
 * re-applies it to the element afterwards.
 */
function declaredValueIsSeeded(string $source, string $id): bool
{
    $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $id)));
    $quoted = preg_quote($id, '/');

    $patterns = [
        '/->set' . preg_quote($studly, '/') . '\s*\(/',        // $model->setWebsiteIds(...)
        "/->setData\(\s*'{$quoted}'/",                          // $model->setData('website_ids', ...)
        "/getElement\(\s*'{$quoted}'\s*\)/",                    // $form->getElement('website_ids')->setValue(...)
        "/\['{$quoted}'\]\s*=/",                                // $data['website_ids'] = ...
        "/'{$quoted}'\s*=>/",                                   // 'website_ids' => ... in a data array
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $source)) {
            return true;
        }
    }

    return false;
}

it('never lets Form::setValues() wipe a hidden or checkbox value the block declared', function () {
    $allowlist = declaredValueAllowlist();
    $offenders = [];

    foreach (declaredValueFindings() as $finding) {
        $key = $finding['file'] . ':' . $finding['id'];
        if (isset($allowlist[$key])) {
            continue;
        }
        $offenders[] = sprintf(
            "%s:%d\n    field '%s' (%s) declares value: %s\n    -> seed the same key on the model next to addField(), e.g. \$model->set%s(...), "
            . "or re-apply it after setValues() via \$form->getElement('%s')->setValue(...).",
            $finding['file'],
            $finding['line'],
            $finding['id'],
            $finding['type'],
            $finding['expr'],
            str_replace(' ', '', ucwords(str_replace('_', ' ', $finding['id']))),
            $finding['id'],
        );
    }

    expect($offenders)->toBe([], "Form::setValues() will clear these declared values before the form renders:\n\n"
        . implode("\n\n", $offenders)
        . "\n\nIf the value does come back at runtime, add the case to declaredValueAllowlist() with the reason.\n");
});

it('recognises a declared value that setValues() would wipe', function () {
    // The analyzer itself, exercised on the shape it exists to catch.
    $file = sys_get_temp_dir() . '/maho_declared_value_' . getmypid() . '.php';
    file_put_contents($file, <<<'PHP'
        <?php
        class Some_Block
        {
            protected function _prepareForm()
            {
                $fieldset->addField('website_ids', 'hidden', [
                    'name'  => 'website_ids',
                    'value' => Mage::app()->getStore(true)->getWebsiteId(),
                ]);
                $fieldset->addField('is_active', 'select', [
                    'name'   => 'is_active',
                    'values' => [['value' => 1, 'label' => 'Active']],
                ]);
                $form->setValues($model->getData());
            }
        }
        PHP);

    try {
        $findings = declaredValueScanFile($file);
    } finally {
        unlink($file);
    }

    expect($findings)->toHaveCount(1);
    expect($findings[0]['id'])->toBe('website_ids');
    expect($findings[0]['type'])->toBe('hidden');
});
