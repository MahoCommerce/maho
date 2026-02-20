<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Admin_Model_User Validation', function () {
    beforeEach(function () {
        $this->user = Mage::getModel('admin/user');
    });

    it('validates successfully with valid user data', function () {
        $this->user->setUsername('testuser');
        $this->user->setFirstname('Test');
        $this->user->setLastname('User');
        $this->user->setEmail('test@example.com');

        $result = $this->user->validate();
        expect($result)->toBeTrue();
    });

    describe('username validation', function () {
        it('fails validation when username is empty', function () {
            $this->user->setUsername('');
            $this->user->setFirstname('Test');
            $this->user->setLastname('User');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('User Name is required field.');
        });

        it('passes validation when username is blank spaces', function () {
            $this->user->setUsername('   ');
            $this->user->setFirstname('Test');
            $this->user->setLastname('User');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeTrue();
        });
    });

    describe('firstname validation', function () {
        it('fails validation when firstname is empty', function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('');
            $this->user->setLastname('User');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('First Name is required field.');
        });

        it('passes validation when firstname is blank spaces', function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('   ');
            $this->user->setLastname('User');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeTrue();
        });
    });

    describe('lastname validation', function () {
        it('fails validation when lastname is empty', function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('Test');
            $this->user->setLastname('');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Last Name is required field.');
        });

        it('passes validation when lastname is blank spaces', function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('Test');
            $this->user->setLastname('   ');
            $this->user->setEmail('test@example.com');

            $result = $this->user->validate();
            expect($result)->toBeTrue();
        });
    });

    describe('email validation', function () {
        it('fails validation when email is invalid', function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('Test');
            $this->user->setLastname('User');
            $this->user->setEmail('invalid-email');

            $result = $this->user->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Please enter a valid email.');
        });

        it('passes validation with valid email formats', function () {
            $validEmails = [
                'test@example.com',
                'user.name@domain.org',
                'user+tag@example.co.uk',
            ];

            foreach ($validEmails as $email) {
                $this->user->setUsername('testuser');
                $this->user->setFirstname('Test');
                $this->user->setLastname('User');
                $this->user->setEmail($email);

                $result = $this->user->validate();
                expect($result)->toBeTrue("Email '{$email}' should be valid");
            }
        });
    });

    describe('validateCurrentPassword method', function () {
        beforeEach(function () {
            $this->user->setUsername('testuser');
            $this->user->setFirstname('Test');
            $this->user->setLastname('User');
            $this->user->setEmail('test@example.com');
            // Set a hashed password for comparison
            $this->user->setPassword(Mage::helper('core')->getHash('testpassword123', 2));
            $this->user->setId(1); // Set ID to simulate existing user
        });

        it('fails validation when current password is empty', function () {
            $result = $this->user->validateCurrentPassword('');
            expect($result)->toBeArray();
            expect($result)->toContain('Current password field cannot be empty.');
        });

        it('fails validation when current password is blank spaces', function () {
            $result = $this->user->validateCurrentPassword('   ');
            expect($result)->toBeArray();
            expect($result)->toContain('Invalid current password.');
        });

        it('fails validation when current password is incorrect', function () {
            $result = $this->user->validateCurrentPassword('wrongpassword');
            expect($result)->toBeArray();
            expect($result)->toContain('Invalid current password.');
        });

        it('passes validation when current password is correct', function () {
            $result = $this->user->validateCurrentPassword('testpassword123');
            expect($result)->toBeTrue();
        });

        it('fails validation for user without ID (new user)', function () {
            $this->user->setId(null);
            $result = $this->user->validateCurrentPassword('testpassword123');
            expect($result)->toBeArray();
            expect($result)->toContain('Invalid current password.');
        });
    });

    it('returns multiple errors when multiple validation rules fail', function () {
        $this->user->setUsername('');
        $this->user->setFirstname('');
        $this->user->setLastname('');
        $this->user->setEmail('invalid-email');

        $result = $this->user->validate();
        expect($result)->toBeArray();

        expect(count($result))->toBeGreaterThanOrEqual(4);
        expect($result)->toContain('User Name is required field.');
        expect($result)->toContain('First Name is required field.');
        expect($result)->toContain('Last Name is required field.');
        expect($result)->toContain('Please enter a valid email.');
    });
});
