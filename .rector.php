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
    ->withPhpSets()
    ->withSkip([
        // ReadOnlyClass / ReadOnlyProperty change the contract, not the code:
        // a readonly class cannot be extended by a normal child, and a readonly
        // property cannot be written from one. Maho classes exist to be extended.
        Rector\Php81\Rector\Property\ReadOnlyPropertyRector::class,
        Rector\Php82\Rector\Class_\ReadOnlyClassRector::class,
        // `const string FOO` is new syntax, and a child class cannot redeclare
        // the constant with another type.
        Rector\Php83\Rector\ClassConst\AddTypeToConstRector::class,
        // Judges "extra" from the core signature alone, but a third-party
        // subclass may well declare the parameter the caller is passing.
        Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector::class,
    ])
    ->withRules([
        SecureGetImageSizeRector::class,
        SecureUnserializeRector::class,
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\SimplifyTautologyTernaryRector::class,
        CodeQuality\Ternary\SwitchNegatedTernaryRector::class,
        CodingStyle\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector::class,
        DeadCode\ClassMethod\RemoveUselessParamTagRector::class,
        DeadCode\ClassMethod\RemoveUselessReturnTagRector::class,
        DeadCode\MethodCall\RemoveNullArgOnNullDefaultParamRector::class,
        DeadCode\Property\RemoveUselessVarTagRector::class,
        EarlyReturn\If_\ChangeNestedIfsToEarlyReturnRector::class,
        EarlyReturn\If_\RemoveAlwaysElseRector::class,
        TypeDeclaration\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
    ])
    // Promoting a Magento-lineage property renames the constructor parameter to
    // the underscore-prefixed property name, which breaks named arguments.
    // rename_property: false keeps promotion to the cases where both already agree.
    ->withConfiguredRule(Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class, [
        Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::RENAME_PROPERTY => false,
    ])
    ->withConfiguredRule(Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector::class, [
        'sensitive_parameters' => [
            'token', 'apiKey', 'email', 'useremail', 'username', 'password', 'newPassword',
        ],
    ])
    // Varien_* to Maho\* namespace migration
    ->withConfiguredRule(RenameClassRector::class, VarienToMahoClassMap::getMap());
