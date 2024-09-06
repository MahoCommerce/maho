<?php
/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$cmsBlocks = [
    [
        'title'         => 'Footer Links',
        'identifier'    => 'footer_links',
        'content'       => '
<ul>
    <li><a href="{{store direct_url="about-magento-demo-store"}}">About Us</a></li>
    <li class="last"><a href="{{store direct_url="customer-service"}}">Customer Service</a></li>
</ul>',
        'is_active'     => 1,
        'stores'        => 0
    ]
];

$cmsPages = [
    [
        'title'         => '404 Not Found 1',
        'root_template' => 'two_columns_right',
        'meta_keywords' => 'Page keywords',
        'meta_description'
                        => 'Page description',
        'identifier'    => 'no-route',
        'content'       => '
<div class="page-title"><h1>Whoops, our bad...</h1></div>
<dl>
    <dt>The page you requested was not found, and we have a fine guess why.</dt>
    <dd>
        <ul class="disc">
            <li>If you typed the URL directly, please make sure the spelling is correct.</li>
            <li>If you clicked on a link to get here, the link is outdated.</li>
        </ul>
    </dd>
</dl>
<dl>
    <dt>What can you do?</dt>
    <dd>Have no fear, help is near! There are many ways you can get back on track with Magento Store.</dd>
    <dd>
        <ul class="disc">
            <li><a href="#" onclick="history.go(-1); return false;">Go back</a> to the previous page.</li>
            <li>Use the search bar at the top of the page to search for your products.</li>
            <li>Follow these links to get you back on track!<br /><a href="{{store url=""}}">Store Home</a>
            <span class="separator">|</span> <a href="{{store url="customer/account"}}">My Account</a></li>
        </ul>
    </dd>
</dl>
',
        'is_active'     => 1,
        'stores'        => [0],
        'sort_order'    => 0
    ],
    [
        'title'         => 'Home page',
        'root_template' => 'two_columns_right',
        'identifier'    => 'home',
        'content'       => '<div class="page-title"><h2>Home Page</h2></div>',
        'is_active'     => 1,
        'stores'        => [0],
        'sort_order'    => 0
    ],
    [
        'title'         => 'About Us',
        'root_template' => 'two_columns_right',
        'identifier'    => 'about-magento-demo-store',
        'content'       => '
<div class="page-title">
    <h1>About Magento Store</h1>
</div>
<div class="col3-set">
<div class="col-1"><p style="line-height:1.2em;"><small>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
Morbi luctus. Duis lobortis. Nulla nec velit. Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec,
tempus vitae, iaculis semper, pede.</small></p>
<p style="color:#888; font:1.2em/1.4em georgia, serif;">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
Morbi luctus. Duis lobortis. Nulla nec velit. Mauris pulvinar erat non massa. Suspendisse tortor turpis,
porta nec, tempus vitae, iaculis semper, pede. Cras vel libero id lectus rhoncus porta.</p></div>
<div class="col-2">
<p><strong style="color:#de036f;">Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus.
Duis lobortis. Nulla nec velit.</strong></p>
<p>Vivamus tortor nisl, lobortis in, faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper.
Phasellus id massa. Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada
fames ac turpis egestas. Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac,
tempus nec, tempor nec, justo. </p>
<p>Maecenas ullamcorper, odio vel tempus egestas, dui orci faucibus orci, sit amet aliquet lectus dolor et quam.
Pellentesque consequat luctus purus. Nunc et risus. Etiam a nibh. Phasellus dignissim metus eget nisi.
Vestibulum sapien dolor, aliquet nec, porta ac, malesuada a, libero. Praesent feugiat purus eget est.
Nulla facilisi. Vestibulum tincidunt sapien eu velit. Mauris purus. Maecenas eget mauris eu orci accumsan feugiat.
Pellentesque eget velit. Nunc tincidunt.</p></div>
<div class="col-3">
<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper </p>
<p><strong style="color:#de036f;">Maecenas ullamcorper, odio vel tempus egestas, dui orci faucibus orci,
sit amet aliquet lectus dolor et quam. Pellentesque consequat luctus purus.</strong></p>
<p>Nunc et risus. Etiam a nibh. Phasellus dignissim metus eget nisi.</p>
<div class="divider"></div>
<p>To all of you, from all of us at Magento Store - Thank you and Happy eCommerce!</p>
<p style="line-height:1.2em;"><strong style="font:italic 2em Georgia, serif;">John Doe</strong><br />
<small>Some important guy</small></p></div>
</div>',
        'is_active'     => 1,
        'stores'        => [0],
        'sort_order'    => 0
    ],
    [
        'title'         => 'Customer Service',
        'root_template' => 'three_columns',
        'identifier'    => 'customer-service',
        'content'       => '<div class="page-title">
<h1>Customer Service</h1>
</div>
<ul class="disc">
<li><a href="#answer1">Shipping &amp; Delivery</a></li>
<li><a href="#answer2">Privacy &amp; Security</a></li>
<li><a href="#answer3">Returns &amp; Replacements</a></li>
<li><a href="#answer4">Ordering</a></li>
<li><a href="#answer5">Payment, Pricing &amp; Promotions</a></li>
<li><a href="#answer6">Viewing Orders</a></li>
<li><a href="#answer7">Updating Account Information</a></li>
</ul>
<dl>
<dt id="answer1">Shipping &amp; Delivery</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
justo.</dd>
<dt id="answer2">Privacy &amp; Security</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
justo.</dd>
<dt id="answer3">Returns &amp; Replacements</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
justo.</dd>
<dt id="answer4">Ordering</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
justo.</dd>
<dt id="answer5">Payment, Pricing &amp; Promotions</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
justo.</dd>
<dt id="answer6">Viewing Orders</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
 Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
 Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
 justo.</dd>
<dt id="answer7">Updating Account Information</dt>
<dd>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Morbi luctus. Duis lobortis. Nulla nec velit.
 Mauris pulvinar erat non massa. Suspendisse tortor turpis, porta nec, tempus vitae, iaculis semper, pede.
 Cras vel libero id lectus rhoncus porta. Suspendisse convallis felis ac enim. Vivamus tortor nisl, lobortis in,
 faucibus et, tempus at, dui. Nunc risus. Proin scelerisque augue. Nam ullamcorper. Phasellus id massa.
 Pellentesque nisl. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.
 Nunc augue. Aenean sed justo non leo vehicula laoreet. Praesent ipsum libero, auctor ac, tempus nec, tempor nec,
 justo.</dd>
</dl>',
        'is_active'     => 1,
        'stores'        => [0],
        'sort_order'    => 0
    ],
    [
        'title'         => 'Enable Cookies',
        'root_template' => 'one_column',
        'identifier'    => 'enable-cookies',
        'content'       => '<div class="std">
    <ul class="messages">
        <li class="notice-msg">
            <ul>
                <li>Please enable cookies in your web browser to continue.</li>
            </ul>
        </li>
    </ul>
    <div class="page-title">
        <h1><a name="top"></a>What are Cookies?</h1>
    </div>
    <p>Cookies are short pieces of data that are sent to your computer when you visit a website.
    On later visits, this data is then returned to that website. Cookies allow us to recognize you automatically
    whenever you visit our site so that we can personalize your experience and provide you with better service.
    We also use cookies (and similar browser data, such as Flash cookies) for fraud prevention and other purposes.
     If your web browser is set to refuse cookies from our website, you will not be able to complete a purchase
     or take advantage of certain features of our website, such as storing items in your Shopping Cart or
     receiving personalized recommendations. As a result, we strongly encourage you to configure your web
     browser to accept cookies from our website.</p>
</div>
',
        'is_active'     => 1,
        'stores'        => [0]
    ]
];

/**
 * Insert default blocks
 */
foreach ($cmsBlocks as $data) {
    Mage::getModel('cms/block')->setData($data)->save();
}

/**
 * Insert default and system pages
 */
foreach ($cmsPages as $data) {
    Mage::getModel('cms/page')->setData($data)->save();
}

$content = '
<div class="links">
    <div class="block-title">
        <strong><span>Company</span></strong>
    </div>
    <ul>
        <li><a href="{{store url=""}}about-magento-demo-store/">About Us</a></li>
        <li><a href="{{store url=""}}contacts/">Contact Us</a></li>
        <li><a href="{{store url=""}}customer-service/">Customer Service</a></li>
        <li><a href="{{store url=""}}privacy-policy-cookie-restriction-mode/">Privacy Policy</a></li>
    </ul>
</div>';

$cmsBlock = [
    'title'         => 'Footer Links Company',
    'identifier'    => 'footer_links_company',
    'content'       => $content,
    'is_active'     => 1,
    'stores'        => 0
];

Mage::getModel('cms/block')->setData($cmsBlock)->save();
