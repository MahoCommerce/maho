<?php

/**
 * Entry points for evaluating a template directive against sandboxed variables.
 *
 * wrap() guards a value on its way into an ExpressionLanguage evaluation, unwrap() takes the
 * result back out. Both live here rather than on ExpressionObjectWrapper because a wrapper
 * instance is handed to the expression engine, and Symfony resolves "customer.foo()" with
 * is_callable([$wrapper, 'foo']) — every real public method of the wrapper, static ones
 * included, is therefore callable straight from a template without passing through the gated
 * __call(). A public ExpressionObjectWrapper::unwrap() would let a template write
 * "{{var customer.unwrap(customer)}}" and continue from the raw model with no guard at all.
 * This class extends the wrapper only to borrow its scope: it is never instantiated and never
 * handed to the engine, so publishing the two methods here exposes nothing to an expression.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Filter\Template;

final class ExpressionSandbox extends ExpressionObjectWrapper
{
    public static function wrap(mixed $value): mixed
    {
        return parent::_wrap($value);
    }

    public static function unwrap(mixed $value): mixed
    {
        return parent::_unwrap($value);
    }
}
