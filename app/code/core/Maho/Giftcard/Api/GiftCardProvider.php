<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Giftcard\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Gift Card Provider — only needs the checkGiftcardBalance named query.
 *
 * All standard CRUD (get, list) is handled by CrudProvider + CrudResource.
 */
final class GiftCardProvider extends CrudProvider
{
    protected array $defaultSort = ['created_at' => 'DESC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'checkGiftcardBalance') {
            $code = $context['args']['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException('Gift card code is required');
            }

            $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode(trim($code));
            if (!$giftcard->getId()) {
                throw new \RuntimeException('Gift card "' . $code . '" not found');
            }

            return $this->toDto($giftcard);
        }

        return null;
    }
}
