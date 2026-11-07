---
description: Create, edit, and assign REST roles in the Maho admin panel to control which legacy REST API resources each user type can access.
---

# REST Roles Configuration

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../../v2/index.md) instead.

REST roles in Maho are used to limit access to certain resources. Limiting access lies in configuration of a REST role and assigning a user to it. You can select which resources will be available for the user and which will not.

REST roles management consists in the role creation, editing, deleting, and user assignment. Note that REST role creation and deletion is available only for Admin role.

The REST Roles page initially includes two roles: Customer and Guest.

Only API Resources can be edited in the Guest and Customer roles. You cannot change the name or assigned users in these roles. Also, you cannot delete the Guest or Customer role.

## Viewing REST Roles

To view the list of REST roles, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens.
3.  REST roles are displayed in a grid with the following columns: ID (role ID), Role Name, User Type, and Created At (date and time of the role creation).

## Working with Admin Role

### Adding a New REST Role for Admin

To add a new REST role for Admin, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens.
3.  In the top right corner, click **Add Admin Role**. The Add New Role page opens.
4.  There are two tabs in the Role Information panel on the left: Role Info and Role API Resources.
5.  Select the Role Info tab and in the Role Information panel, enter the name for the role to be created in the corresponding **Role Name** field. This field is required.
6.  In the Role API Resources tab, in the **Resource Access** drop-down list, select whether the user will have full or custom access by selecting the corresponding **All** or **Custom** options. If you select the **Custom** option, the Resources tree will appear where you will be able to check the required resources and actions.
7.  Click **Save Role** in the top right corner to save the role.
8.  After you saved the role, a new Role Users tab appears in the Role Information panel on the left. In this tab, you can manage users for the current role. Click **Reset Filter** to view all users to which the role can be assigned.

### Editing an Existing Admin REST Role

To edit an existing Admin REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. In the roles grid, select the Admin role and click it.
3.  The Edit Role `<role name>` page opens. You can edit the following information:
    -   **Role Info**: Edit the name of the Admin role by selecting the Role Info tab to the left.
    -   **Role API Resources**: Select or clear the resources available for this role.
    -   **Role Users**: Assign or remove users for this role.
4.  Click **Save Role** in the top right corner to apply changes.

### Deleting an Existing Admin REST Role

To delete an existing Admin REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. In the roles grid, select the required Admin role to be deleted and click it.
3.  The Edit Role `<role name>` page opens. In the top right corner, click **Delete Role**. The role is deleted.

### Assigning a REST Role to Admin

To assign a REST role to admin, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Permissions** > **Users**.
2.  The Users page opens. In the users grid, select the user to which the REST Admin role will be assigned.
3.  The Edit User `<Name of the User>` page opens. In the User Information panel, select the REST Role tab.
4.  In the list of REST roles, select the Admin role to be assigned and select the option button next to it.
5.  Click **Save User** in the top right corner to save changes.

### Assigning Multiple Users to an Admin REST Role

To assign more than one user to an existing Admin REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. In the REST roles grid, select the Admin role to which users will be assigned.
3.  The Edit Role `<role name>` page opens. In the Role Information panel, select the Role Users tab.
4.  In the list of users, click **Reset Filter** to view the list of all users to which the role can be assigned. Select the checkboxes near the users to be assigned to the Admin role.
5.  Click **Save Role** in the top right corner to save changes.

### Viewing Users Assigned to an Admin REST Role

To view the list of users assigned to a REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. From the list of roles, select the Admin role whose assigned users you want to view and click it.
3.  The Edit Role `<role name>` page opens. In the Role Information panel on the left, select the Role Users tab.
4.  The list of REST role users is displayed in a grid with the following columns: ID, User Name, First Name, and Last Name.

### Unassigning User from the Admin REST Role

To unassign the Admin REST role from a user, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. From the list of roles, select the Admin role from which you want to unassign a user and click it.
3.  The Edit Role `<role name>` page opens. In the Role Information panel on the left, select the Role Users tab.
4.  Clear the checkbox next to the user which you want to unassign from the current REST role.
5.  Click **Save Role** in the top right corner to apply the changes.

## Working with Guest and Customer Roles

As it has been mentioned before, the Customer and Guest roles cannot be removed and can be only partially edited. You can edit only the resources and actions allowed for the user.

### Editing the Guest REST Role

To edit the Guest REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. From the list of roles, select the Guest role and click it.
3.  The Edit Role "Guest" page opens. In the Role Resources panel, edit the required information.
4.  Click **Save Role** in the top right corner to apply changes.

### Editing the Customer REST Role

To edit the Customer REST role, perform the following steps:

1.  On the Maho Admin Panel menu, select **System** > **Web Services** > **REST - Roles**.
2.  The REST Roles page opens. From the list of roles, select the Customer role and click it.
3.  The Edit Role "Customer" page opens. In the Role Resources panel, edit the required information.
4.  Click **Save Role** in the top right corner to apply changes.
