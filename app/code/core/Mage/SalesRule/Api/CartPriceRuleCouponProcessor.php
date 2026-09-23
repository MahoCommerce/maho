<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CartPriceRuleCouponProcessor extends \Maho\ApiPlatform\Processor
{
    public const MAX_GENERATE_QTY = 1000;
    public const MAX_CODE_LENGTH = 32;
    public const MAX_AFFIX_LENGTH = 32;
    public const MAX_DELETE_IDS = 1000;

    private const GENERATE_FIELDS = ['qty', 'length', 'format', 'prefix', 'suffix', 'dash'];

    public function __construct(
        Security $security,
        private readonly CartPriceRuleProvider $ruleProvider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?JsonResponse
    {
        $user = $this->requireUser();
        $rule = $this->ruleProvider->loadRule((int) ($uriVariables['ruleId'] ?? 0));
        $this->ruleProvider->assertRuleReadable($rule, $user);
        $this->assertAllWebsitesAllowed((array) $rule->getWebsiteIds(), $user, 'cart price rule');

        return match ($operation->getName()) {
            'cart_price_rule_coupons_generate' => $this->generate($rule, $this->parseRequestBody($context['request'] ?? null), $user),
            'cart_price_rule_coupons_mass_delete' => $this->massDelete($rule, $this->parseRequestBody($context['request'] ?? null), $user),
            default => $this->deleteOne($rule, (int) ($uriVariables['couponId'] ?? 0), $user),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function generate(\Mage_SalesRule_Model_Rule $rule, array $body, ApiUser $user): JsonResponse
    {
        if (CartPriceRuleProvider::couponTypeOf($rule) !== CartPriceRule::COUPON_TYPE_AUTO) {
            throw new ConflictHttpException('Only a rule with couponType "auto" can generate coupons');
        }

        $settings = $this->readGenerateSettings($body);
        $data = $settings + [
            'rule_id' => (int) $rule->getId(),
            'uses_per_coupon' => $rule->getUsesPerCoupon() ?: null,
            'uses_per_customer' => $rule->getUsesPerCustomer() ?: null,
            'to_date' => $rule->getToDate(),
        ];

        // A new instance, because the singleton of the rule keeps the settings of an earlier generation
        /** @var \Mage_SalesRule_Model_Coupon_Massgenerator $generator */
        $generator = \Mage::getModel('salesrule/coupon_massgenerator');
        if (!$generator->validateData($data)) {
            throw new ValidationException('The generation settings are not valid', 'qty');
        }
        $generator->setData($data);

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            $generator->generatePool();
            $adapter->commit();
        } catch (\Mage_Core_Exception $e) {
            $adapter->rollBack();
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (\Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }

        $count = $generator->getGeneratedCount();
        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/coupon_collection');
        $collection->addRuleToFilter($rule)->addGeneratedCouponsFilter();
        $collection->getSelect()->order('main_table.coupon_id DESC')->limit($count);

        $coupons = [];
        foreach ($collection as $coupon) {
            $coupons[] = CartPriceRuleCoupon::fromCoupon($coupon)->toArray();
        }
        $this->logApiActivity('cart_price_rule', 'generate_coupons', null, $rule, $user);

        return $this->respondRaw(['generatedCount' => $count, 'coupons' => $coupons], JsonResponse::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{qty: int, length: int, format: string, prefix: string, suffix: string, dash: int}
     */
    private function readGenerateSettings(array $body): array
    {
        /** @var \Mage_SalesRule_Helper_Coupon $helper */
        $helper = \Mage::helper('salesrule/coupon');
        $formats = array_keys($helper->getFormatsList());
        $errors = [];

        foreach (array_keys($body) as $key) {
            if (!in_array($key, self::GENERATE_FIELDS, true)) {
                $errors[] = ['field' => (string) $key, 'message' => 'Unknown field'];
            }
        }

        $qty = $this->readInt($body['qty'] ?? null);
        if ($qty === null || $qty < 1 || $qty > self::MAX_GENERATE_QTY) {
            $errors[] = ['field' => 'qty', 'message' => sprintf('qty must be an integer from 1 to %d', self::MAX_GENERATE_QTY)];
        }

        $length = array_key_exists('length', $body) ? $this->readInt($body['length']) : max(1, (int) $helper->getDefaultLength());
        if ($length === null || $length < 1 || $length > self::MAX_CODE_LENGTH) {
            $errors[] = ['field' => 'length', 'message' => sprintf('length must be an integer from 1 to %d', self::MAX_CODE_LENGTH)];
        }

        $defaultFormat = (string) $helper->getDefaultFormat();
        $format = $body['format'] ?? (in_array($defaultFormat, $formats, true) ? $defaultFormat : \Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC);
        if (!is_string($format) || !in_array($format, $formats, true)) {
            $errors[] = ['field' => 'format', 'message' => 'format must be one of: ' . implode(', ', $formats)];
        }

        $affixes = [];
        foreach (['prefix' => (string) $helper->getDefaultPrefix(), 'suffix' => (string) $helper->getDefaultSuffix()] as $field => $default) {
            $affixes[$field] = $body[$field] ?? $default;
            if (!is_string($affixes[$field]) || !preg_match('/^[A-Za-z0-9_-]{0,' . self::MAX_AFFIX_LENGTH . '}$/', $affixes[$field])) {
                $errors[] = ['field' => $field, 'message' => sprintf('%s must have at most %d characters of A-Z, a-z, 0-9, _ and -', $field, self::MAX_AFFIX_LENGTH)];
            }
        }

        $dash = array_key_exists('dash', $body) ? $this->readInt($body['dash']) : (int) $helper->getDefaultDashInterval();
        if ($dash === null || $dash < 0 || ($length !== null && $dash > $length)) {
            $errors[] = ['field' => 'dash', 'message' => 'dash must be an integer from 0 to length'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors[0]['message'], $errors[0]['field'], 'Invalid', ['errors' => $errors]);
        }

        return [
            'qty' => (int) $qty,
            'length' => (int) $length,
            'format' => (string) $format,
            'prefix' => (string) $affixes['prefix'],
            'suffix' => (string) $affixes['suffix'],
            'dash' => (int) $dash,
        ];
    }

    private function deleteOne(\Mage_SalesRule_Model_Rule $rule, int $couponId, ApiUser $user): null
    {
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon')->load($couponId);
        if (!$coupon->getId() || (int) $coupon->getRuleId() !== (int) $rule->getId()) {
            throw new NotFoundHttpException('Coupon not found');
        }
        if ($coupon->getIsPrimary()) {
            throw new ConflictHttpException('The primary coupon changes only with the couponCode or the couponType of the rule');
        }

        $oldData = $coupon->getData();
        $this->safeDelete($coupon, 'delete coupon');
        $this->logApiActivity('coupon', 'delete', $oldData, null, $user);
        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function massDelete(\Mage_SalesRule_Model_Rule $rule, array $body, ApiUser $user): JsonResponse
    {
        $ids = $body['ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids) || $ids === [] || count($ids) > self::MAX_DELETE_IDS) {
            throw new ValidationException(sprintf('ids must be a list of 1 to %d coupon IDs', self::MAX_DELETE_IDS), 'ids');
        }
        $couponIds = [];
        foreach ($ids as $index => $id) {
            $couponId = $this->readInt($id);
            if ($couponId === null || $couponId < 1) {
                throw new ValidationException('Each ID must be a positive integer', "ids[{$index}]");
            }
            $couponIds[] = $couponId;
        }

        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $deleted = $adapter->delete($resource->getTableName('salesrule/coupon'), [
            'rule_id = ?' => (int) $rule->getId(),
            'coupon_id IN (?)' => array_values(array_unique($couponIds)),
            '(is_primary IS NULL OR is_primary = 0)',
        ]);
        $this->logApiActivity('cart_price_rule', 'delete_coupons', null, $rule, $user);

        return $this->respondRaw(['deletedCount' => (int) $deleted]);
    }

    private function readInt(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? null : $number;
    }
}
