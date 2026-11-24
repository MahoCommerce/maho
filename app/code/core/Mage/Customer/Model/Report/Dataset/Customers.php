<?php

/**
 * The customers dataset of the report explorer: one row per customer account, dated by the registration.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;
use Maho\Report\DatasetProvider;
use Maho\Report\Definition\Aggregate;
use Maho\Report\Definition\ChartType;
use Maho\Report\Definition\Dataset;
use Maho\Report\Definition\Dimension;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\FieldType;
use Maho\Report\Definition\Join;
use Maho\Report\Definition\JoinType;
use Maho\Report\Definition\Metric;
use Maho\Report\Definition\MetricFormat;
use Maho\Report\Definition\Preset;
use Maho\Report\Definition\Records;

class Mage_Customer_Model_Report_Dataset_Customers implements DatasetProvider
{
    public const string CODE = 'customers';

    #[\Override]
    public function build(AdapterInterface $adapter): Dataset
    {
        $helper = Mage::helper('customer');
        $resource = Mage::getSingleton('core/resource');

        $dataset = new Dataset(self::CODE, $helper->__('Customers'), 'customers', 'report/customers', $resource->getTableName('customer/entity'), 'main', 'store_id');

        $dataset->addField(new Field('email', $helper->__('Email'), FieldType::String, 'main.email', personal: true));

        $dataset->setRecords(new Records(['email', 'created_at', 'website_id', 'group_id', 'is_active'], 'customer_id', 'customer/manage', 'customers', 'adminhtml/customer/edit', 'id'));
        foreach ([
            new Field('created_at', $helper->__('Registration date'), FieldType::Date, 'main.created_at'),
            new Field('store_id', $helper->__('Store view'), FieldType::Id, 'main.store_id', labels: 'store'),
            new Field('website_id', $helper->__('Website'), FieldType::Id, 'main.website_id', labels: 'website'),
            new Field('group_id', $helper->__('Customer group'), FieldType::Id, 'main.group_id', labels: 'customer_group'),
            new Field('is_active', $helper->__('Active'), FieldType::Boolean, (string) $adapter->getIfNullSql('main.is_active', 0)),
            new Field('customer_id', $helper->__('Customer'), FieldType::Id, 'main.entity_id', personal: true, labels: 'customer'),
        ] as $field) {
            $dataset->addField($field);
        }
        $dataset->addDateField('created_at', $helper->__('Registration date'));
        $dataset->addMetric(Metric::aggregate('registrations', $helper->__('New accounts'), Aggregate::Count, null));
        foreach (['store_id' => $helper->__('Store view'), 'website_id' => $helper->__('Website'), 'group_id' => $helper->__('Customer group')] as $code => $label) {
            $dataset->addDimension(new Dimension($code, $label, $code));
        }

        $dataset->addPreset(new Preset('new_registrations', $helper->__('New accounts'), $helper->__('Customers'), [
            'metrics' => ['registrations'],
            'dimensions' => ['period:day'],
            'range' => 'last_30_days',
            'compare' => 'previous_period',
        ], ChartType::Line));

        return $dataset;
    }
}
