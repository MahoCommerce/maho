<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter;

use Maho\Filter\Template\ExpressionSandbox;
use Maho\Filter\Template\Tokenizer\Parameter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

class Template
{
    /**
     * Cunstruction regular expression
     */
    public const CONSTRUCTION_PATTERN = '/{{([a-z]{0,10})(.*?)}}/si';

    /**
     * Cunstruction logic regular expression
     */
    public const CONSTRUCTION_DEPEND_PATTERN = '/{{depend\s*(.*?)}}(.*?){{\\/depend\s*}}/si';
    public const CONSTRUCTION_IF_PATTERN = '/{{if\s*(.*?)}}(.*?)({{else}}(.*?))?{{\\/if\s*}}/si';

    /**
     * Expression operators refused in {{if}} / {{depend}} conditions, see _assertNoDeniedOperator()
     */
    private const DENIED_OPERATORS = ['matches', '..'];

    /**
     * Maximum byte length of an {{if}} / {{depend}} / {{var}} expression.
     *
     * Symfony's expression parser is recursive-descent — it recurses once per operator — so a
     * deeply nested expression ({{if not not not ... x}}, {{if ((((...))))}}) overflows the native
     * C stack and takes the whole worker down with a SIGSEGV *during parse*, before any node-level
     * guard can run. A signal is not an exception, so filter()'s try/catch cannot absorb it: an
     * admin with only template-edit rights would crash transactional-mail rendering with one saved
     * template. A real condition or variable path is a few dozen bytes; capping the raw string
     * bounds nesting far below the crash depth while leaving every legitimate expression untouched.
     */
    private const MAX_EXPRESSION_LENGTH = 2000;

    private static ?ExpressionLanguage $_expressionLanguage = null;

    /**
     * Assigned template variables
     *
     * @var array
     */
    protected $_templateVars = [];

    /**
     * _templateVars run through ExpressionSandbox::wrap(), memoized for the life of the
     * assignment. Wrapping an object is cheap (it only captures it), but wrapping an array is an
     * eager filtered deep copy — the only way to gate a native subscript — so redoing it for
     * every directive makes a template's cost the product of its directive count and its largest
     * array variable. setVariables() is the only thing that can change what needs wrapping.
     *
     * @var array<string, mixed>|null
     */
    private ?array $_wrappedTemplateVars = null;

    /**
     * Template processor
     *
     * @var array|string|null
     */
    protected $_templateProcessor = null;

    /**
     * Include processor
     *
     * @var array|string|null
     */
    protected $_includeProcessor = null;

    /**
     * Sets template variables that's can be called through {var ...} statement
     */
    public function setVariables(array $variables)
    {
        foreach ($variables as $name => $value) {
            $this->_templateVars[$name] = $value;
        }
        $this->_wrappedTemplateVars = null;
        return $this;
    }

    /**
     * Sets the proccessor of templates. Templates are directives that include email templates based on system
     * configuration path.
     *
     * @param array $callback it must return string
     */
    public function setTemplateProcessor(array $callback)
    {
        $this->_templateProcessor = $callback;
        return $this;
    }

    /**
     * Sets the proccessor of templates.
     *
     * @return array|null
     */
    public function getTemplateProcessor()
    {
        return is_callable($this->_templateProcessor) ? $this->_templateProcessor : null;
    }

    /**
     * Sets the proccessor of includes.
     *
     * @param array $callback it must return string
     */
    public function setIncludeProcessor(array $callback)
    {
        $this->_includeProcessor = $callback;
        return $this;
    }

    /**
     * Sets the proccessor of includes.
     *
     * @return array|null
     */
    public function getIncludeProcessor()
    {
        return is_callable($this->_includeProcessor) ? $this->_includeProcessor : null;
    }

    /**
     * Filter the string as template.
     *
     * @param string $value
     * @return string
     */
    public function filter($value)
    {
        if ($value === null) {
            return '';
        }

        // "depend" and "if" operands should be first
        $directives = [
            self::CONSTRUCTION_DEPEND_PATTERN => 'dependDirective',
            self::CONSTRUCTION_IF_PATTERN     => 'ifDirective',
        ];
        foreach ($directives as $pattern => $directive) {
            if (preg_match_all($pattern, $value, $constructions, PREG_SET_ORDER)) {
                foreach ($constructions as $index => $construction) {
                    $replacedValue = '';
                    $callback = [$this, $directive];
                    try {
                        $replacedValue = call_user_func($callback, $construction);
                    } catch (\Exception $e) {
                        throw $e;
                    }
                    $value = str_replace($construction[0], $replacedValue, $value);
                }
            }
        }

        if (preg_match_all(self::CONSTRUCTION_PATTERN, $value, $constructions, PREG_SET_ORDER)) {
            foreach ($constructions as $index => $construction) {
                $replacedValue = '';
                $callback = [$this, $construction[1] . 'Directive'];
                if (!is_callable($callback)) {
                    continue;
                }
                try {
                    $replacedValue = call_user_func($callback, $construction);
                } catch (\Exception $e) {
                    throw $e;
                }
                $value = str_replace($construction[0], $replacedValue ?? '', $value);
            }
        }
        return $value;
    }

    public function varDirective($construction)
    {
        if (count($this->_templateVars) == 0) {
            // If template preprocessing
            return $construction[0];
        }

        return $this->_stringifyVariable($this->_getVariable($construction[2], ''));
    }

    /**
     * Render a resolved {{var}} value as the string that replaces the directive.
     *
     * A path can legitimately resolve to a model — {{var order.billing_address}} hands back the
     * address object — and casting one that defines no __toString() raises a native \Error, which
     * is not an \Exception and so escapes filter()'s handler and aborts the whole render. An
     * object with no string form has no template representation, so it degrades to the empty
     * default like every other unresolvable path.
     *
     * An array degrades the same way: a getter returning one ({{var product.multiselect_attr}})
     * would otherwise cast to the literal text "Array" in the rendered mail and raise an
     * "Array to string conversion" warning while doing it.
     */
    protected function _stringifyVariable(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value) || (is_object($value) && !$value instanceof \Stringable)) {
            return '';
        }
        return (string) $value;
    }

    public function includeDirective($construction)
    {
        // Processing of {include template=... [...]} statement
        $includeParameters = $this->_getIncludeParameters($construction[2]);
        if (!isset($includeParameters['template']) || !$this->getIncludeProcessor()) {
            // Not specified template or not seted include processor
            $replacedValue = '{Error in include processing}';
        } else {
            // Including of template
            $templateCode = $includeParameters['template'];
            unset($includeParameters['template']);
            $includeParameters = array_merge_recursive($includeParameters, $this->_templateVars);
            $replacedValue = call_user_func($this->getIncludeProcessor(), $templateCode, $includeParameters);
        }
        return $replacedValue;
    }

    /**
     * This directive allows email templates to be included inside other email templates using the following syntax:
     * {{template config_path="<PATH>"}}, where <PATH> equals the XPATH to the system configuration value that contains
     * the value of the email template. For example "sales_email/order/template", which is stored in the
     * Mage_Sales_Model_Order::sales_email/order/template. This directive is useful to include things like a global
     * header/footer.
     *
     * @param $construction
     * @return mixed|string
     */
    public function templateDirective($construction)
    {
        // Processing of {template config_path=... [...]} statement
        $templateParameters = $this->_getIncludeParameters($construction[2]);
        if (!isset($templateParameters['config_path']) || !$this->getTemplateProcessor()) {
            $replacedValue = '{Error in template processing}';
        } else {
            // Including of template
            $configPath = $templateParameters['config_path'];
            unset($templateParameters['config_path']);
            $templateParameters = array_merge_recursive($templateParameters, $this->_templateVars);
            $replacedValue = call_user_func($this->getTemplateProcessor(), $configPath, $templateParameters);
        }
        return $replacedValue;
    }

    public function dependDirective($construction)
    {
        if (count($this->_templateVars) == 0) {
            // If template preprocessing
            return $construction[0];
        }

        if (!$this->_evaluateCondition($construction[1])) {
            return '';
        }
        return $construction[2];
    }

    public function ifDirective($construction)
    {
        if (count($this->_templateVars) == 0) {
            return $construction[0];
        }

        if (!$this->_evaluateCondition($construction[1])) {
            if (isset($construction[3]) && isset($construction[4])) {
                return $construction[4];
            }
            return '';
        }
        return $construction[2];
    }

    /**
     * Evaluate an {{if}} / {{depend}} condition as a Symfony ExpressionLanguage expression
     * against the template variables, e.g. {{if order.grand_total > 100 && customer.group_id == 2}}.
     *
     * The legacy single-variable syntax ("order.increment_id", "customer.getName()") is a
     * subset of the expression syntax and keeps working: identifiers the template does not
     * define evaluate as null, and the result uses standard PHP truthiness. A condition that
     * fails to parse or evaluate logs and yields false, so a broken template degrades instead
     * of aborting.
     *
     * Truthiness is the one deliberate behaviour change: the legacy directive tested
     * _getVariable(...) != '', which under PHP 8 makes 0, '0' and 0.0 *truthy*, so
     * {{if order.getItemsCount()}} rendered its true branch for an empty order. Casting to bool
     * makes those falsy, which is what a template author writing {{if x}} means; the cost is
     * that a field whose value is literally "0" (a house number, say) now takes the else branch.
     */
    protected function _evaluateCondition(string $condition): bool
    {
        $expression = trim($condition);
        if ($expression === '') {
            return false;
        }

        $values = $this->_expressionValues($expression);
        try {
            $parsed = self::_parseExpression($expression, array_keys($values));
            return (bool) self::_getExpressionLanguage()->evaluate($parsed, $values);
        } catch (\Throwable $e) {
            self::_logExpressionFailure(sprintf('Invalid condition "%s" in template directive', $expression), $e);
            return false;
        }
    }

    /**
     * Log an expression that could not be resolved, at a level matching how alarming it is.
     *
     * A hop past a value that is legitimately absent — {{if order.shipping_address.company}} on a
     * virtual order — makes Symfony throw a plain \RuntimeException ("Unable to get property ... of
     * non-object"), because a template author never writes its ?. null-safe syntax. The legacy
     * tokenizer resolved that to the default silently, so logging it as a warning on every render
     * would flood system.log for templates that are working as intended: it is recorded at debug
     * level instead. Everything else — a syntax error, a denied operator, an exception escaping a
     * getter (which is a \RuntimeException *subclass*, never the base class Symfony throws) — still
     * warns.
     */
    private static function _logExpressionFailure(string $message, \Throwable $e): void
    {
        $level = $e::class === \RuntimeException::class ? \Mage::LOG_DEBUG : \Mage::LOG_WARNING;
        \Mage::log(sprintf('%s: %s', $message, $e->getMessage()), $level);
    }

    /**
     * Build the variable map an expression is evaluated against: every template variable
     * wrapped so property/method access is gated, plus every identifier-shaped token in the
     * expression defaulted to null so an absent variable ({{depend comment}}, {{var comment}})
     * evaluates silently as empty instead of failing the parse. Extra names never change how
     * operators or literals are parsed. Shared by {{if}}/{{depend}} and {{var}}.
     *
     * @return array<string, mixed>
     */
    private function _expressionValues(string $expression): array
    {
        if ($this->_wrappedTemplateVars === null) {
            $this->_wrappedTemplateVars = [];
            foreach ($this->_templateVars as $name => $value) {
                $this->_wrappedTemplateVars[$name] = ExpressionSandbox::wrap($value);
            }
        }
        $values = $this->_wrappedTemplateVars;
        preg_match_all('/\b[a-z_][a-z0-9_]*\b/i', $expression, $matches);
        foreach ($matches[0] as $name) {
            $values[$name] ??= null;
        }
        return $values;
    }

    /**
     * Parse an expression under the guards {{if}}/{{depend}} and {{var}} share: refuse an
     * over-long expression before the recursive parser can run into it (see MAX_EXPRESSION_LENGTH)
     * and refuse a denied operator once parsed. Both refusals throw from the \LogicException
     * family, so the callers' \Throwable catch routes them through _logExpressionFailure() as an
     * authoring error, the same as Symfony's own SyntaxError.
     *
     * @param string[] $names identifiers the expression is allowed to reference
     * @throws \LengthException|\LogicException|\Symfony\Component\ExpressionLanguage\SyntaxError
     */
    private static function _parseExpression(string $expression, array $names): ParsedExpression
    {
        if (strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new \LengthException(sprintf('expression exceeds %d bytes', self::MAX_EXPRESSION_LENGTH));
        }
        $parsed = self::_getExpressionLanguage()->parse($expression, $names);
        self::_assertNoDeniedOperator($parsed->getNodes());
        return $parsed;
    }

    /**
     * Reject the two operators a condition never legitimately needs but which let a template
     * author stall the process: "matches" runs an arbitrary regex against arbitrary input
     * (catastrophic backtracking) and ".." builds a range array of arbitrary size.
     *
     * Refusing an operator is a template-authoring error like Symfony's own SyntaxError, so it
     * throws from the \LogicException family: _logExpressionFailure() reserves the quiet debug
     * level for the base \RuntimeException Symfony throws when a hop resolves to null.
     *
     * @throws \LogicException
     */
    private static function _assertNoDeniedOperator(Node $node): void
    {
        $operator = $node->attributes['operator'] ?? null;
        if (in_array($operator, self::DENIED_OPERATORS, true)) {
            throw new \LogicException(sprintf('operator "%s" is not allowed in a condition', $operator));
        }
        foreach ($node->nodes as $child) {
            if ($child instanceof Node) {
                self::_assertNoDeniedOperator($child);
            }
        }
    }

    protected static function _getExpressionLanguage(): ExpressionLanguage
    {
        if (self::$_expressionLanguage === null) {
            $language = new ExpressionLanguage();
            // Templates must not read arbitrary PHP/class constants
            foreach (['constant', 'enum'] as $function) {
                $language->register(
                    $function,
                    static fn(): string => 'null',
                    static fn(): mixed => null,
                );
            }
            self::$_expressionLanguage = $language;
        }
        return self::$_expressionLanguage;
    }

    /**
     * Return associative array of include construction.
     *
     * @param string $value raw parameters
     * @return array
     */
    protected function _getIncludeParameters($value)
    {
        $tokenizer = new Parameter();
        $tokenizer->setString($value);
        $params = $tokenizer->tokenize();
        foreach ($params as $key => $value) {
            if (str_starts_with($value, '$')) {
                $params[$key] = $this->_getVariable(substr($value, 1), null);
            }
        }
        return $params;
    }

    /**
     * Resolve a {{var}} object path to its value through the same sandboxed ExpressionLanguage
     * engine as {{if}}/{{depend}}: property reads and read-only accessors work (including
     * arg-bearing ones such as getAttributeText('color') and format('html')), while
     * state-changing methods, secret fields and whole-object dumps are refused by the wrapper.
     * Returns $default when the path is empty, resolves to nothing, or fails to parse/evaluate.
     *
     * @param string $value raw variable expression
     * @param string|null $default default value
     * @return mixed
     */
    protected function _getVariable($value, $default = '{no_value_defined}')
    {
        \Maho\Profiler::start('email_template_proccessing_variables');
        $expression = trim($value);
        if ($expression === '') {
            \Maho\Profiler::stop('email_template_proccessing_variables');
            return $default;
        }
        try {
            $values = $this->_expressionValues($expression);
            $parsed = self::_parseExpression($expression, array_keys($values));
            $result = ExpressionSandbox::unwrap(self::_getExpressionLanguage()->evaluate($parsed, $values)) ?? $default;
        } catch (\Throwable $e) {
            self::_logExpressionFailure(sprintf('Invalid variable "%s" in template directive', $expression), $e);
            $result = $default;
        }
        \Maho\Profiler::stop('email_template_proccessing_variables');
        return $result;
    }
}
