<?php

/**
 * Load, save, enable, disable and delete the admin users, and change their password, for the admin and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

use Mage_Admin_Model_User as User;

class Mage_Admin_Service_User
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $userId): User
    {
        $user = Mage::getModel('admin/user')->load($userId);
        if (!$user->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('adminhtml')->__('This user no longer exists.'));
        }
        return $user;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getByUsername(
        #[\SensitiveParameter]
        string $username,
    ): User {
        $user = Mage::getModel('admin/user')->loadByUsername($username);
        if (!$user->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('adminhtml')->__('The user %s does not exist.', $username));
        }
        return $user;
    }

    /**
     * Check $user with validate(), save it, and give it the role $roleId when it is set. A new user
     * triggers the new admin user notification when admin/security/crate_admin_user_notification is on.
     * $actingUserId is the admin user who makes the request. This user cannot deactivate the own account.
     *
     * @throws Mage_Core_Exception_Input with the errors of validate()
     * @throws Mage_Core_Exception_Conflict when $actingUserId deactivates the own account
     */
    public function save(User $user, ?int $roleId = null, ?int $actingUserId = null): User
    {
        $isNew = !$user->getId();
        if (!$isNew && $actingUserId === (int) $user->getId() && $user->getIsActive() === false) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('adminhtml')->__('You cannot deactivate your own account.'));
        }
        $messages = $user->validate();
        $errors = new Mage_Core_Exception_Input();
        foreach ($messages === true ? [] : $messages as $field => $message) {
            $errors->addError((string) $field, $message);
        }
        $errors->throwIfErrors();

        $user->save();
        if ($roleId !== null) {
            $user->setRoleIds([$roleId])->setRoleUserId($user->getUserId())->saveRelations();
        }
        if ($isNew && Mage::getStoreConfigFlag('admin/security/crate_admin_user_notification')) {
            try {
                $user->sendAdminNotification($user);
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
        return $user;
    }

    /**
     * Give $user the new password $password and save it with save(). $confirmation, when set, must be the same.
     *
     * @throws Mage_Core_Exception_Input when $password is empty, or with the errors of validate()
     */
    public function changePassword(
        User $user,
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        ?string $confirmation = null,
    ): User {
        if ($password === '') {
            throw new Mage_Core_Exception_Input()
                ->addError('new_password', Mage::helper('adminhtml')->__('New password field cannot be empty.'));
        }
        $user->setNewPassword($password);
        if ($confirmation !== null) {
            $user->setPasswordConfirmation($confirmation);
        }
        return $this->save($user);
    }

    public function enable(User $user): void
    {
        $user->setIsActive()->save();
    }

    /**
     * Disable $user. Its admin sessions end at their next request, and its API tokens are revoked.
     */
    public function disable(User $user): void
    {
        $user->setIsActive(false)->save();
    }

    /**
     * Remove the two-factor authentication of $user, who must enroll again from System > My Account.
     */
    public function resetTwofa(User $user): void
    {
        $user->setTwofaEnabled(false)->setTwofaSecret(null)->save();
    }

    /**
     * Delete $user. $actingUserId is the admin user who makes the request. This user cannot delete the own account.
     *
     * @throws Mage_Core_Exception_Conflict when $actingUserId deletes the own account
     */
    public function delete(User $user, ?int $actingUserId = null): void
    {
        if ($actingUserId !== null && $actingUserId === (int) $user->getId()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('adminhtml')->__('You cannot delete your own account.'));
        }
        $user->delete();
    }
}
