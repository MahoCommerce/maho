<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Post_View extends Mage_Core_Block_Template
{
    public function getPost(): ?Maho_Blog_Model_Post
    {
        return Mage::registry('current_blog_post');
    }
}
