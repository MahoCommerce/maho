---
description: How Maho's legacy REST API authorizes requests - user types, access levels, and the ACL that controls access to resources.
---

# REST Permission Settings

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../../v2/index.md) instead.

## Introduction

After the authentication is complete successfully, the Access Token is received and will be used in every API call. This key allows identifying the client that accesses the API. With the help of this key, the following information about the user can be retrieved:

-   Type of the API user
-   User ID (can be Admin ID or Customer ID)

## Authorization

### Access Levels

There is a three-level authorization approach in Maho REST API. These three levels are as follows:

-   Guest
-   Customer
-   Admin

The following graphic describes the default rights for each access level with each level obtaining more rights up to Admin who has access to everything.

Each user type will be described below.  
Maho grants permissions for the following three types of users:

**Guest**

Guest can be a type of application that does not require authentication. This application has access only to public resources.

**Customer**

Customer can be a registered and logged in user. This type of user can have access only to its own resources as well as to public resources.

**Admin**

Admin can be the store owner. This type has full set of permissions.

Understanding of access levels is the basis of the ACL work.

### Access Control List (ACL)

#### ACL Overview

Every user has a specific role and purpose. To accomplish their goals, each user must be able to access certain resources and perform specific actions. Allowing users to access the resources without any limits can compromise Maho security.

The Access Control List (ACL) is a set of permissions (access rights) that particular users have for certain resources. When a user wants to perform a specific action with a resource (for example, update the customer information), Maho checks the permission for this combination of user, resource, and action. If the action is allowed, the user can proceed. Otherwise, the action is denied.

#### Understanding ACL

Access control lists include two main things: a subject and an object. Usually, the subject is the user who wants to use the resource. The object is the resource that a certain user wants to have access to. So, ACL is used to decide when the subject can have access to object.

You should remember that ACL is not the same as authentication. ACL is the next step after the authentication is passed successfully. These two concepts are closely connected but the difference lies in the following: authentication is understanding who the user is and ACL is understanding what the user can do.

#### ACL Structure

ACL is implemented in a tree structure. There is a tree of resources for each user type. Namely, Admin, Customer, and Guest have their own trees of resources.  
Each ACL entry specifies two instances: a subject and an action the subject can perform.

#### Read/Write Permissions

All REST resource attributes are divided into two categories: Read and Write. The Read category includes the operation of retrieving. So, when selecting the attributes in the Read category, you specify them for the resource retrieving. The Write category includes the operations of creating and updating. So, when selecting the attributes in the Write category, you specify them for the resource creation and updating.

#### Setting Up ACL

Setting up ACL is performed on two levels:

-   [Setting up REST roles](roles_configuration.md)
-   [Setting up REST attributes](attributes_configuration.md)