<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 */
final class ProductTierPriceProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ProductTierPriceProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDeleteAll($productId);
        }

        $this->requirePermission($user, 'products/write');
        return $this->handleReplace($productId, $context);
    }

    private function handleReplace(int $productId, array $context): array
    {
        $product = $this->loadProduct($productId);

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if (!is_array($body)) {
            throw new BadRequestHttpException('Request body must be an array of tier prices');
        }

        $tierPrices = [];
        foreach ($body as $tp) {
            if (!is_array($tp)) {
                throw new BadRequestHttpException('Each tier price must be an object');
            }

            $groupId = $tp['customerGroupId'] ?? $tp['customer_group_id'] ?? 'all';
            if ($groupId === 'all') {
                $groupId = \Mage_Customer_Model_Group::CUST_GROUP_ALL;
            }

            $price = (float) ($tp['price'] ?? 0);
            if ($price < 0) {
                throw new BadRequestHttpException('Price must not be negative');
            }

            $qty = (float) ($tp['qty'] ?? 1);
            if ($qty <= 0) {
                throw new BadRequestHttpException('Quantity must be greater than 0');
            }

            $tierPrices[] = [
                'website_id' => (int) ($tp['websiteId'] ?? $tp['website_id'] ?? 0),
                'cust_group' => (int) $groupId,
                'price_qty' => $qty,
                'price' => $price,
            ];
        }

        $product->getTierPrice();
        $product->setTierPrice($tierPrices);

        $this->safeSave($product, 'save tier prices');

        // Re-read and return
        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId],
            [],
        );
    }

    private function handleDeleteAll(int $productId): null
    {
        $product = $this->loadProduct($productId);
        $product->getTierPrice();
        $product->setTierPrice([]);
        $this->safeSave($product, 'delete tier prices');

        return null;
    }

}
