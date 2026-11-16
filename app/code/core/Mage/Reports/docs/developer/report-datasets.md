---
description: How to add a data set, a metric, a grouping or a ready-made report to the Report Explorer, with a dataset class in config.xml or an observer of report_dataset_build.
---

# Report Explorer Data Sets

The Report Explorer builds each report from a data set. A data set names a table and declares
its fields, metrics, groupings, order scopes and ready-made reports (presets). The engine in
`lib/Maho/Report` turns a request into SQL for MySQL, PostgreSQL and SQLite. It reads live data,
so a new data set needs no aggregation table and no cron job.

## The parts of a data set

| Class | What it declares |
|-------|------------------|
| `Maho\Report\Definition\Dataset` | The code, the label, the group of its presets, the ACL resource, the table, its alias and the store column |
| `Field` | A SQL expression with a type (`FieldType`), the joins that it needs, and a `personal` flag |
| `Join` | A table, or a closure that returns a `Maho\Db\Select`, with its condition and the joins that it needs |
| `Metric` | An aggregate of a field (`Metric::aggregate()`), with an optional condition field, or a formula over other metrics (`Metric::formula()`) |
| `Dimension` | A grouping over a field. `Dataset::addDateField()` adds the `period` grouping |
| `Scope` | A named condition that the request picks, such as the order scopes `placed`, `not_canceled` and `all` |
| `Preset` | A ready-made report: a request array, a chart type and a description |
| `Records` | The fields of the record list behind a report, the ID field, the ACL resource and API permission of the records, and the admin route of one record |

A field with `personal: true` shows only to a caller who may manage customers. A field with
`localDate: true` already holds local days, so the engine does not convert its time zone.

A formula uses `+ - * /`, parentheses, numbers and metric codes. The engine calculates it after
the aggregation, on each row and on the totals. A division by 0 gives null.

A metric is additive when its total is the sum of its values in each group: a `Sum` or a `Count`,
or a formula that only adds and subtracts additive metrics. Only an additive metric takes a
monthly target, and only an additive metric gives each value a share of the change in an
explanation.

## Records

`Dataset::setRecords()` lets the page and the API list the records behind a report:

```php
$dataset->setRecords(new Records(
    ['increment_id', 'created_at', 'status', 'sales_total'],
    'order_id',
    'sales/order/actions/view',
    'orders',
    'adminhtml/sales_order/view',
    'order_id',
));
```

The arguments are the fields of each record, the field that holds its ID, the admin ACL resource
and the API permission that the records need, and the admin route and its parameter that open
one record. A data set without a page for one record passes null for the route. Each field must
be a field of the data set, and a personal field shows only to a caller who may manage customers.

## Add a data set

Write a class that implements `Maho\Report\DatasetProvider`:

```php
use Maho\Db\Adapter\AdapterInterface;
use Maho\Report\DatasetProvider;
use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\ChartType;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\Preset;

class My_Module_Model_Report_Dataset_Tickets implements DatasetProvider
{
    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('my_module');
        $table = Mage::getSingleton('core/resource')->getTableName('my_module/ticket');

        $dataset = new Dataset('tickets', $helper->__('Tickets'), $helper->__('Support'), 'report/tickets', $table, 'main', 'store_id');
        $dataset->addField(new Field('created_at', $helper->__('Opened'), FieldType::Date, 'main.created_at'));
        $dataset->addField(new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'));
        $dataset->addDateField('created_at', $helper->__('Opened'));
        $dataset->addMetric(Metric::aggregate('tickets', $helper->__('Tickets'), Aggregate::Count, null));
        $dataset->addPreset(new Preset('tickets_by_day', $helper->__('Tickets by day'), $helper->__('Support'), [
            'metrics' => ['tickets'],
            'dimensions' => ['period:day'],
            'range' => 'last_30_days',
        ], ChartType::Line));

        return $dataset;
    }
}
```

Declare it in the `config.xml` of the module:

```xml
<global>
    <reports>
        <datasets>
            <tickets>
                <class>my_module/report_dataset_tickets</class>
            </tickets>
        </datasets>
    </reports>
</global>
```

Flush the cache. The data set shows on **Reports > Explorer**, in the API and in the tools of
the admin assistant, for each administrator whose role allows its ACL resource.

The `labels` argument of a field names a label source of `Mage_Reports_Model_Explorer_Labels`,
such as `store`, `customer_group`, `country`, `product` or `payment_method`. The page and the API
then show a name next to each ID.

## Change a data set of another module

The registry dispatches `report_dataset_build` after it builds each data set. An observer can add
fields, metrics, groupings and presets, or remove them:

```php
#[Maho\Config\Observer('report_dataset_build')]
public function addLoyaltyPoints(\Maho\Event\Observer $observer): void
{
    $dataset = $observer->getEvent()->getData('dataset');
    if ($dataset->code !== 'sales_orders') {
        return;
    }
    $dataset->addField(new Field('points', 'Points', FieldType::Integer, 'COALESCE(main.loyalty_points, 0)'));
    $dataset->addMetric(Metric::aggregate('points', 'Points', Aggregate::Sum, 'points'));
}
```

Run `composer dump-autoload` after you add the observer attribute.

## Check the SQL

`./maho report:explain` prints the SQL of each statement of a report, with its time and its row
count. `./maho report:run` prints the report as a table, CSV or JSON:

```bash
./maho report:explain --preset=sales_by_payment_method --range=last_12_months
./maho report:run --request='{"dataset":"tickets","metrics":["tickets"],"dimensions":["period:week"]}'
```

Use the adapter helpers in a field expression, such as `getIfNullSql()` and `getCheckSql()`, so
that the SQL works on every database. The integration tests in
`tests/Backend/Integration/Reports/Explorer` show how to test a data set against hand-computed
values.
