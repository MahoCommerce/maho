<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector as CodeQuality;
use Rector\DeadCode\Rector as DeadCode;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector as TypeDeclaration;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/lib',
        __DIR__ . '/public',
    ])
    ->withRules([
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\Foreach_\UnusedForeachValueToArrayKeysRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\Ternary\SimplifyTautologyTernaryRector::class,
        DeadCode\ClassMethod\RemoveUselessParamTagRector::class,
        DeadCode\ClassMethod\RemoveUselessReturnTagRector::class,
        DeadCode\Property\RemoveUselessVarTagRector::class,
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        TypeDeclaration\ClassMethod\ReturnNeverTypeRector::class
    ])
    ->withConfiguredRule(Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector::class, [
        'sensitive_parameters' => [
            'password'
        ],
    ]);