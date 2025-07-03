# Zend_Pdf to dompdf Migration Plan

## Overview
Complete migration from Zend_Pdf to dompdf for PDF generation in Maho ecommerce platform. This migration replaces coordinate-based drawing with HTML/CSS template-based PDF generation using phtml templates.

## Migration Strategy
- **Direct replacement**: Complete removal of Zend_Pdf, no backward compatibility
- **Template-based**: HTML/CSS templates instead of coordinate calculations  
- **Block architecture**: PDF generation using standard Maho block/template system
- **Data preservation**: All existing PDF data maintained in new templates

## Implementation Status

### Phase 1: Infrastructure Setup
- [x] Add dompdf to composer.json
- [x] Remove Zend_Pdf dependency
- [x] Create base PDF classes
  - [x] `Mage_Core_Block_Pdf` - Base PDF block with dompdf integration
  - [x] `Mage_Sales_Block_Order_Pdf_Abstract` - PDF document base class
  - [x] `Mage_Core_Helper_Pdf` - PDF helper functions

### Phase 2: Document Generation Classes (33 classes total)

#### Core Document Classes (3 classes)
- [x] `Mage_Sales_Block_Order_Pdf_Invoice` (replace Model/Order/Pdf/Invoice.php)
- [x] `Mage_Sales_Block_Order_Pdf_Shipment` (replace Model/Order/Pdf/Shipment.php)
- [x] `Mage_Sales_Block_Order_Pdf_Creditmemo` (replace Model/Order/Pdf/Creditmemo.php)

#### Item Renderer Classes (6 classes)
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Abstract` (replace Model/Order/Pdf/Items/Abstract.php)
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Invoice_Default` 
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Invoice_Grouped`
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Shipment_Default`
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Creditmemo_Default`
- [ ] `Mage_Sales_Block_Order_Pdf_Items_Creditmemo_Grouped`

#### Module-Specific Renderers (7 classes)
- [ ] `Mage_Bundle_Block_Sales_Order_Pdf_Items_Abstract`
- [ ] `Mage_Bundle_Block_Sales_Order_Pdf_Items_Invoice`
- [ ] `Mage_Bundle_Block_Sales_Order_Pdf_Items_Shipment`
- [ ] `Mage_Bundle_Block_Sales_Order_Pdf_Items_Creditmemo`
- [ ] `Mage_Downloadable_Block_Sales_Order_Pdf_Items_Abstract`
- [ ] `Mage_Downloadable_Block_Sales_Order_Pdf_Items_Invoice`
- [ ] `Mage_Downloadable_Block_Sales_Order_Pdf_Items_Creditmemo`

#### Totals Classes (5 classes)
- [ ] `Mage_Sales_Block_Order_Pdf_Totals_Default` (replace Model/Order/Pdf/Total/Default.php)
- [ ] `Mage_Tax_Block_Sales_Pdf_Subtotal`
- [ ] `Mage_Tax_Block_Sales_Pdf_Tax`
- [ ] `Mage_Tax_Block_Sales_Pdf_Grandtotal`
- [ ] `Mage_Tax_Block_Sales_Pdf_Shipping`

#### Controller Classes (9 classes)
- [ ] Update `Mage_Adminhtml_Sales_OrderController` PDF actions
- [ ] Update `Mage_Adminhtml_Sales_Order_InvoiceController`
- [ ] Update `Mage_Adminhtml_Sales_Order_ShipmentController`
- [ ] Update `Mage_Adminhtml_Sales_Order_CreditmemoController`
- [ ] Update `Mage_Adminhtml_Controller_Sales_Invoice`
- [ ] Update `Mage_Adminhtml_Controller_Sales_Shipment`
- [ ] Update `Mage_Adminhtml_Controller_Sales_Creditmemo`

#### Optional DHL Classes (3 classes)
- [ ] `Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf`
- [ ] `Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_Page`
- [ ] `Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_PageBuilder`

### Phase 3: Template Creation (~25 templates)

#### Template Structure
```
app/design/adminhtml/default/default/template/sales/order/pdf/
├── invoice/
│   ├── default.phtml
│   ├── header.phtml
│   ├── items/
│   │   ├── default.phtml
│   │   ├── grouped.phtml
│   │   ├── bundle.phtml
│   │   └── downloadable.phtml
│   └── totals/
│       ├── default.phtml
│       ├── tax.phtml
│       ├── shipping.phtml
│       └── grandtotal.phtml
├── shipment/ [similar structure]
├── creditmemo/ [similar structure]
├── shared/
│   ├── logo.phtml
│   ├── addresses.phtml
│   ├── payment_info.phtml
│   └── footer.phtml
└── styles/
    └── pdf.css
```

#### Template Creation Status
- [ ] Invoice templates (8 templates)
- [ ] Shipment templates (6 templates) 
- [ ] Creditmemo templates (8 templates)
- [ ] Shared templates (4 templates)
- [ ] CSS stylesheet (1 file)

### Phase 4: Configuration Updates

#### Configuration Files
- [ ] Update `app/code/core/Mage/Sales/etc/config.xml`
- [ ] Update `app/code/core/Mage/Sales/etc/system.xml`
- [ ] Create `Mage_Sales_Model_System_Config_Source_Pdf_Papersize`

#### Configuration Changes
- [ ] Add dompdf configuration section
- [ ] Update PDF block configuration (replace model config)
- [ ] Update item renderer configuration for blocks
- [ ] Add template path configuration
- [ ] Remove Zend_Pdf references

### Phase 5: Complete Zend_Pdf Removal & Template Creation
- [x] Modify existing Model PDF classes to use dompdf  
- [x] Update Abstract.php to support HTML template rendering
- [x] Update Invoice.php, Shipment.php, Creditmemo.php
- [x] Update item renderer Abstract.php to extend Block_Template
- [x] Update all item renderers (Invoice, Shipment, Creditmemo, Grouped)
- [x] Create all phtml templates (invoice, shipment, creditmemo + items)
- [x] Create comprehensive PDF CSS stylesheet
- [x] Add grouped product template support

## Technical Implementation Details

### Current Data Structures (Preserved)
```php
// Line block structure maintained in templates
$lines[0][] = [
    'text' => 'Content',
    'feed' => 35,        // Becomes CSS column width
    'align' => 'right'   // Becomes CSS text-align
];
```

### New Architecture
- **dompdf integration**: HTML/CSS → PDF conversion
- **Block-based**: Standard Maho block/template architecture
- **Template inheritance**: Theme-based PDF customization
- **Layout XML**: PDF generation via layout handles

### CSS Approach
- **Coordinate mapping**: Current feed positions → CSS column widths
- **Table layouts**: Better PDF compatibility than CSS Grid/Flexbox
- **Font handling**: DejaVu Sans for broad character support
- **Color preservation**: Exact color mapping from current implementation

## Files Modified/Created

### New Files Created
- Infrastructure: 3 new base classes
- Document blocks: 3 classes  
- Item renderers: 13 classes
- Totals: 5 classes
- Templates: ~25 phtml files + 1 CSS file
- Configuration: 1 new source model class

### Files Modified  
- 9 controller classes updated
- 2 configuration XML files updated
- composer.json updated

### Files Removed
- All existing Zend_Pdf usage removed from 33 classes

## Migration Completion Criteria
- [x] All core PDF infrastructure in place
- [x] Model classes updated to use dompdf  
- [x] Block classes created for template rendering
- [x] Configuration updated with layout handles
- [x] All item renderers updated
- [x] All templates created (Invoice, Shipment, Creditmemo + items)
- [x] Comprehensive CSS stylesheet created
- [x] Support for grouped products added
- [ ] Bundle and Downloadable product renderers (optional)
- [ ] No Zend_Pdf references remain in codebase

*Note: Manual testing will be performed by the user*

---

*Last Updated: 2025-07-02*
*Status: Planning Complete - Ready for Implementation*