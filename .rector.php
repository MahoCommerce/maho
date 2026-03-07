<?php

declare(strict_types=1);

use Maho\Rector\SecureGetImageSizeRector;
use Maho\Rector\SecureUnserializeRector;
use Maho\Rector\VarienToMahoClassMap;
use Rector\CodeQuality\Rector as CodeQuality;
use Rector\CodingStyle\Rector as CodingStyle;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector as DeadCode;
use Rector\EarlyReturn\Rector as EarlyReturn;
use Rector\Renaming\Rector\Name\RenameClassRector;
use Rector\TypeDeclaration\Rector as TypeDeclaration;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/lib',
        __DIR__ . '/public',
    ])
    ->withPhpSets(
        php70: true,
    )
    ->withRules([
        SecureGetImageSizeRector::class,
        SecureUnserializeRector::class,
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
        CodeQuality\Ternary\SwitchNegatedTernaryRector::class,
        CodingStyle\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class,
        DeadCode\ClassMethod\RemoveUselessParamTagRector::class,
        DeadCode\ClassMethod\RemoveUselessReturnTagRector::class,
        DeadCode\MethodCall\RemoveNullArgOnNullDefaultParamRector::class,
        DeadCode\Property\RemoveUselessVarTagRector::class,
        EarlyReturn\If_\ChangeNestedIfsToEarlyReturnRector::class,
        EarlyReturn\If_\RemoveAlwaysElseRector::class,
        Rector\CodingStyle\Rector\FuncCall\ConsistentImplodeRector::class,
        Rector\Php71\Rector\List_\ListToArrayDestructRector::class,
        Rector\Php74\Rector\Assign\NullCoalescingOperatorRector::class,
        Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector::class,
        Rector\Php80\Rector\FuncCall\ClassOnObjectRector::class,
        Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector::class,
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        TypeDeclaration\ClassMethod\ReturnNeverTypeRector::class,
        TypeDeclaration\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
    ])
    ->withConfiguredRule(Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector::class, [
        'sensitive_parameters' => [
            'apiKey', 'email', 'useremail', 'username', 'password'
        ],
    ])
    // Varien_* to Maho\* namespace migration
    ->withConfiguredRule(RenameClassRector::class, VarienToMahoClassMap::getMap());
