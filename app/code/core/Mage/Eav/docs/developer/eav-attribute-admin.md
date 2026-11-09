---
description: How System > Attributes manages the attributes and attribute sets of an EAV entity type, the settings that each type gives, and how the core entity types use them.
---

# Attribute Admin Pages <span class="version-badge">v27.1+</span>

**System > Attributes** has one entry for each EAV entity type that an admin can extend: categories,
customers, customer addresses, CMS pages, blog posts and blog categories. Each entry opens an
attribute grid and, when the type uses sets, an attribute set grid. Product attributes stay under
**Catalog > Attributes**.

Two controllers serve every type: `Mage_Adminhtml_Eav_AttributeController` and
`Mage_Adminhtml_Eav_SetController`. The `entity_type` url parameter names the type. The attribute
model of the type gives the rules of the pages, so a module adds a type with no controller and no
block of its own.

## Add an entity type

1. Give the entity type an attribute model that extends `Mage_Eav_Model_Entity_Attribute`.
2. Override `getAdminPageSettings()` in the attribute model. Return a
   `Mage_Eav_Model_Entity_Attribute_AdminPageSettings` for your type, and `null` for any other type.
3. Declare the menu entry and the ACL resource below `system/attributes` in the `adminhtml.xml` of
   your module. The menu action is `adminhtml/eav_attribute/index/entity_type/<type code>`.
4. Add the strings of the titles to the locale file of your module.

```php
#[\Override]
public function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
{
    if ($this->getEntityType()->getEntityTypeCode() !== 'my_entity') {
        return null;
    }
    return new Mage_Eav_Model_Entity_Attribute_AdminPageSettings(
        acl: 'system/attributes/my_entity',
        title: Mage::helper('mymodule')->__('My Entity Attributes'),
        setsTitle: Mage::helper('mymodule')->__('My Entity Attribute Sets'),
        addToDefaultSet: true,
    );
}
```

A type without settings gets "Access denied" on the pages.

The grid and the set editor list only the attributes that an admin added. The edit, save and
delete actions refuse a system attribute. The edit pages and the storefront templates of these
types place their system attributes themselves, so the attribute pages have nothing useful to
change on them. A new set copies the system attributes from the set it is based on. Products keep
the Magento behavior under **Catalog > Attributes**.

## The settings

| Argument | Type | What it does |
|---|---|---|
| `acl` | string | The ACL resource and the menu entry, below `system/attributes`. |
| `title` | string | The title of the attribute grid. |
| `setsTitle` | string or `null` | The title of the set grid. With `null` (the default), the pages show no **Attribute Sets** button. |
| `mainTab` | string or `null` | The block of the **Properties** tab of the attribute edit page. |
| `allowedTags` | list of strings | The HTML tags that an option label keeps when the attribute allows HTML on the storefront. |
| `addToDefaultSet` | bool | A new attribute that names no set goes into the first group of the default set. The rule is in `Mage_Eav_Model_Entity_Attribute::_beforeSave()`, so it applies to the admin, an import and code. |
| `singleSet` | bool | Every record uses the default set. **Attribute Set** opens that set, and the pages cannot add or delete a set. |

Set `addToDefaultSet` when most stores use one set for your entity. A new attribute then shows on
the edit page at once. Leave it off when stores usually have many sets, as for products. A module
that creates an attribute in code for such a type must add it to a set itself.

Set `singleSet` when the records of your entity have no set of their own. The groups of the default
set still give the order of the fields, the tabs and the storefront tables.

On every type, a record shows an attribute only when its set holds the attribute. An attribute in
no set shows nowhere.

The attribute model can also change the save and the texts of the set page:

- `prepareAdminData()` turns the posted values into the data of the attribute.
- `saveAdminData()` saves the posted values that the attribute does not save itself.
- `_getInputTypes()` gives the input types of the type. The event
  `eav_attribute_input_types_prepare` lets another module add or remove a type.
- `getValidationErrors()` returns the errors of the values that a save refuses. A subclass adds the
  rules of its type: the catalog attribute checks the reserved codes of products, the scope, the
  layered navigation value, the position and the product types of **Apply To**.
- `getAllowedOptionTags()` returns the HTML tags that an option label keeps when the attribute
  allows HTML on the storefront. By default, it returns `allowedTags` of the settings.
- `getAttributeSummary()` returns a summary that the attribute page shows above the properties,
  in a section of its own. Each row names a setting and lists its values as badges. A value that
  does not show the attribute has a line through it. The customer types list the forms, the
  customer groups and the websites.
- `getSetNote($set)` returns a text that the set page shows above the groups. Each line of the text
  is a paragraph. The customer types use it to say that the customer group picks the set, to name
  the groups that use the set, and to say that only the groups after the first one have a title in
  the forms.
- `getSetDeleteQuestion($set)` returns the question before a set delete. By default, it names the
  attributes whose values the records lose, because the records move to the default set.
- `getSetRemoveQuestion($set, $attributes)` returns the question before a save of the set page
  takes user-defined attributes out of the set, or `null` for no question. By default, it names
  each attribute and the number of records of the set that have a value. The customer types say
  that the values stay, and CMS pages say that the pages keep their values.

## The attribute service

`Mage::getService('eav/attribute')` returns `Mage_Eav_Service_Attribute`. The admin pages of every
entity type, the product attribute API and the `import:attributes` command save, delete and change
the options of an attribute through it:

- `newAttribute($entityTypeCode)`, `getById($id, $entityTypeCode)` and `getByCode($code, $entityTypeCode)`
  give the attribute model of the type. An ID or a code of another type is not found.
- `save($attribute)` removes the HTML tags from the labels and checks the attribute. A new attribute
  gets the backend, source and frontend models of its input type and becomes user-defined.
- `saveOptions($attribute, $changes)` changes some options of a Dropdown or a Multiple Select and
  keeps the others, with their default flag.
- `delete($attribute)` deletes a user-defined attribute.

The model keeps the rules of one attribute, so a save in code gets them too:

- The code of a new attribute starts with a letter a-z and holds only letters a-z, digits and
  underscores, from 2 to 64 characters. Another attribute of the type cannot have the same code.
- A saved attribute keeps its code and its input type.
- A saved attribute keeps the locked fields that `global/eav_attributes/<type code>` gives for its
  code, and a system attribute keeps its **Unique Value** flag.
- A system attribute cannot be deleted. The resource model reads the stored flag.

## How the core entity types use them

| Entity type | Values stored in | What picks the set | `addToDefaultSet` |
|---|---|---|---|
| Products | EAV value tables | Each product | Not managed here |
| Catalog categories | EAV value tables | Each category | Yes |
| Customers | EAV value tables | The customer group | Yes |
| Customer addresses | EAV value tables | The customer group | Yes |
| CMS pages | A static column of `cms_page` for each attribute | The default set, for every page (`singleSet`) | Yes |
| Blog posts | EAV value tables | Each post | Yes |
| Blog categories | EAV value tables | Each category | Yes |

The reasons for each choice:

- **Products** keep the Magento behavior. Stores have many product sets, so a new attribute goes
  into no set, and the admin picks the sets.
- **Catalog categories** build their edit page from the groups of the set. A new attribute in a
  group shows on the tab of that group.
- **Customers and customer addresses** take the set of the customer group. A group without a set
  uses the default set, so a new attribute in the default set shows to all these groups at once. Each
  later group of the set is a fieldset of the forms. The `data-upgrade-2.0.4-2.0.5.php` script of
  `Mage_Customer` puts each form attribute that was in no set into the default set.
- **CMS pages** have no set column. `Mage_Cms_Model_Page::getAttributeSetId()` returns the default
  set, and `Mage_Cms_Model_Resource_Page::getCustomAttributes()` returns only its attributes, in the
  order of the set. The attributes of the first group show on the **Page Information** tab, and each
  other group that holds an attribute adds a tab.
- **Blog posts and blog categories** have a hard-coded edit form for the fields of the blog. The
  attributes of the first group of the set show on the main tab (**Content** for a post, **General**
  for a category). Each other group that holds an attribute adds a tab.

## Group labels and storefront parts

A group of a set has a name, which the admin uses, and an optional label for each store view in the
`eav_attribute_group_label` table. The **Add** and **Rename** buttons of the set editor open one
window with a row for each.

- `Mage_Eav_Model_Entity_Attribute_Group::getStoreLabel($storeId)` returns the label of the store
  view, or the group name as plain text.
- `setStoreLabels()` replaces all labels on the next save. A save that does not set them keeps the
  saved labels.
- The `addStoreLabel($storeId)` method of the group collection joins the labels of one store view,
  so a list of groups needs no query for each group.

`Mage_Eav_Helper_Data::groupAttributesBySet($attributes, $setId, $storeId)` splits a list of
attributes by the groups of a set, in the order of the groups. The first part has no label: it holds
the attributes of the first group, the attributes that are not in the set and the attributes that
are not user-defined, because they join the fields that the page shows itself. Each other group follows with its label. These callers use it:

- `Mage_Customer_Block_Form_Attributes` shows each later group as a fieldset in the storefront
  forms of customers and addresses.
- The admin customer page shows each later group as a fieldset of the **Account Information** and
  **Addresses** tabs, with the Admin name.
- `Mage_Eav_Block_Frontend_Attributes` shows one table for each group on the storefront pages of
  catalog categories, CMS pages, blog posts and blog categories.

## The storefront table

`Mage_Eav_Block_Frontend_Attributes` escapes each value. An attribute that allows HTML or uses the
WYSIWYG editor keeps its HTML, through `renderHtmlValue()`. The base block does not know the template
processor of the entity type, so it removes the directives and sanitizes the rest. A block for one
entity type overrides the method:

- `Mage_Catalog_Block_Category_Attributes` uses `Mage_Catalog_Helper_Output::categoryAttribute()`.
  The save sanitizes a WYSIWYG value, as it does for the description.
- `Maho_Blog_Block_Attributes` resolves the directives of a WYSIWYG value with the processor of the
  post content. `Maho_Blog_Helper_Data::sanitizeAttributeValues()` sanitizes the value on save.
- `Mage_Cms_Block_Page_Attributes` keeps the method of the base block. A CMS page has no EAV
  resource, so the block reads the attributes of the set from `Mage_Cms_Model_Page::getAttributes()`. The
  `cms_eav_attribute` table holds the **Visible on Frontend** and **Allow HTML Tags on Frontend**
  flags of each attribute.

To show a page attribute in another place, read it in a template of the `Mage_Cms_Block_Page`
block: `$this->getPage()->getData('your_code')`. A Dropdown returns the option ID, and a Multiple
Select returns the option IDs separated by commas. The value is not sanitized, so escape it, for
example with `$this->escapeHtml()`.

## The API

Each resource of an entity type has a `customAttributes` map of the user-defined attributes. The
same rules apply to every resource:

- A read returns the same keys and the same shapes that a write takes.
  `Maho\ApiPlatform\Service\CustomAttributes::toApiValue()` gives the shape: a list of option IDs for
  a multiselect, an option ID for a dropdown, a bool for a Yes/No, an int or a float for a number,
  and `Y-m-d` for a date.
- A write takes only the attributes of the set of the record. A new record must get each required
  attribute.
- Each error names the field `customAttributes.<code>`.
- Customers and addresses also serve the storefront. A customer token or a guest writes through the
  storefront form of the action (`Mage\Customer\Api\CustomAttributes::STOREFRONT_FORMS`), and
  `Mage\Customer\Api\CustomAttributesNormalizer` removes from a read the attributes that no
  storefront form shows to the group of the caller. `GET /api/rest/v2/customer-forms/{form}` lists
  the fields of a storefront form.
- The property names its attributes with `extraProperties: [CustomAttributeSchema::KEY => '<type code>']`.
  `EnumSourceSchemaFactory` then lists each attribute as a typed property in the OpenAPI document and
  the MCP tools, with the option IDs of a dropdown as an enum.

`GET /api/rest/v2/custom-attributes?entityType=<type code>` lists the user-defined attributes of
each type that has settings: the code, the input type, the options and the sets that hold the
attribute. `GET /api/rest/v2/custom-attributes/{id}` reads one. Product attributes are at
`/product-attributes`.

## Sets and the nightly cleanup

The nightly `eav_clean_orphaned_records` cron deletes each value whose attribute is not in the set
of its record. It reads the `attribute_set_id` column of the entity table. For that reason:

- A save of the set page that takes a user-defined attribute out of the set asks first.
  `Mage_Adminhtml_Controller_Eav_Set::saveAction()` answers `{"confirm": "<question>"}`, and the
  set editor posts again with `confirmed=1` when the admin accepts.
- A change of the set of a saved record asks first, and names the attributes that the new set does
  not have. `Mage_Eav_Helper_Data::getSetChangeQuestions()` builds the questions, and
  `Mage_Eav_Model_Resource_Entity_Attribute_Set::getMissingAttributeLabels()` finds the attributes.
- The set page refuses to remove a system attribute from a set, and to delete a group that still
  holds one. `Mage_Adminhtml_Controller_Eav_Set::checkSystemAttributes()` makes the same check on
  the server. The check also covers the system attributes that the editor hides.
- Customers and customer addresses keep `attribute_set_id` 0 in their rows, so the cron does not
  delete their values.
