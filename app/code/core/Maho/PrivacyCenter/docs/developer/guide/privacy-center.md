---
description: Add the data of your module to the GDPR export and erasure of the Privacy Center, with an exporter, an eraser and the events of an erasure.
---

# Privacy Center <span class="version-badge">v27.1+</span>

The Privacy Center collects the personal data of a customer from a list of exporters, and erases it with a list of erasers. A module that keeps personal data adds one of each, so that an export holds its data and an erasure removes it.

Maho loads an exporter or an eraser only when the Privacy Center runs it. So a module that declares them also works on a Maho version before 27.1, and when a store turns the Privacy Center off.

## The subject

Each exporter and each eraser gets a `Maho_PrivacyCenter_Model_Subject`:

- `customerId`: the customer account, or `null` for a guest.
- `email`: the email of the request. Use it to find the data of a guest.
- `storeId`: the store of the request.
- `isGuest()`: `true` when the request has no customer account.
- `getRowsCondition()`: the SQL condition that selects the rows of the customer and the guest rows of the email.
- `getOrderIds()`: the orders of the customer and the guest orders of the email.

## Add an exporter

An exporter extends `Maho_PrivacyCenter_Model_Export_AbstractExporter`. `getLabel()` is the title of its section in `data.html`, and the **My Personal Data** page of the customer account lists it. `export()` returns a list of records, or one record. A value is a scalar, `null` or an array of the same shape. `clean()` removes the fields that hold a secret, such as `password_hash`.

`clean()` knows only the secrets of the core. Leave out the secrets of your module yourself, such as a code that gives access to a value, and the data of other persons. The gift card exporter, for example, leaves out the code of each card, and the balance of a card that the customer sent to another person.

```php
class My_Module_Model_Privacy_Exporter extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('my_module')->__('Loyalty Points');
    }

    #[\Override]
    public function export(Maho_PrivacyCenter_Model_Subject $subject): array
    {
        $points = Mage::getResourceModel('my_module/points_collection');
        // The rows of the customer, and the guest rows of the email
        $points->getSelect()->where(
            $subject->getRowsCondition($points->getConnection(), 'main_table.customer_id', 'main_table.email'),
            $subject->email,
        );
        $data = [];
        foreach ($points as $row) {
            $data[] = $this->clean($row->getData());
        }
        return $data;
    }
}
```

Declare it in the `config.xml` of your module. The node name is the key of the section in `data.json`:

```xml
<global>
    <privacycenter>
        <exporters>
            <loyalty_points>my_module/privacy_exporter</loyalty_points>
        </exporters>
    </privacycenter>
</global>
```

## Add an eraser

An eraser extends `Maho_PrivacyCenter_Model_Eraser_AbstractEraser`. `getLabel()` tells what the eraser removes, and the **Delete My Account** page of the customer account lists it. `erase()` deletes or anonymizes the data, and returns one line for the history of the request. Declare it under `global/privacycenter/erasers`. The erasers run in the order of the configuration, and the `customer` eraser, which deletes the account, runs last.

Use the foreign keys of your tables where you can: a key on `customer_entity` with `ON DELETE CASCADE` deletes your rows with the account, and needs no eraser. Write an eraser for the data that a guest email names, and for the data that has no foreign key. The `customer` eraser runs last, so `customerId` still finds the rows of the account in your eraser.

```php
class My_Module_Model_Privacy_Eraser extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('my_module')->__('Your loyalty points');
    }

    #[\Override]
    public function erase(Maho_PrivacyCenter_Model_Subject $subject): string
    {
        $adapter = $this->getConnection();
        $count = $adapter->delete($this->getTable('my_module/points'), [
            $subject->getRowsCondition($adapter, 'customer_id', 'email') => $subject->email,
        ]);
        return Mage::helper('my_module')->__('%d loyalty point entries deleted', $count);
    }
}
```

```xml
<global>
    <privacycenter>
        <erasers>
            <loyalty_points>my_module/privacy_eraser</loyalty_points>
        </erasers>
    </privacycenter>
</global>
```

The customer reads the label on the **Delete My Account** page, so write it for the customer, such as "Your loyalty points". An administrator reads the line that `erase()` returns in the history of the request.

Keep the data that a law requires, such as an invoice, and tell it in the user documentation of your module. On a record that you keep, clear the fields that no law requires. The core erasers do the same: an order keeps the invoice data, but loses the IP address, the birth date and the gender.

To tell an external service, such as a mailing list, to delete the person, observe `privacy_after_erase`. The account no longer exists then, so read the email from the `request`.

## Events

| Event | Data | When |
|---|---|---|
| `privacy_collect_exporters` | `container` (`exporters` key) | Before an export. Add, replace or remove an exporter. |
| `privacy_collect_erasers` | `container` (`erasers` key) | Before an erasure. Add, replace or remove an eraser. |
| `privacy_before_erase` | `request`, `subject` | Before the first eraser runs. |
| `privacy_after_erase` | `request`, `customer_id` | After the last eraser ran. |
| `checkout_agreement_acceptance_save_commit_after` | `acceptance` | After the save of each accepted terms and conditions text. |

## The service

`Mage::getService('privacycenter/request')` holds the flow. The storefront, the admin and the API call the same methods: `submit()` for a guest, `submitForCustomer()`, `submitByStaff()`, `confirm()`, `approve()`, `reject()`, `cancel()`, `process()`, `export()` and `erase()`.

`Mage::getService('checkout/agreement')` holds the terms and conditions: `getActive()`, `assertAccepted()`, `recordAcceptances()`, `getPendingForCustomer()`, `acceptForCustomer()` and `getAcceptances()`. A checkout of your own that places an order through `Mage_Sales_Service_Order::submit()` with `$agreementIds` gets the acceptance log with no other code.
