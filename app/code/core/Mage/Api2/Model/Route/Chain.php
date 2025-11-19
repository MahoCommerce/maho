<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
     * @param string $separator Separator between routes
     * @param Mage_Api2_Model_Route_Base $route2 Second route
     */
    public function __construct(
        Mage_Api2_Model_Route_Base $route1,
        string $separator,
        Mage_Api2_Model_Route_Base $route2,
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
     * @param string|Mage_Api2_Model_Request $path Path string or Request object to match
     * @param bool $partial Allow partial matching
     * @return array<string, mixed>|false
     */
    #[\Override]
    public function match($path, bool $partial = false): array|false
    {
        // Handle both string paths and Request objects
        if ($path instanceof Mage_Api2_Model_Request) {
            $pathString = ltrim($path->getPathInfo(), '/');
        } else {
            $pathString = $path;
        }

        // Special handling when separator is '/' - use normal partial matching
        if ($this->_separator === '/') {
            $result1 = $this->_route1->match($pathString, true);
            if ($result1 === false) {
                return false;
            }

            $matchedPath1 = $this->_route1->getMatchedPath();
            if ($matchedPath1) {
                $remainder = substr($pathString, strlen($matchedPath1));
                if (str_starts_with($remainder, '/')) {
                    $pathRemainder = substr($remainder, 1);
                } else {
                    $pathRemainder = $remainder;
                }
            } else {
                $pathRemainder = $pathString;
            }

            $result2 = $this->_route2->match($pathRemainder, $partial);
            if ($result2 === false) {
                return false;
            }

            // Set combined matched path
            $matchedPath2 = $this->_route2->getMatchedPath();
            if ($matchedPath1 && $matchedPath2) {
                $this->setMatchedPath($matchedPath1 . '/' . $matchedPath2);
            } elseif ($matchedPath1) {
                $this->setMatchedPath($matchedPath1);
            } elseif ($matchedPath2) {
                $this->setMatchedPath($matchedPath2);
            }

            return $result2 + $result1;
        }

        // For non-'/' separators, try to find the separator and split there
        if ($this->_separator !== '') {
            // Find all occurrences of the separator
            $separatorPos = strpos($pathString, $this->_separator);

            // Try different split points
            while ($separatorPos !== false) {
                $firstPart = substr($pathString, 0, $separatorPos);
                $secondPart = substr($pathString, $separatorPos + strlen($this->_separator));

                // Try to match the first part
                $result1 = $this->_route1->match($firstPart);
                if ($result1 !== false) {
                    // First part matched, try second part
                    $result2 = $this->_route2->match($secondPart, $partial);
                    if ($result2 !== false) {
                        // Both matched!
                        $matchedPath1 = $this->_route1->getMatchedPath();
                        $matchedPath2 = $this->_route2->getMatchedPath();

                        if ($matchedPath1 && $matchedPath2) {
                            $this->setMatchedPath($matchedPath1 . $this->_separator . $matchedPath2);
                        } elseif ($matchedPath1) {
                            $this->setMatchedPath($matchedPath1);
                        } elseif ($matchedPath2) {
                            $this->setMatchedPath($matchedPath2);
                        }

                        // Combine results - second route values override first
                        return $result2 + $result1;
                    }
                }

                // Try next occurrence
                $separatorPos = strpos($pathString, $this->_separator, $separatorPos + 1);
            }
        } else {
            // Empty separator - concatenated routes
            // Try all possible split points
            for ($i = 0; $i <= strlen($pathString); $i++) {
                $firstPart = substr($pathString, 0, $i);
                $secondPart = substr($pathString, $i);

                $result1 = $this->_route1->match($firstPart);
                if ($result1 !== false) {
                    $result2 = $this->_route2->match($secondPart, $partial);
                    if ($result2 !== false) {
                        // Both matched!
                        $matchedPath1 = $this->_route1->getMatchedPath();
                        $matchedPath2 = $this->_route2->getMatchedPath();

                        $this->setMatchedPath($matchedPath1 . $matchedPath2);

                        return $result2 + $result1;
                    }
                }
            }
        }

        return false;
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

    /**
     * Get specific default value from either route
     *
     * @param string $name Name of the default
     */
    #[\Override]
    public function getDefault(string $name): mixed
    {
        $defaults = $this->getDefaults();
        return $defaults[$name] ?? null;
    }
}
