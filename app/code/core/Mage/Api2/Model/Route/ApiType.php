<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Route_ApiType extends Mage_Api2_Model_Route_Abstract implements Mage_Api2_Model_Route_Interface
{
    /**
     * API url template with API type variable
     * @deprecated
     */
    public const API_ROUTE = 'api/:api_type';

    /**
     * Prepares the route for mapping by splitting (exploding) it
     * to a corresponding atomic parts. These parts are assigned
     * a position which is later used for matching and preparing values.
     *
     * @param string|array<string, mixed> $route Map used to match with later submitted URL path, or array of parameters
     * @param array<string, mixed> $defaults Defaults for map variables with keys as variable names
     * @param array<string, string> $reqs Regular expression requirements for variables (keys as variable names)
     */
    public function __construct(
        $route,
        $defaults = [],
        $reqs = [],
    ) {
        // If $route is an array (array format constructor), use it directly
        if (is_array($route)) {
            parent::__construct($route);
        } else {
            // If $route is a string, construct the arguments array
            parent::__construct([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => $route,
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => $defaults,
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => $reqs,
            ]);
        }
    }

    /**
     * Matches a Request with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string|Mage_Api2_Model_Request $path Path string or Request object to match
     * @param bool $partial OPTIONAL Partial path matching (default: false)
     * @return array<string, mixed>|false An array of assigned values or false on a mismatch
     */
    #[\Override]
    public function match($path, bool $partial = false): array|false
    {
        // First try normal PATH_INFO matching
        $result = parent::match($path, $partial);

        // If no match and 'type' query parameter exists from Request object, use it as fallback
        if (!$result && $path instanceof Mage_Api2_Model_Request) {
            $apiType = $path->getParam('type');
            if ($apiType && in_array($apiType, Mage_Api2_Model_Server::getApiTypes())) {
                // Set matched path to empty string to avoid null in Router.php line 92
                $this->setMatchedPath('');
                // Merge with defaults
                return ['api_type' => $apiType] + $this->getDefaults();
            }
        }

        return $result;
    }
}
