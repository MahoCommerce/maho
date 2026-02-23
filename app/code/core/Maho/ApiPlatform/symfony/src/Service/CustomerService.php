<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Customer Service - Business logic for customer operations
 */
class CustomerService
{
    /**
     * Authenticate customer with email and password
     */
    public function authenticate(#[\SensitiveParameter]
        string $email, #[\SensitiveParameter]
        string $password): ?\Mage_Customer_Model_Customer
    {
        try {
            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);

            if (!$customer->getId()) {
                return null;
            }

            // Validate password
            if (!$customer->validatePassword($password)) {
                return null;
            }

            return $customer;
        } catch (\Exception $e) {
            \Mage::logException($e);
            return null;
        }
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $id): ?\Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')->load($id);

        return $customer->getId() ? $customer : null;
    }

    /**
     * Get customer by email
     */
    public function getCustomerByEmail(#[\SensitiveParameter]
        string $email): ?\Mage_Customer_Model_Customer
    {
        $customer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($email);

        return $customer->getId() ? $customer : null;
    }

    /**
     * Search customers using optimized direct SQL
     * Smart detection: @ = email, digits = phone, otherwise = name
     */
    public function searchCustomers(
        string $search = '',
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $telephone = null,
        int $page = 1,
        int $pageSize = 20,
    ): array {
        $search = trim($search);

        // Minimum search length validation (unless explicit email/telephone filter)
        $hasAtSign = str_contains($search, '@');
        if (empty($email) && empty($telephone) && !empty($search)) {
            // Need at least 5 chars, OR contains @ (email indicator)
            if (strlen($search) < 5 && !$hasAtSign) {
                return ['customers' => [], 'total' => 0];
            }
        }

        // Smart search type detection from general search
        if (!empty($search) && empty($email) && empty($telephone)) {
            if ($hasAtSign) {
                // Contains @ - treat as email search
                $email = $search;
                $search = '';
            } elseif (preg_match('/^[\d\s\-\+\(\)]{5,}$/', $search)) {
                // Looks like a phone number (5+ digits/spaces/dashes)
                $telephone = preg_replace('/[^\d]/', '', $search); // Strip non-digits
                $search = '';
            }
            // Otherwise: general search (name + email + phone)
        }

        // Use optimized SQL search for better performance
        $customerIds = $this->searchCustomerIdsFast($search, $email, $telephone, $page, $pageSize);

        if (empty($customerIds['ids'])) {
            return [
                'customers' => [],
                'total' => 0,
            ];
        }

        // Load full customer models only for the paginated results
        $collection = \Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect(['firstname', 'lastname', 'email', 'default_billing', 'group_id'])
            ->addFieldToFilter('entity_id', ['in' => $customerIds['ids']]);

        // Build a map by ID for ordering
        $customerMap = [];
        foreach ($collection as $customer) {
            $customerMap[(int) $customer->getId()] = $customer;
        }

        // Return in the order from search results
        $customers = [];
        foreach ($customerIds['ids'] as $id) {
            if (isset($customerMap[(int) $id])) {
                $customers[] = $customerMap[(int) $id];
            }
        }

        return [
            'customers' => $customers,
            'total' => $customerIds['total'],
        ];
    }

    /**
     * Fast customer ID search using direct SQL
     * Returns customer IDs matching the search criteria
     */
    private function searchCustomerIdsFast(
        string $search,
        #[\SensitiveParameter]
        ?string $email,
        ?string $telephone,
        int $page,
        int $pageSize,
    ): array {
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');

        // Get EAV attribute IDs for customer - cast to int for security
        $eavConfig = \Mage::getSingleton('eav/config');
        $firstnameAttr = $eavConfig->getAttribute('customer', 'firstname');
        $lastnameAttr = $eavConfig->getAttribute('customer', 'lastname');
        $telephoneAttr = $eavConfig->getAttribute('customer_address', 'telephone');

        // Validate required attributes exist
        if (!$firstnameAttr || !$lastnameAttr || !$telephoneAttr) {
            \Mage::log('CustomerService: Required EAV attributes not found');
            return ['ids' => [], 'total' => 0];
        }

        // Cast attribute IDs to integers for SQL safety
        $firstnameAttrId = (int) $firstnameAttr->getId();
        $lastnameAttrId = (int) $lastnameAttr->getId();
        $telephoneAttrId = (int) $telephoneAttr->getId();

        $customerTable = $resource->getTableName('customer/entity');
        $customerVarcharTable = $resource->getTableName('customer_entity_varchar');
        $addressTable = $resource->getTableName('customer/address_entity');
        $addressVarcharTable = $resource->getTableName('customer_address_entity_varchar');

        // Ensure pagination values are safe integers
        $pageSize = max(1, min((int) $pageSize, 30)); // Limit to 30 max for performance
        $page = max(1, (int) $page);
        $offset = ($page - 1) * $pageSize;

        // Build query based on search type
        if ($telephone !== null && !empty($telephone)) {
            // Phone search - use trailing wildcard only (digits already stripped by caller)
            $telephoneSafe = $read->quote($telephone . '%');

            $sql = "
                SELECT DISTINCT c.entity_id
                FROM {$customerTable} c
                INNER JOIN {$addressTable} a ON a.parent_id = c.entity_id
                INNER JOIN {$addressVarcharTable} av_tel ON av_tel.entity_id = a.entity_id
                    AND av_tel.attribute_id = {$telephoneAttrId}
                WHERE av_tel.value LIKE {$telephoneSafe}
                ORDER BY c.entity_id DESC
                LIMIT {$pageSize} OFFSET {$offset}
            ";

            $countSql = "
                SELECT COUNT(DISTINCT c.entity_id)
                FROM {$customerTable} c
                INNER JOIN {$addressTable} a ON a.parent_id = c.entity_id
                INNER JOIN {$addressVarcharTable} av_tel ON av_tel.entity_id = a.entity_id
                    AND av_tel.attribute_id = {$telephoneAttrId}
                WHERE av_tel.value LIKE {$telephoneSafe}
            ";
        } elseif ($email !== null && !empty($email)) {
            // Email search - use exact match to leverage index
            $emailSafe = $read->quote($email);

            $sql = "
                SELECT c.entity_id
                FROM {$customerTable} c
                WHERE c.email = {$emailSafe}
                ORDER BY c.entity_id DESC
                LIMIT {$pageSize} OFFSET {$offset}
            ";

            $countSql = "
                SELECT COUNT(*)
                FROM {$customerTable} c
                WHERE c.email = {$emailSafe}
            ";
        } elseif (!empty($search)) {
            // General search - name, email, or phone
            // Use trailing wildcard only (search%) to allow index usage
            $searchSafe = $read->quote($search . '%');

            // Use UNION to combine results from different search paths
            $sql = "
                SELECT DISTINCT customer_id FROM (
                    SELECT c.entity_id as customer_id
                    FROM {$customerTable} c
                    WHERE c.email LIKE {$searchSafe}

                    UNION

                    SELECT cv.entity_id as customer_id
                    FROM {$customerVarcharTable} cv
                    WHERE cv.attribute_id = {$firstnameAttrId}
                    AND cv.value LIKE {$searchSafe}

                    UNION

                    SELECT cv.entity_id as customer_id
                    FROM {$customerVarcharTable} cv
                    WHERE cv.attribute_id = {$lastnameAttrId}
                    AND cv.value LIKE {$searchSafe}

                    UNION

                    SELECT a.parent_id as customer_id
                    FROM {$addressTable} a
                    INNER JOIN {$addressVarcharTable} av ON av.entity_id = a.entity_id
                        AND av.attribute_id = {$telephoneAttrId}
                    WHERE av.value LIKE {$searchSafe}
                ) AS combined
                ORDER BY customer_id DESC
                LIMIT {$pageSize} OFFSET {$offset}
            ";

            $countSql = "
                SELECT COUNT(DISTINCT customer_id) FROM (
                    SELECT c.entity_id as customer_id
                    FROM {$customerTable} c
                    WHERE c.email LIKE {$searchSafe}

                    UNION

                    SELECT cv.entity_id as customer_id
                    FROM {$customerVarcharTable} cv
                    WHERE cv.attribute_id = {$firstnameAttrId}
                    AND cv.value LIKE {$searchSafe}

                    UNION

                    SELECT cv.entity_id as customer_id
                    FROM {$customerVarcharTable} cv
                    WHERE cv.attribute_id = {$lastnameAttrId}
                    AND cv.value LIKE {$searchSafe}

                    UNION

                    SELECT a.parent_id as customer_id
                    FROM {$addressTable} a
                    INNER JOIN {$addressVarcharTable} av ON av.entity_id = a.entity_id
                        AND av.attribute_id = {$telephoneAttrId}
                    WHERE av.value LIKE {$searchSafe}
                ) AS combined
            ";
        } else {
            // No search criteria - return recent customers
            $sql = "
                SELECT entity_id
                FROM {$customerTable}
                ORDER BY entity_id DESC
                LIMIT {$pageSize} OFFSET {$offset}
            ";

            $countSql = "SELECT COUNT(*) FROM {$customerTable}";
        }

        $ids = $read->fetchCol($sql);
        $total = (int) $read->fetchOne($countSql);

        return [
            'ids' => $ids,
            'total' => $total,
        ];
    }

    /**
     * Create customer with minimal information (for POS quick checkout)
     */
    public function createCustomerQuick(
        string $firstName,
        string $lastName,
        #[\SensitiveParameter]
        ?string $email = null,
        ?string $telephone = null,
        ?int $groupId = null,
    ): \Mage_Customer_Model_Customer {
        $customer = \Mage::getModel('customer/customer');

        // If no email provided, generate a temporary one
        if (empty($email)) {
            $email = 'guest_' . time() . '_' . random_int(1000, 9999) . '@pos.local';
        }

        $customer->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->setStore(\Mage::app()->getStore())
            ->setFirstname($firstName)
            ->setLastname($lastName)
            ->setEmail($email);

        if ($groupId !== null) {
            $customer->setGroupId($groupId);
        }

        // Save customer
        $customer->save();

        // Add default address if telephone provided
        if ($telephone) {
            $address = \Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId())
                ->setFirstname($firstName)
                ->setLastname($lastName)
                ->setTelephone($telephone)
                ->setCountryId('AU') // Default to Australia
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true);

            try {
                $address->save();
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        return $customer;
    }

    /**
     * Register new customer with full information
     */
    public function registerCustomer(
        string $firstName,
        string $lastName,
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        bool $isSubscribed = false,
    ): \Mage_Customer_Model_Customer {
        // Check if email already exists
        if ($this->getCustomerByEmail($email)) {
            throw new \Exception('A customer with this email already exists.');
        }

        $customer = \Mage::getModel('customer/customer');

        $customer->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->setStore(\Mage::app()->getStore())
            ->setFirstname($firstName)
            ->setLastname($lastName)
            ->setEmail($email)
            ->setPassword($password)
            ->setIsSubscribed($isSubscribed);

        $customer->save();

        return $customer;
    }

    /**
     * Update customer information
     */
    public function updateCustomer(
        \Mage_Customer_Model_Customer $customer,
        array $data,
    ): \Mage_Customer_Model_Customer {
        if (isset($data['firstName'])) {
            $customer->setFirstname($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $customer->setLastname($data['lastName']);
        }

        if (isset($data['email'])) {
            // Check if email is already used by another customer
            $existing = $this->getCustomerByEmail($data['email']);
            if ($existing && $existing->getId() !== $customer->getId()) {
                throw new \Exception('This email is already in use.');
            }
            $customer->setEmail($data['email']);
        }

        if (isset($data['isSubscribed'])) {
            $customer->setIsSubscribed($data['isSubscribed']);
        }

        $customer->save();

        return $customer;
    }

    /**
     * Change customer password
     */
    public function changePassword(
        \Mage_Customer_Model_Customer $customer,
        string $currentPassword,
        string $newPassword,
    ): bool {
        // Validate current password
        if (!$customer->validatePassword($currentPassword)) {
            throw new \Exception('Current password is incorrect.');
        }

        $customer->setPassword($newPassword);
        $customer->save();

        return true;
    }

    /**
     * Request password reset token
     */
    public function requestPasswordReset(#[\SensitiveParameter]
        string $email): bool
    {
        $customer = $this->getCustomerByEmail($email);

        if (!$customer) {
            // Don't reveal if email exists or not (security)
            return true;
        }

        try {
            $customer->sendPasswordResetConfirmationEmail();
            return true;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new \Exception('Unable to send password reset email.');
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword(#[\SensitiveParameter]
        string $email, string $token, string $newPassword): bool
    {
        $customer = $this->getCustomerByEmail($email);

        if (!$customer) {
            throw new \Exception('Invalid email or token.');
        }

        // Validate reset token (use hash_equals to prevent timing attacks)
        $storedToken = $customer->getRpToken();
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            throw new \Exception('Invalid or expired reset token.');
        }

        // Check token expiration using configurable duration (default 24 hours)
        $expiryHours = (int) \Mage::getStoreConfig('customer/password/reset_link_expiration_period') ?: 24;
        $tokenCreatedAt = strtotime($customer->getRpTokenCreatedAt());
        if ((time() - $tokenCreatedAt) > ($expiryHours * 3600)) {
            throw new \Exception('Reset token has expired.');
        }

        // Set new password and clear token
        $customer->setPassword($newPassword);
        $customer->setRpToken('');
        $customer->setRpTokenCreatedAt('');
        $customer->save();

        return true;
    }
}
