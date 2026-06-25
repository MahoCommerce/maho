<?php

/**
 * Controller fixtures for ControllerOverrideTest — inheritance shapes the compiler reflects.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

// Route-owning base controller (the class an override targets).
class Test_Override_BaseController {}

// Simple override: extends the base, declares no route of its own.
class Test_Override_ChildController extends Test_Override_BaseController {}

// Chained override: extends another override → deepest in the chain.
class Test_Override_GrandchildController extends Test_Override_ChildController {}

// Abstract link in the chain — must be skipped as a candidate, but a concrete
// descendant still resolves the base as its nearest route-owning ancestor.
abstract class Test_Override_AbstractController extends Test_Override_BaseController {}
class Test_Override_ConcreteController extends Test_Override_AbstractController {}

// Two independent siblings extending the base → genuine cross-module conflict.
class Test_Override_SiblingAController extends Test_Override_BaseController {}
class Test_Override_SiblingBController extends Test_Override_BaseController {}

// Unrelated controller — not a subclass of the base, never an override.
class Test_Override_UnrelatedController {}

// Stands in for a third-party XML `<args><modules>` override of Mage_Checkout_CartController.
// The dispatcher builds this class name as `<module>_<Controller>Controller`, so the module
// node must be "Fixture_Xml" for frontName/controller "checkout"/"cart".
class Fixture_Xml_CartController extends Mage_Checkout_CartController {}

// Override of a real routed controller that adds a brand-new, un-routed action — the
// migrator must refuse to drop the XML for it (the new action would 404 without a route).
class Fixture_NewAction_CartController extends Mage_Checkout_CartController
{
    public function brandNewAction(): void {}
}

// Two clean sibling overrides of the same routed core controller, living in *separate*
// modules — stand-ins for a cross-file sibling conflict the migrator's pre-pass must detect
// (each module looks clean in isolation; only the global view reveals the clash).
class Fixture_SiblingX_CartController extends Mage_Checkout_CartController {}
class Fixture_SiblingY_CartController extends Mage_Checkout_CartController {}
