<?php

/**
 * Index of the classes in the project, to find the methods that a subclass declares again.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Rector;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Rector finds the parents of a class, but not its children. This index reads every PHP file of
 * the project one time, anonymous classes and the tests included, and records for each class its
 * parent, its interfaces, its traits and its own methods. All names are in lower case, and a
 * Varien name from the class_alias list of app/bootstrap.php resolves to its Maho class.
 */
final class MethodOverrideIndex
{
    private const array SCANNED_PATHS = ['app', 'lib', 'maho', 'public', 'tests'];

    private static ?self $instance = null;

    /** @var array<string, list<string>> class name => names of the classes that extend or implement it */
    private array $children = [];

    /** @var array<string, array<string, true>> class name => its own method names */
    private array $methods = [];

    /** @var array<string, list<string>> class name => names of the traits it uses */
    private array $traits = [];

    /** @var array<string, string> alias => class */
    private array $aliases = [];

    /** @var array<string, bool> */
    private array $overrideCache = [];

    private function __construct(private readonly string $rootDir) {}

    public static function get(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self(dirname(__DIR__, 3));
            self::$instance->build();
        }

        return self::$instance;
    }

    /**
     * Tell if a class below $className declares $methodName, directly or through a trait.
     */
    public function hasOverride(string $className, string $methodName): bool
    {
        $className = $this->normalize($className);
        $methodName = strtolower($methodName);
        $key = $className . '::' . $methodName;

        return $this->overrideCache[$key] ??= $this->findOverride($className, $methodName, []);
    }

    /**
     * @param array<string, true> $visited
     */
    private function findOverride(string $className, string $methodName, array $visited): bool
    {
        foreach ($this->children[$className] ?? [] as $childName) {
            if (isset($visited[$childName])) {
                continue;
            }

            $visited[$childName] = true;

            if ($this->declaresMethod($childName, $methodName, [])) {
                return true;
            }

            if ($this->findOverride($childName, $methodName, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $visited
     */
    private function declaresMethod(string $className, string $methodName, array $visited): bool
    {
        if (isset($this->methods[$className][$methodName])) {
            return true;
        }

        foreach ($this->traits[$className] ?? [] as $traitName) {
            if (isset($visited[$traitName])) {
                continue;
            }

            $visited[$traitName] = true;

            if ($this->declaresMethod($traitName, $methodName, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function build(): void
    {
        $this->readAliases();

        $parser = new ParserFactory()->createForHostVersion();
        $nodeFinder = new NodeFinder();

        foreach ($this->listFiles() as $file) {
            $code = file_get_contents($file);
            if ($code === false) {
                continue;
            }

            try {
                $stmts = $parser->parse($code);
            } catch (\PhpParser\Error) {
                continue;
            }

            if ($stmts === null) {
                continue;
            }

            $traverser = new NodeTraverser(new NameResolver());
            $stmts = $traverser->traverse($stmts);

            foreach ($nodeFinder->findInstanceOf($stmts, ClassLike::class) as $classLike) {
                $this->addClassLike($classLike, $file);
            }
        }
    }

    private function addClassLike(ClassLike $classLike, string $file): void
    {
        $className = $classLike->namespacedName instanceof Name
            ? $this->normalize($classLike->namespacedName->toString())
            : 'class@anonymous:' . $file . ':' . $classLike->getStartLine();

        $parents = [];
        if ($classLike instanceof Class_) {
            if ($classLike->extends instanceof Name) {
                $parents[] = $classLike->extends;
            }

            array_push($parents, ...$classLike->implements);
        } elseif ($classLike instanceof Interface_) {
            array_push($parents, ...$classLike->extends);
        }

        foreach ($parents as $parent) {
            $this->children[$this->normalize($parent->toString())][] = $className;
        }

        foreach ($classLike->getMethods() as $classMethod) {
            $this->methods[$className][$classMethod->name->toLowerString()] = true;
        }

        foreach ($classLike->stmts as $stmt) {
            if (!$stmt instanceof TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $trait) {
                $this->traits[$className][] = $this->normalize($trait->toString());
            }
        }
    }

    private function readAliases(): void
    {
        $bootstrap = file_get_contents($this->rootDir . '/app/bootstrap.php');
        if ($bootstrap !== false) {
            preg_match_all("#class_alias\(\\\\?([\\w\\\\]+)::class,\s*'([\\w\\\\]+)'\)#", $bootstrap, $matches, PREG_SET_ORDER);
            foreach ($matches as [, $className, $alias]) {
                $this->aliases[strtolower($alias)] = strtolower($className);
            }
        }

        foreach (VarienToMahoClassMap::getMap() as $alias => $className) {
            $this->aliases[strtolower($alias)] ??= strtolower($className);
        }
    }

    private function normalize(string $className): string
    {
        $className = strtolower(ltrim($className, '\\'));

        return $this->aliases[$className] ?? $className;
    }

    /**
     * @return \Generator<string>
     */
    private function listFiles(): \Generator
    {
        foreach (self::SCANNED_PATHS as $path) {
            $fullPath = $this->rootDir . '/' . $path;
            if (is_file($fullPath)) {
                yield $fullPath;
                continue;
            }

            if (!is_dir($fullPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
                if ($fileInfo->getExtension() === 'php') {
                    yield $fileInfo->getPathname();
                }
            }
        }
    }
}
