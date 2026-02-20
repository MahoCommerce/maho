<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Base route class for API2 routing
 *
 * Simplified route matching for REST API patterns like /api/:api_type/products/:id
 * Supports variables (:varName) and regex requirements
 *
 * - Pattern parsing with :variables
 * - Regex requirements for variables
 * - Default values
 * - URL path matching
 */
class Mage_Api2_Model_Route_Base
{
    /**
     * URI delimiter
     */
    public const URI_DELIMITER = '/';

    /**
     * Variable prefix in route pattern
     */
    protected string $_urlVariable = ':';

    /**
     * URI delimiter for splitting paths
     */
    protected string $_urlDelimiter = self::URI_DELIMITER;

    /**
     * Regex delimiter for pattern matching
     */
    protected string $_regexDelimiter = '#';

    /**
     * Default regex for variables (null = match anything)
     */
    protected ?string $_defaultRegex = null;

    /**
     * Holds names of all route's pattern variable names. Array index holds a position in URL.
     *
     * @var array<int, string>
     */
    protected array $_variables = [];

    /**
     * Holds Route patterns for all URL parts.
     * - For a variable: stores its regex requirement or null
     * - For a static part: stores its literal value
     *
     * @var array<int, string|null>
     */
    protected array $_parts = [];

    /**
     * Holds user submitted default values for route's variables
     *
     * @var array<string, mixed>
     */
    protected array $_defaults = [];

    /**
     * Holds user submitted regular expression patterns for route's variables' values
     *
     * @var array<string, string>
     */
    protected array $_requirements = [];

    /**
     * Associative array filled on match() that holds matched path values
     *
     * @var array<string, string>
     */
    protected array $_values = [];

    /**
     * Count of route pattern's static parts for validation
     */
    protected int $_staticCount = 0;

    /**
     * Path matched by this route
     */
    protected ?string $_matchedPath = null;

    /**
     * Prepares the route for mapping by splitting (exploding) it
     * to corresponding atomic parts.
     *
     * @param string $route Map used to match with later submitted URL path (e.g., "api/:api_type/products/:id")
     * @param array<string, mixed> $defaults Defaults for map variables with keys as variable names
     * @param array<string, string> $reqs Regular expression requirements for variables (keys as variable names)
     */
    public function __construct(
        string $route,
        array $defaults = [],
        array $reqs = [],
    ) {
        $route               = trim($route, $this->_urlDelimiter);
        $this->_defaults     = $defaults;
        $this->_requirements = $reqs;

        if ($route !== '') {
            foreach (explode($this->_urlDelimiter, $route) as $pos => $part) {
                // Check if this part is a variable (starts with : but not ::)
                if (str_starts_with($part, $this->_urlVariable) && !str_starts_with($part, $this->_urlVariable . $this->_urlVariable)) {
                    $name = substr($part, 1);

                    $this->_parts[$pos]     = $reqs[$name] ?? $this->_defaultRegex;
                    $this->_variables[$pos] = $name;
                } else {
                    // Escape literal : if pattern is ::something
                    if (str_starts_with($part, $this->_urlVariable)) {
                        $part = substr($part, 1);
                    }

                    $this->_parts[$pos] = $part;
                    $this->_staticCount++;
                }
            }
        }
    }

    /**
     * Matches a user submitted path with parts defined by a map.
     * Assigns and returns an array of variables on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @param bool $partial Allow partial path matching (default: false)
     * @return array<string, mixed>|false An array of assigned values or false on a mismatch
     */
    public function match(string $path, bool $partial = false): array|false
    {
        $pathStaticCount = 0;
        $values          = [];
        $matchedPath     = '';

        if (!$partial) {
            $path = trim($path, $this->_urlDelimiter);
        }

        if ($path !== '') {
            $pathParts = explode($this->_urlDelimiter, $path);

            foreach ($pathParts as $pos => $pathPart) {
                // Path is longer than a route, it's not a match
                if (!array_key_exists($pos, $this->_parts)) {
                    if ($partial) {
                        break;
                    }
                    return false;
                }

                $matchedPath .= $pathPart . $this->_urlDelimiter;

                $name     = $this->_variables[$pos] ?? null;
                $pathPart = urldecode($pathPart);
                $part     = $this->_parts[$pos];

                // If it's a static part, match directly
                if ($name === null && $part !== $pathPart) {
                    return false;
                }

                // If it's a variable with requirement, match a regex. If not - everything matches
                if ($part !== null
                    && !preg_match(
                        $this->_regexDelimiter . '^' . $part . '$' . $this->_regexDelimiter . 'iu',
                        $pathPart,
                    )
                ) {
                    return false;
                }

                // If it's a variable store its value for later
                if ($name !== null) {
                    $values[$name] = $pathPart;
                } else {
                    $pathStaticCount++;
                }
            }
        }

        // Check if all static mappings have been matched
        if ($this->_staticCount !== $pathStaticCount) {
            return false;
        }

        $return = $values + $this->_defaults;

        // Check if all map variables have been initialized
        foreach ($this->_variables as $var) {
            if (!array_key_exists($var, $return)) {
                return false;
            }
            if ($return[$var] === '' || $return[$var] === null) {
                // Empty variable? Replace with the default value.
                $return[$var] = $this->_defaults[$var] ?? null;
            }
        }

        $this->setMatchedPath(rtrim($matchedPath, $this->_urlDelimiter));

        $this->_values = $values;

        return $return;
    }

    /**
     * Set partially matched path
     */
    public function setMatchedPath(string $path): void
    {
        $this->_matchedPath = $path;
    }

    /**
     * Get partially matched path
     */
    public function getMatchedPath(): ?string
    {
        return $this->_matchedPath;
    }

    /**
     * Return a single parameter of route's defaults
     */
    public function getDefault(string $name): mixed
    {
        return $this->_defaults[$name] ?? null;
    }

    /**
     * Return an array of defaults
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return $this->_defaults;
    }

    /**
     * Get all variables which are used by the route
     *
     * @return array<int, string>
     */
    public function getVariables(): array
    {
        return $this->_variables;
    }

    /**
     * Create a new chain by combining this route with another
     *
     * Returns a chain object that can match/assemble both routes in sequence.
     * Used for API2 to combine api/:api_type with resource-specific routes.
     *
     * @param Mage_Api2_Model_Route_Base|string $route Route object or route pattern string
     * @param string $separator Separator between routes
     */
    public function chain($route, string $separator = '/'): Mage_Api2_Model_Route_Chain
    {
        // If route is a string pattern, convert it to a route object
        if (is_string($route)) {
            $route = new Mage_Api2_Model_Route_Base($route);
        }

        return new Mage_Api2_Model_Route_Chain($this, $separator, $route);
    }

    /**
     * Assembles user submitted parameters forming a URL path defined by this route
     *
     * Takes route pattern like "api/:api_type/products/:id" and fills in variables
     * from $data to create "api/rest/products/123"
     *
     * @param array<string, mixed> $data An array of variable and value pairs used as parameters
     * @param bool $reset Whether or not to set route defaults with those provided in $data
     * @param bool $encode Whether to URL encode the assembled path
     * @param bool $partial Whether to allow partial URL assembly
     * @return string Route path with user submitted parameters
     */
    public function assemble(array $data = [], bool $reset = false, bool $encode = false, bool $partial = false): string
    {
        $url = [];

        // Build URL from parts, replacing variables with data values
        foreach ($this->_parts as $key => $part) {
            $name = $this->_variables[$key] ?? null;

            if ($name !== null) {
                // This is a variable - get its value from data
                if (isset($data[$name])) {
                    $value = $data[$name];
                    unset($data[$name]);
                } elseif (isset($this->_defaults[$name])) {
                    $value = $this->_defaults[$name];
                } else {
                    // Required variable is missing
                    continue;
                }

                $url[$key] = $encode ? urlencode((string) $value) : (string) $value;
            } else {
                // This is a static part
                $url[$key] = $part;
            }
        }

        return implode($this->_urlDelimiter, $url);
    }
}
