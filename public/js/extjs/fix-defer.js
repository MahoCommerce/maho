/**
 * Maho
 *
 * @category    Mage
 * @package     js
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/*
 * Both ExtJS and PrototypeJS write to Function.prototype.defer
 * However, PrototypeJS has a default delay of 0.01s if no first argument is provided
 * Ref: https://github.com/prototypejs/prototype/blob/1.7.3/src/prototype/lang/function.js#L292-L295
 * While ExtJS executes the function immediately. Presumably this causes an error for
 * PrototypeJS Ajax calls.
 *
 */

(function(){
    var eDefer = Function.prototype.defer;
    Function.prototype.defer = function() {
        var argLen = arguments.length;
        if (argLen==0 || (argLen==1 && arguments[0]==1)) {
            //common for Prototype Ajax requests
            return this.delay.curry(0.01).apply(this, arguments);
        }

        return eDefer.apply(this, arguments);
    }
})();
