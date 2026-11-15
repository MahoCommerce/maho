<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Reflection\ClassReflection;
use Rector\PHPStan\ScopeFetcher;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Add the void or the static return type to a method that has no parent method and no override.
 *
 * The rule acts only on a method that has no native return type and no @return tag, so it
 * types the methods that PHPStan reports as missingType.return. The stock Rector rules check
 * the parents of a method, but not its children, and they skip protected methods and abstract
 * classes. This rule finds the children through MethodOverrideIndex, so it can type those too.
 * It skips deprecated classes and methods, because PHPStan does not report errors in them.
 *
 *  - void: the body returns no value and does not yield. A body that only throws stays as it is,
 *    because it is a placeholder that a subclass of another package can replace with a value.
 *  - static: every return is "return $this;", and the last statement of the body is one of them.
 *
 * A third-party class that overrides a typed method with no return type stops with a fatal
 * error. Maho accepts this break, so each pull request that runs the rule lists it in its
 * release notes.
 *
 * rector.php does not register the rule. Run it with a temporary config that lists only this
 * rule and the paths app, lib and public. Then run PHPStan, and remove the type of each method
 * that causes a new error, such as a caller that uses a void result. Last, delete the baseline
 * entries that PHPStan reports as ignore.unmatched.
 */
final class AddVoidAndStaticReturnTypeRector extends AbstractRector
{
    private const array RETURN_TAGS = ['@return', '@phpstan-return', '@psalm-return'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add the void or the static return type to a method that has no parent method and no override',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeClass
{
    protected function _prepareLayout()
    {
        $this->setTemplate('some.phtml');
        return $this;
    }

    public function reset()
    {
        $this->_items = [];
    }
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class SomeClass
{
    protected function _prepareLayout(): static
    {
        $this->setTemplate('some.phtml');
        return $this;
    }

    public function reset(): void
    {
        $this->_items = [];
    }
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
    public function refactor(Node $node): ?Node
    {
        if (!$this->isCandidate($node)) {
            return null;
        }

        $returnType = $this->resolveReturnType($node);
        if ($returnType === null) {
            return null;
        }

        $node->returnType = new Identifier($returnType);

        return $node;
    }

    private function isCandidate(ClassMethod $classMethod): bool
    {
        if ($classMethod->returnType !== null || $classMethod->stmts === null || $classMethod->byRef) {
            return false;
        }

        if (str_starts_with($classMethod->name->toString(), '__')) {
            return false;
        }

        if ($this->hasReturnTag($classMethod)) {
            return false;
        }

        $scope = ScopeFetcher::fetch($classMethod);
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection || !$classReflection->isClass() || $scope->isInTrait()) {
            return false;
        }

        if ($classReflection->isAnonymous() || $classReflection->isDeprecated()) {
            return false;
        }

        $methodName = $classMethod->name->toString();
        if ($classReflection->getNativeMethod($methodName)->isDeprecated()->yes()) {
            return false;
        }

        foreach ([...$classReflection->getParents(), ...$classReflection->getInterfaces()] as $ancestor) {
            if ($ancestor->hasNativeMethod($methodName)) {
                return false;
            }
        }

        return !MethodOverrideIndex::get()->hasOverride($classReflection->getName(), $methodName);
    }

    private function hasReturnTag(ClassMethod $classMethod): bool
    {
        $docComment = $classMethod->getDocComment();
        if ($docComment === null) {
            return false;
        }

        $text = $docComment->getText();

        return array_any(self::RETURN_TAGS, fn(string $tag): bool => str_contains($text, $tag));
    }

    private function resolveReturnType(ClassMethod $classMethod): ?string
    {
        $stmts = $classMethod->stmts ?? [];

        $returns = [];
        $yields = false;
        $this->collectReturns($stmts, $returns, $yields);

        if ($yields) {
            return null;
        }

        $valueReturns = array_filter($returns, fn(Return_ $return): bool => $return->expr !== null);

        if ($valueReturns === []) {
            return $this->isThrowOnly($stmts) ? null : 'void';
        }

        if (count($valueReturns) !== count($returns)) {
            return null;
        }

        foreach ($returns as $return) {
            if (!$return->expr instanceof Variable || $return->expr->name !== 'this') {
                return null;
            }
        }

        $lastStmt = end($stmts);

        return $lastStmt instanceof Return_ ? 'static' : null;
    }

    /**
     * Collect the return statements of the method body. A closure, an arrow function, a function
     * or a class inside the body has its own returns, so the walk does not go into them.
     *
     * @param array<Node|mixed> $nodes
     * @param list<Return_> $returns
     */
    private function collectReturns(array $nodes, array &$returns, bool &$yields): void
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                $this->collectReturns($node, $returns, $yields);
                continue;
            }

            if (!$node instanceof Node) {
                continue;
            }

            if ($node instanceof Closure || $node instanceof ArrowFunction || $node instanceof Function_ || $node instanceof ClassLike) {
                continue;
            }

            if ($node instanceof Yield_ || $node instanceof YieldFrom) {
                $yields = true;
            }

            if ($node instanceof Return_) {
                $returns[] = $node;
            }

            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->{$subNodeName};
                $this->collectReturns(is_array($subNode) ? $subNode : [$subNode], $returns, $yields);
            }
        }
    }

    /**
     * @param Node\Stmt[] $stmts
     */
    private function isThrowOnly(array $stmts): bool
    {
        if (count($stmts) !== 1 || !$stmts[0] instanceof Expression) {
            return false;
        }

        $expr = $stmts[0]->expr;
        if ($expr instanceof Throw_) {
            return true;
        }

        return $expr instanceof StaticCall && $this->isName($expr->class, 'Mage') && $this->isName($expr->name, 'throwException');
    }
}
