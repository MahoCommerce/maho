---
description: Configure REST attributes in the Maho admin panel to limit which resource fields guest, customer, and admin users can read and write.
---

# REST Attributes Configuration

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../../v2/index.md) instead.

REST attributes allow specifying additional filters for different types of users. Attributes allow limiting user access more precisely.

## REST Attributes Structure

The REST attributes tree includes the following elements (as subnodes):

-   Name of the resource
    -   Read permissions (includes all elements available for the current resource)
    -   Write permissions (includes all elements available for the current resource)

The Resources tree may be too immense. To avoid scrolling down when searching for the required resource, you can fold the nodes for better representation.

## Managing Attributes for Guest

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Attributes**.
2.  The REST Attributes page opens. From the list of user types, select the **Guest** type and click it.
3.  The page for editing attribute rules opens.
4.  In the User Type Resources panel, in the **Resource Access** drop-down list, select whether all or some specific resources will be limited to a **Guest** type of user by selecting the corresponding **All** or **Custom** options.
5.  If you select the **Custom** option, the resources tree appears.
6.  Select the required options and click **Save** in the top right corner to apply changes.

## Managing Attributes for Customer

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Attributes**.
2.  The REST Attributes page opens. From the list of user types, select the **Customer** type and click it.
3.  The page for editing attribute rules opens.
4.  In the User Type Resources panel, in the **Resource Access** drop-down list, select whether all or some specific resources will be limited to a **Customer** type of user by selecting the corresponding **All** or **Custom** options.
5.  If you select the **Custom** option, the resources tree appears. Some resources have options for selecting read and write permissions.
6.  Select the required options and click **Save** in the top right corner to apply changes.

## Managing Attributes for Admin

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Attributes**.
2.  The REST Attributes page opens. From the list of user types, select the **Admin** type and click it.
3.  The page for editing attribute rules opens.
4.  In the User Type Resources panel, in the **Resource Access** drop-down list, select whether all or some specific resources will be limited to an **Admin** type of user by selecting the corresponding **All** or **Custom** options.
5.  If you select the **Custom** option, the resources tree appears. Each resource has options for selecting read and write permissions.
6.  Select the required options and click **Save** in the top right corner to apply changes.

## Examples

This section provides some examples of limiting Guest and Customer access to certain resource elements.

### Limiting Guest Access to Products

To allow Guests (users that are not registered in the Maho system) view only product name and final price with tax, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles** and select the Guest role.
2.  In the **Role API Resources**, specify the Retrieve option for the Product resource.
3.  Click **Save Role** on the top right corner to save the role.
4.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Attributes** and select **Guest** in the list of User Types.
5.  In the Resources tree, navigate to the **Catalog Product** node. In the Read subnode, select the **Name** and **Final Price With Tax** options.
6.  Click **Save** in the top right corner to save the selected attributes.

### Limiting Customer Access to Products

To allow Customers (users that are registered in the Maho system) view only product name and final price with tax, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles** and select the Customer role.
2.  In the **Role API Resources**, specify the Retrieve option for the Product resource.
3.  Click **Save Role** on the top right corner to save the role.
4.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Attributes** and select **Customer** in the list of User Types.
5.  In the Resources tree, navigate to the **Catalog Product** node. In the Read subnode, select the **Name** and **Final Price With Tax** options.
6.  Click **Save** in the top right corner to save the selected attributes.