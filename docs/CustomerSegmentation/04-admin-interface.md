# Maho Customer Segmentation - Admin Interface Design

## Overview

The admin interface provides comprehensive tools for creating, managing, and analyzing customer segments. The design follows Maho's existing admin panel conventions while introducing modern UI patterns for complex condition building and data visualization.

## Navigation Structure

### Menu Integration
```
Customers (Main Menu)
├── All Customers
├── Customer Groups
├── Customer Segments (NEW)
│   ├── Manage Segments
│   ├── Create New Segment
│   └── Performance Reports
└── Now Online
```

## Core Interface Components

### 1. Segment Grid (List View)

#### Grid Columns
- **Segment Name** (with edit link)
- **Status** (Active/Inactive indicator)
- **Customer Count** (with refresh button)
- **Website(s)** (multi-select display)
- **Last Updated** (timestamp)
- **Actions** (Edit, Delete, Duplicate, Refresh)

#### Grid Features
```php
// Grid configuration
$this->addColumn('name', [
    'header' => Mage::helper('customersegmentation')->__('Segment Name'),
    'index' => 'name',
    'type' => 'text',
    'width' => '200px'
]);

$this->addColumn('is_active', [
    'header' => Mage::helper('customersegmentation')->__('Status'),
    'index' => 'is_active',
    'type' => 'options',
    'options' => [1 => 'Active', 0 => 'Inactive'],
    'renderer' => 'customersegmentation/adminhtml_grid_renderer_status'
]);

$this->addColumn('matched_customers_count', [
    'header' => Mage::helper('customersegmentation')->__('Customers'),
    'index' => 'matched_customers_count',
    'type' => 'number',
    'width' => '100px',
    'renderer' => 'customersegmentation/adminhtml_grid_renderer_count'
]);
```

#### Mass Actions
- Delete selected segments
- Enable/Disable segments
- Refresh selected segments

### 2. Segment Edit Form

#### Form Structure
```
┌─ General Information Tab ─────────────────────────────────┐
│  • Segment Name                                           │
│  • Description                                            │
│  • Status (Active/Inactive)                              │
│  • Website Selection                                      │
│  • Customer Groups                                       │
│  • Priority                                              │
└───────────────────────────────────────────────────────────┘

┌─ Conditions Tab ──────────────────────────────────────────┐
│  • Condition Builder (drag & drop interface)             │
│  • Real-time customer count preview                      │
│  • Condition validation                                  │
└───────────────────────────────────────────────────────────┘

┌─ Matched Customers Tab ───────────────────────────────────┐
│  • Customer grid with segment members                    │
│  • Filter and search capabilities                        │
└───────────────────────────────────────────────────────────┘

┌─ Performance Tab ─────────────────────────────────────────┐
│  • Performance metrics                                   │
│  • Optimization suggestions                              │
└───────────────────────────────────────────────────────────┘
```

### 3. Condition Builder Interface

#### Visual Condition Builder
```javascript
// Modern JavaScript-based condition builder
class ConditionBuilder {
    constructor(container) {
        this.container = container;
        this.conditions = [];
        this.init();
    }
    
    init() {
        this.createRootGroup();
        this.bindEvents();
        this.loadExisting();
    }
    
    createRootGroup() {
        const group = this.createConditionGroup('all'); // AND group
        this.container.appendChild(group);
    }
    
    createConditionGroup(operator) {
        const group = document.createElement('div');
        group.className = 'condition-group';
        group.innerHTML = this.getGroupTemplate(operator);
        return group;
    }
    
    addCondition(groupElement, type) {
        const condition = this.createCondition(type);
        groupElement.querySelector('.conditions-list').appendChild(condition);
        this.updatePreview();
    }
}
```

#### Condition Categories
```
Customer Data
├── Personal Information
│   ├── Name contains/starts with/ends with
│   ├── Email domain is/is not
│   ├── Gender is/is not
│   └── Date of birth (age ranges, birthday proximity)
├── Account Information
│   ├── Customer group is/is not
│   ├── Account created (date range)
│   ├── Last login (days ago)
│   └── Newsletter subscription status
└── Address Information
    ├── Country is/is not
    ├── State/Region is/is not
    ├── City contains
    └── Postal code matches pattern

Order History
├── Purchase Behavior
│   ├── Number of orders (greater/less than)
│   ├── Total spent (amount ranges)
│   ├── Average order value
│   └── Order frequency
├── Order Timing
│   ├── First order date
│   ├── Last order date
│   ├── Days since last order
│   └── Seasonal purchase patterns
├── Payment & Shipping
│   ├── Payment method used
│   ├── Shipping method used
│   └── Coupon usage history
└── Product Purchases
    ├── Purchased specific product(s)
    ├── Purchased from category
    ├── Product attributes (brand, price range)
    └── Purchase quantity

Shopping Cart
├── Current Cart
│   ├── Cart total amount
│   ├── Number of items
│   ├── Specific products in cart
│   └── Applied coupons
├── Cart Behavior
│   ├── Cart abandonment (days abandoned)
│   ├── Cart recovery rate
│   └── Multiple cart sessions
└── Cart History
    ├── Average cart value
    ├── Cart conversion rate
    └── Frequently added products

Product Interaction
├── Browsing Behavior
│   ├── Products viewed
│   ├── Categories browsed
│   ├── Time spent on products
│   └── Search terms used
├── Wishlist Activity
│   ├── Items in wishlist
│   ├── Wishlist value
│   └── Wishlist to purchase rate
└── Reviews & Ratings
    ├── Reviews written
    ├── Average rating given
    └── Helpful votes received
```

### 4. Real-time Preview System

#### Customer Count Preview
```php
class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_Preview
{
    public function getPreviewAjaxUrl()
    {
        return $this->getUrl('*/*/preview', ['_current' => true]);
    }
    
    protected function _toHtml()
    {
        $html = '<div id="segment-preview">';
        $html .= '<div class="preview-controls">';
        $html .= '<button type="button" onclick="updatePreview()" class="scalable">';
        $html .= $this->__('Update Preview') . '</button>';
        $html .= '</div>';
        $html .= '<div id="preview-results">';
        $html .= $this->__('Click "Update Preview" to see matching customers');
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
```

#### JavaScript Integration
```javascript
function updatePreview() {
    const conditions = collectConditions();
    const websiteIds = getSelectedWebsites();
    
    showLoadingIndicator();
    
    fetch(previewUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            conditions: conditions,
            website_ids: websiteIds
        })
    })
    .then(response => response.json())
    .then(data => {
        updatePreviewDisplay(data);
        hideLoadingIndicator();
    })
    .catch(error => {
        showError('Preview failed: ' + error.message);
        hideLoadingIndicator();
    });
}

function updatePreviewDisplay(data) {
    const resultsDiv = document.getElementById('preview-results');
    resultsDiv.innerHTML = `
        <div class="preview-summary">
            <h4>Matching Customers: ${data.count}</h4>
            <p>Query execution time: ${data.execution_time}ms</p>
        </div>
        <div class="preview-samples">
            <h5>Sample Customers:</h5>
            <ul>${data.samples.map(customer => 
                `<li>${customer.name} (${customer.email})</li>`
            ).join('')}</ul>
        </div>
    `;
}
```


### 5. Performance Dashboard

#### Metrics Display
```php
class Maho_CustomerSegmentation_Block_Adminhtml_Dashboard_Performance
{
    public function getSegmentMetrics()
    {
        return [
            'total_segments' => $this->getTotalSegments(),
            'active_segments' => $this->getActiveSegments(),
            'total_customers_segmented' => $this->getTotalCustomersSegmented(),
            'avg_customers_per_segment' => $this->getAverageCustomersPerSegment(),
            'last_refresh_time' => $this->getLastRefreshTime(),
            'refresh_success_rate' => $this->getRefreshSuccessRate()
        ];
    }
    
    public function getPerformanceChartData()
    {
        $logs = Mage::getResourceModel('customersegmentation/refresh_log_collection')
            ->addFieldToFilter('created_at', [
                'from' => date('Y-m-d', strtotime('-30 days')),
                'to' => date('Y-m-d')
            ])
            ->setOrder('created_at', 'ASC');
        
        $chartData = [];
        foreach ($logs as $log) {
            $chartData[] = [
                'date' => $log->getCreatedAt(),
                'execution_time' => $log->getExecutionTime(),
                'customers_processed' => $log->getCustomersProcessed()
            ];
        }
        
        return $chartData;
    }
}
```

## User Experience Enhancements

### 1. Drag & Drop Interface
- Visual condition building with drag-and-drop
- Nested condition groups
- Color-coded condition types
- Intuitive operator selection

### 2. Smart Suggestions
- Auto-complete for attribute values
- Popular condition templates
- Performance optimization hints
- Data validation warnings

### 3. Responsive Design
- Mobile-friendly admin interface
- Touch-optimized controls
- Collapsible sections
- Keyboard shortcuts

### 4. Accessibility Features
- ARIA labels for screen readers
- Keyboard navigation support
- High contrast mode
- Font size adjustments

## Configuration Interface

### System Configuration
```xml
<!-- system.xml -->
<config>
    <sections>
        <customer_segmentation>
            <label>Customer Segmentation</label>
            <tab>customer</tab>
            <sort_order>300</sort_order>
            <show_in_default>1</show_in_default>
            <show_in_website>1</show_in_website>
            <show_in_store>1</show_in_store>
            <groups>
                <general>
                    <label>General Settings</label>
                    <fields>
                        <enabled>
                            <label>Enable Customer Segmentation</label>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                        </enabled>
                        <refresh_frequency>
                            <label>Auto Refresh Frequency (hours)</label>
                            <frontend_type>text</frontend_type>
                            <validate>validate-number</validate>
                        </refresh_frequency>
                        <batch_size>
                            <label>Batch Size for Processing</label>
                            <frontend_type>text</frontend_type>
                            <validate>validate-digits</validate>
                        </batch_size>
                    </fields>
                </general>
                <performance>
                    <label>Performance Settings</label>
                    <fields>
                        <enable_caching>
                            <label>Enable Segment Caching</label>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                        </enable_caching>
                        <cache_lifetime>
                            <label>Cache Lifetime (seconds)</label>
                            <frontend_type>text</frontend_type>
                            <validate>validate-number</validate>
                        </cache_lifetime>
                    </fields>
                </performance>
            </groups>
        </customer_segmentation>
    </sections>
</config>
```

## Security Considerations

### 1. Access Control
- Role-based permissions for segment management
- Separate permissions for viewing vs. editing
- Audit trail for all segment changes

### 2. Data Protection
- Encryption for sensitive segment data
- GDPR compliance features
- Data retention policies

### 3. Input Validation
- XSS protection in condition values
- SQL injection prevention
- File upload restrictions
- Rate limiting for API calls

## Integration with Existing Features

### 1. Customer Grid Integration
- Add segment filter to customer grid
- Display customer segments in customer details
- Bulk segment assignment tools

### 2. Reports Integration
- Segment-based customer reports
- Sales performance by segment
- Conversion analytics

### 3. Marketing Tools
- Cart price rule integration
- Email campaign targeting
- Promotional banner targeting