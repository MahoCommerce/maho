<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter;

use Maho\Filter\Template\ExpressionObjectWrapper;
use Maho\Filter\Template\Tokenizer\Parameter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\Node;

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
     * Expression operators refused in {{if}} / {{depend}} conditions, see assertNoDeniedOperator()
     */
    private const DENIED_OPERATORS = ['matches', '..'];

    private static ?ExpressionLanguage $_expressionLanguage = null;

    /**
     * Assigned template variables
     *
     * @var array
     */
    protected $_templateVars = [];

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

        $replacedValue = $this->_getVariable($construction[2], '');
        if ($replacedValue instanceof \DateTimeInterface) {
            $replacedValue = $replacedValue->format('Y-m-d H:i:s');
        }
        return (string) $replacedValue;
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

        if (!$this->evaluateCondition($construction[1])) {
            return '';
        }
        return $construction[2];
    }

    public function ifDirective($construction)
    {
        if (count($this->_templateVars) == 0) {
            return $construction[0];
        }

        if (!$this->evaluateCondition($construction[1])) {
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
     * fails to parse or evaluate logs a warning and yields false, so a broken template
     * degrades instead of aborting.
     */
    protected function evaluateCondition(string $condition): bool
    {
        $expression = trim($condition);
        if ($expression === '') {
            return false;
        }

        $values = $this->_expressionValues($expression);
        try {
            $language = self::getExpressionLanguage();
            $parsed = $language->parse($expression, array_keys($values));
            self::assertNoDeniedOperator($parsed->getNodes());
            return (bool) $language->evaluate($parsed, $values);
        } catch (\Throwable $e) {
            \Mage::log(
                sprintf('Invalid condition "%s" in template directive: %s', $expression, $e->getMessage()),
                \Mage::LOG_WARNING,
            );
            return false;
        }
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
        $values = [];
        foreach ($this->_templateVars as $name => $value) {
            $values[$name] = ExpressionObjectWrapper::wrap($value);
        }
        preg_match_all('/\b[a-z_][a-z0-9_]*\b/i', $expression, $matches);
        foreach ($matches[0] as $name) {
            $values[$name] ??= null;
        }
        return $values;
    }

    /**
     * Reject the two operators a condition never legitimately needs but which let a template
     * author stall the process: "matches" runs an arbitrary regex against arbitrary input
     * (catastrophic backtracking) and ".." builds a range array of arbitrary size.
     *
     * @throws \RuntimeException
     */
    private static function assertNoDeniedOperator(Node $node): void
    {
        $operator = $node->attributes['operator'] ?? null;
        if (in_array($operator, self::DENIED_OPERATORS, true)) {
            throw new \RuntimeException(sprintf('operator "%s" is not allowed in a condition', $operator));
        }
        foreach ($node->nodes as $child) {
            if ($child instanceof Node) {
                self::assertNoDeniedOperator($child);
            }
        }
    }

    protected static function getExpressionLanguage(): ExpressionLanguage
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
            $language = self::getExpressionLanguage();
            $parsed = $language->parse($expression, array_keys($values));
            self::assertNoDeniedOperator($parsed->getNodes());
            $result = ExpressionObjectWrapper::unwrap($language->evaluate($parsed, $values)) ?? $default;
        } catch (\Throwable $e) {
            \Mage::log(
                sprintf('Invalid variable "%s" in template directive: %s', $expression, $e->getMessage()),
                \Mage::LOG_WARNING,
            );
            $result = $default;
        }
        \Maho\Profiler::stop('email_template_proccessing_variables');
        return $result;
    }
}
