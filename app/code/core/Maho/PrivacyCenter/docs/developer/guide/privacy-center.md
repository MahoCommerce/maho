---
description: Add the data of your module to the GDPR export and erasure of the Privacy Center, with an exporter, an eraser and the events of an erasure.
---

# Privacy Center <span class="version-badge">v27.1+</span>

The Privacy Center collects the personal data of a customer from a list of exporters, and erases it with a list of erasers. A module that keeps personal data adds one of each, so that an export holds its data and an erasure removes it.

## The subject

Each exporter and each eraser gets a `Maho_PrivacyCenter_Model_Subject`:

- `customerId`: the customer account, or `null` for a guest.
- `email`: the email of the request. Use it to find the data of a guest.
- `storeId`: the store of the request.

## Add an exporter

An exporter extends `Maho_PrivacyCenter_Model_Export_AbstractExporter`. `getLabel()` is the title of its section in `data.html`, and the **My Personal Data** page of the customer account lists it. `export()` returns a list of records, or one record. A value is a scalar, `null` or an array of the same shape. `clean()` removes the fields that hold a secret, such as `password_hash`.

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
        if ($subject->isGuest()) {
            return [];
        }
        $data = [];
        foreach (Mage::getResourceModel('my_module/points_collection')->addFieldToFilter('customer_id', $subject->customerId) as $row) {
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

Use the foreign keys of your tables where you can: a key on `customer_entity` with `ON DELETE CASCADE` deletes your rows with the account, and needs no eraser. Write an eraser for the data that a guest email names, and for the data that has no foreign key.

Keep the data that a law requires, such as an invoice, and tell it in the user documentation of your module.

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
