<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Route chain for combining multiple routes
 *
 * Allows chaining routes like: api/:api_type + products/:id = api/:api_type/products/:id
 * Used by API2 to build compound routes.
 */
class Mage_Api2_Model_Route_Chain extends Mage_Api2_Model_Route_Base
{
    /**
     * First route in the chain
     */
    protected Mage_Api2_Model_Route_Base $_route1;

    /**
     * Second route in the chain
     */
    protected Mage_Api2_Model_Route_Base $_route2;

    /**
     * Separator between routes
     */
    protected string $_separator;

    /**
     * Create a chain of two routes
     *
     * @param Mage_Api2_Model_Route_Base $route1 First route
     * @param Mage_Api2_Model_Route_Base $route2 Second route
     * @param string $separator Separator between routes (default: '/')
     */
    public function __construct(
        Mage_Api2_Model_Route_Base $route1,
        Mage_Api2_Model_Route_Base $route2,
        string $separator = '/',
    ) {
        $this->_route1 = $route1;
        $this->_route2 = $route2;
        $this->_separator = $separator;

        // Don't call parent constructor - we don't have a route pattern
    }

    /**
     * Match path against chained routes
     *
     * Matches first route, then matches remainder against second route.
     *
     * @param string $path Path to match
     * @param bool $partial Allow partial matching
     * @return array<string, mixed>|false
     */
    #[\Override]
    public function match(string $path, bool $partial = false): array|false
    {
        // Match first route
        $result1 = $this->_route1->match($path, true);
        if ($result1 === false) {
            return false;
        }

        // Get matched path and calculate remainder
        $matchedPath = $this->_route1->getMatchedPath();
        if ($matchedPath) {
            $pathRemainder = ltrim(substr($path, strlen($matchedPath)), $this->_separator);
        } else {
            $pathRemainder = $path;
        }

        // Match remainder against second route
        $result2 = $this->_route2->match($pathRemainder, $partial);
        if ($result2 === false) {
            return false;
        }

        // Combine results
        $this->setMatchedPath($matchedPath . $this->_separator . $this->_route2->getMatchedPath());

        return $result1 + $result2;
    }

    /**
     * Assemble URL from chained routes
     *
     * Assembles both routes and joins them with separator.
     *
     * @param array<string, mixed> $data Parameters for assembly
     * @param bool $reset Reset to defaults
     * @param bool $encode URL encode the result
     * @param bool $partial Allow partial assembly
     */
    #[\Override]
    public function assemble(array $data = [], bool $reset = false, bool $encode = false, bool $partial = false): string
    {
        $url1 = $this->_route1->assemble($data, $reset, $encode, $partial);
        $url2 = $this->_route2->assemble($data, $reset, $encode, $partial);

        if ($url1 && $url2) {
            return $url1 . $this->_separator . $url2;
        }

        return $url1 . $url2;
    }

    /**
     * Get defaults from both routes
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function getDefaults(): array
    {
        return $this->_route1->getDefaults() + $this->_route2->getDefaults();
    }
}
