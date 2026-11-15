<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

class Mage_Rating_Service_Rating
{
    public const STARS = 5;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Rating_Model_Rating
    {
        $rating = Mage::getModel('rating/rating')->load($id);
        if (!$rating->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('rating')->__('Rating not found'));
        }
        return $rating;
    }

    /**
     * Save $rating. A rating with no options gets one option for each star: a customer votes through them.
     */
    public function save(Mage_Rating_Model_Rating $rating): Mage_Rating_Model_Rating
    {
        $rating->save();
        if (Mage::getResourceModel('rating/rating_option_collection')->addRatingFilter($rating->getId())->getSize() > 0) {
            return $rating;
        }
        for ($star = 1; $star <= self::STARS; $star++) {
            Mage::getModel('rating/rating_option')
                ->setRatingId((int) $rating->getId())
                ->setCode((string) $star)
                ->setValue($star)
                ->setPosition($star)
                ->save();
        }
        return $rating;
    }

    public function delete(Mage_Rating_Model_Rating $rating): void
    {
        $rating->delete();
    }
}
