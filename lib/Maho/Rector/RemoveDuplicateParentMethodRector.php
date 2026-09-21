<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\PhpParser\Parser\RectorParser;
use Rector\PHPStan\ScopeFetcher;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Remove a method that repeats the parent method, signature and body included.
 *
 * The rule compares the printed body of the child method with the printed body of
 * the parent method. It skips every case where the two bodies can print the same
 * text but run different code:
 *  - a body that contains self:: or parent::, because both keywords bind to the
 *    class that declares the method
 *  - a body that contains a magic constant, for example __CLASS__ or __FILE__
 *  - a docblock on the child that refines the parent contract
 *  - a signature that differs in visibility, in static, in final, in by-reference
 *    return, in parameters or in return type
 *
 * Known limit: the comparison uses the printed source, so two identical bodies in
 * two namespaces with different use statements can compare as equal. Read the diff
 * before you accept it.
 */
final class RemoveDuplicateParentMethodRector extends AbstractRector
{
    /**
     * A docblock tag in this list refines the parent contract.
     * @var string[]
     */
    private const REFINING_TAGS = [
        '@param', '@phpstan-param', '@psalm-param',
        '@return', '@phpstan-return', '@psalm-return',
        '@throws', '@deprecated', '@var', '@template',
    ];

    /** @var array<string, ClassMethod|null> */
    private array $parentClassMethods = [];

    /** @var array<string, Node\Stmt[]> */
    private array $parsedFiles = [];

    public function __construct(
        private readonly RectorParser $rectorParser,
        private readonly BetterNodeFinder $betterNodeFinder,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove a class method that repeats the parent method with the same signature and the same body',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ParentClass
{
    public function getLabel(): string
    {
        return 'label';
    }
}

class ChildClass extends ParentClass
{
    public function getLabel(): string
    {
        return 'label';
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ParentClass
{
    public function getLabel(): string
    {
        return 'label';
    }
}

class ChildClass extends ParentClass
{
}
CODE_SAMPLE,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    #[\Override]
    public function refactor(Node $node): ?int
    {
        if ($node->stmts === null || $node->isAbstract()) {
            return null;
        }

        $parentMethodReflection = $this->matchParentMethodReflection($node);
        if (!$parentMethodReflection instanceof ExtendedMethodReflection) {
            return null;
        }

        $parentClassMethod = $this->resolveParentClassMethod($parentMethodReflection);
        if (!$parentClassMethod instanceof ClassMethod) {
            return null;
        }

        if (!$this->isSameSignature($node, $parentClassMethod)) {
            return null;
        }

        if (!$this->hasOnlyOverrideAttribute($node) || !$this->hasOnlyOverrideAttribute($parentClassMethod)) {
            return null;
        }

        if ($this->isContextBound($node) || $this->isContextBound($parentClassMethod)) {
            return null;
        }

        if (!$this->hasSameContract($node, $parentClassMethod)) {
            return null;
        }

        if (!$this->nodeComparator->areNodesEqual($node->stmts, $parentClassMethod->stmts)) {
            return null;
        }

        return NodeVisitor::REMOVE_NODE;
    }

    private function matchParentMethodReflection(ClassMethod $classMethod): ?ExtendedMethodReflection
    {
        $scope = ScopeFetcher::fetch($classMethod);

        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection || !$classReflection->isClass()) {
            return null;
        }

        $parentClassReflection = $classReflection->getParentClass();
        if (!$parentClassReflection instanceof ClassReflection) {
            return null;
        }

        $methodName = $classMethod->name->toString();
        if (!$parentClassReflection->hasNativeMethod($methodName)) {
            return null;
        }

        $extendedMethodReflection = $parentClassReflection->getNativeMethod($methodName);

        // a private parent method is not visible in the child scope
        if ($extendedMethodReflection->isPrivate()) {
            return null;
        }

        return $extendedMethodReflection;
    }

    private function resolveParentClassMethod(ExtendedMethodReflection $extendedMethodReflection): ?ClassMethod
    {
        $declaringClassReflection = $extendedMethodReflection->getDeclaringClass();
        $className = $declaringClassReflection->getName();
        $methodName = $extendedMethodReflection->getName();

        $cacheKey = $className . '::' . $methodName;
        if (array_key_exists($cacheKey, $this->parentClassMethods)) {
            return $this->parentClassMethods[$cacheKey];
        }

        $this->parentClassMethods[$cacheKey] = null;

        // an internal PHP class has no file
        $fileName = $declaringClassReflection->getFileName();
        if ($fileName === null) {
            return null;
        }

        $this->parsedFiles[$fileName] ??= $this->rectorParser->parseFile($fileName);

        $shortName = str_contains($className, '\\')
            ? substr($className, strrpos($className, '\\') + 1)
            : $className;

        foreach ($this->betterNodeFinder->findInstanceOf($this->parsedFiles[$fileName], ClassLike::class) as $classLike) {
            if (!$classLike->name instanceof Identifier) {
                continue;
            }

            if ($classLike->name->toString() !== $shortName) {
                continue;
            }

            // a method that comes from a trait is not in this node, and stays null
            $this->parentClassMethods[$cacheKey] = $classLike->getMethod($methodName);
            break;
        }

        return $this->parentClassMethods[$cacheKey];
    }

    private function isSameSignature(ClassMethod $classMethod, ClassMethod $parentClassMethod): bool
    {
        if ($classMethod->flags !== $parentClassMethod->flags) {
            // an implicit public method has no flag, an explicit one has the public flag
            if (!$this->hasSameModifiers($classMethod, $parentClassMethod)) {
                return false;
            }
        }

        if ($classMethod->byRef !== $parentClassMethod->byRef) {
            return false;
        }

        $params = $classMethod->getParams();
        $parentParams = $parentClassMethod->getParams();
        if (count($params) !== count($parentParams)) {
            return false;
        }

        foreach ($params as $position => $param) {
            if (!$this->isSameParam($param, $parentParams[$position])) {
                return false;
            }
        }

        if (($classMethod->returnType instanceof Node) !== ($parentClassMethod->returnType instanceof Node)) {
            return false;
        }

        if (!$classMethod->returnType instanceof Node) {
            return true;
        }

        return $this->nodeComparator->areNodesEqual($classMethod->returnType, $parentClassMethod->returnType);
    }

    private function hasSameModifiers(ClassMethod $classMethod, ClassMethod $parentClassMethod): bool
    {
        return $classMethod->isPublic() === $parentClassMethod->isPublic()
            && $classMethod->isProtected() === $parentClassMethod->isProtected()
            && $classMethod->isPrivate() === $parentClassMethod->isPrivate()
            && $classMethod->isStatic() === $parentClassMethod->isStatic()
            && $classMethod->isFinal() === $parentClassMethod->isFinal()
            && $classMethod->isAbstract() === $parentClassMethod->isAbstract();
    }

    private function isSameParam(Param $param, Param $parentParam): bool
    {
        return $this->nodeComparator->areNodesEqual($param, $parentParam);
    }

    /**
     * self:: and parent:: bind to the class that declares the method, and a magic
     * constant reports that class or that file. Such a body is not movable.
     */
    private function isContextBound(ClassMethod $classMethod): bool
    {
        $foundNode = $this->betterNodeFinder->findFirst($classMethod, static function (Node $node): bool {
            if ($node instanceof MagicConst) {
                return true;
            }

            if (!$node instanceof Name) {
                return false;
            }

            $name = $node->toLowerString();

            return $name === 'self' || $name === 'parent';
        });

        return $foundNode instanceof Node;
    }

    /**
     * The child must not refine the parent contract with its docblock.
     */
    private function hasSameContract(ClassMethod $classMethod, ClassMethod $parentClassMethod): bool
    {
        $docComment = $classMethod->getDocComment();
        if ($docComment === null) {
            return true;
        }

        $docText = $docComment->getText();
        $hasRefiningTag = array_any(self::REFINING_TAGS, fn($refiningTag) => preg_match('#' . preg_quote($refiningTag, '#') . '\b#', $docText) === 1);

        if (!$hasRefiningTag) {
            return true;
        }

        $parentDocComment = $parentClassMethod->getDocComment();
        if ($parentDocComment === null) {
            return false;
        }

        return $this->normalizeDocText($docText) === $this->normalizeDocText($parentDocComment->getText());
    }

    private function normalizeDocText(string $docText): string
    {
        $docText = str_replace('*', ' ', $docText);

        return trim((string) preg_replace('#\s+#', ' ', $docText));
    }

    private function hasOnlyOverrideAttribute(ClassMethod $classMethod): bool
    {
        foreach ($classMethod->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if (!$this->isName($attribute->name, 'Override')) {
                    return false;
                }
            }
        }

        return true;
    }
}
