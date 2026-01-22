/**
 * Auth Module - Handles authentication, customer account, addresses, orders
 */
export default {
    // State
    token: localStorage.getItem('authToken'),
    customer: JSON.parse(localStorage.getItem('customer') || 'null'),
    loginForm: { email: '', password: '' },
    registerForm: { firstName: '', lastName: '', email: '', password: '', confirmPassword: '' },
    accountTab: 'info',
    addresses: [],
    orders: [],
    ordersPage: 1,
    ordersTotalPages: 1,
    ordersTotalItems: 0,
    editingAddressId: null,
    newAddress: {
        firstName: '',
        lastName: '',
        street: '',
        city: '',
        postcode: '',
        region: '',
        regionId: null,
        countryId: 'AU',
        telephone: '',
        isDefaultBilling: false,
        isDefaultShipping: false
    },
    showAddressForm: false,
    selectedOrder: null,
    selectedOrderInvoices: [],
    loadingInvoices: false,

    async login() {
        this.loading = true;
        try {
            // Include current cart ID for merging
            const loginPayload = {
                ...this.loginForm,
                cartId: this.cartId
            };

            const response = await fetch('/api/auth/token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(loginPayload)
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.message);
            }

            this.token = data.token;
            this.customer = data.customer;
            localStorage.setItem('authToken', data.token);
            localStorage.setItem('customer', JSON.stringify(data.customer));

            // Use the customer's cart ID from login response
            if (data.cartId) {
                this.cartId = data.cartId;
                localStorage.setItem('cartId', data.cartId);
                await this.loadCart();
            }

            // Prefill checkout (with safety check)
            if (this.checkout) {
                this.checkout.email = data.customer.email;
                if (this.checkout.shipping) {
                    this.checkout.shipping.firstName = data.customer.firstName;
                    this.checkout.shipping.lastName = data.customer.lastName;
                }
            }

            // Load wishlist module and sync guest wishlist
            try {
                await this.loadModule('wishlist');
                if (this.syncGuestWishlist) {
                    await this.syncGuestWishlist();
                }
            } catch (wishlistError) {
                console.warn('Wishlist sync failed:', wishlistError);
            }

            this.success = 'Welcome back, ' + data.customer.firstName + '!';
            this.navigate('home');
        } catch (e) {
            this.error = e.message || 'Login failed';
        }
        this.loading = false;
    },

    logout() {
        this.clearAuth();
        this.clearCart();
        this.loading = false;
        this.success = 'You have been logged out';
        this.navigate('home');
    },

    clearAuth() {
        this.token = null;
        this.customer = null;
        this.addresses = [];
        this.orders = [];
        this.wishlist = [];
        this.myReviews = [];
        this.editingAddressId = null;
        this.showAddressForm = false;
        localStorage.removeItem('authToken');
        localStorage.removeItem('customer');
    },

    clearCart() {
        this.cartId = null;
        this.cart = {};
        this.cartCount = 0;
        localStorage.removeItem('cartId');
    },

    validateAuthState() {
        if ((this.token && !this.customer) || (!this.token && this.customer)) {
            console.log('Auth state mismatch, clearing stale data');
            this.clearAuth();
        }
    },

    async register() {
        if (this.registerForm.password !== this.registerForm.confirmPassword) {
            this.error = 'Passwords do not match';
            return;
        }
        if (this.registerForm.password.length < 8) {
            this.error = 'Password must be at least 8 characters';
            return;
        }
        this.loading = true;
        try {
            await this.api('/customers', {
                method: 'POST',
                body: JSON.stringify({
                    firstName: this.registerForm.firstName,
                    lastName: this.registerForm.lastName,
                    email: this.registerForm.email,
                    password: this.registerForm.password
                })
            });

            // Auto-login after registration
            this.loginForm.email = this.registerForm.email;
            this.loginForm.password = this.registerForm.password;
            await this.login();

            // Reset register form
            this.registerForm = { firstName: '', lastName: '', email: '', password: '', confirmPassword: '' };
        } catch (e) {
            this.error = 'Registration failed: ' + e.message;
        }
        this.loading = false;
    },

    async loadAccountData() {
        if (!this.token) return;

        this.loading = true;
        try {
            const res = await fetch('/api/auth/me', {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });

            // Handle auth failures - logout and redirect
            if (res.status === 401 || res.status === 403) {
                this.logout();
                window.router?.navigate('/login');
                return;
            }

            const data = await res.json();

            if (data.error) {
                if (data.error === 'invalid_token' || data.error === 'unauthorized') {
                    this.logout();
                    window.router?.navigate('/login');
                    return;
                }
                throw new Error(data.message);
            }

            this.customer = data;
            this.addresses = data.addresses || [];
            localStorage.setItem('customer', JSON.stringify(data));
        } catch (e) {
            this.error = 'Failed to load account data';
            // Clear stale cached data on error
            this.customer = null;
            this.addresses = [];
            localStorage.removeItem('customer');
        }
        this.loading = false;
    },

    async loadOrders(page = 1) {
        if (!this.token) return;

        this.loading = true;
        const pageSize = 10;
        try {
            const data = await fetch('/api/customers/me/orders?page=' + page + '&pageSize=' + pageSize, {
                headers: { 'Authorization': 'Bearer ' + this.token }
            }).then(r => r.json());

            if (data.error) {
                throw new Error(data.message);
            }

            // Parse orders from the API response (handles various response formats)
            const orders = data.member || data['hydra:member'] || data.orders || data || [];
            this.orders = Array.isArray(orders) ? orders : [];
            this.ordersPage = page;

            // Calculate total pages from hydra:totalItems or fallback
            const totalItems = data['hydra:totalItems'] || data.totalItems || data.total || this.orders.length;
            this.ordersTotalPages = Math.ceil(totalItems / pageSize) || 1;
            this.ordersTotalItems = totalItems;
        } catch (e) {
            this.error = 'Failed to load orders';
        }
        this.loading = false;
    },

    async loadOrderInvoices(orderId) {
        if (!this.token || !orderId) return [];

        try {
            const data = await fetch('/api/customers/me/orders/' + orderId + '/invoices', {
                headers: { 'Authorization': 'Bearer ' + this.token }
            }).then(r => r.json());

            if (data.error) {
                console.error('Failed to load invoices:', data.message);
                return [];
            }

            return data.invoices || [];
        } catch (e) {
            console.error('Failed to load invoices:', e);
            return [];
        }
    },

    async downloadInvoice(orderId, invoiceId) {
        if (!this.token || !orderId || !invoiceId) {
            this.error = 'Unable to download invoice';
            return;
        }

        try {
            const response = await fetch('/api/customers/me/orders/' + orderId + '/invoices/' + invoiceId + '/pdf', {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to download invoice');
            }

            // Get filename from Content-Disposition header or use default
            const contentDisposition = response.headers.get('Content-Disposition');
            let filename = 'invoice.pdf';
            if (contentDisposition) {
                const match = contentDisposition.match(/filename="(.+?)"/);
                if (match) filename = match[1];
            }

            // Download the PDF
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.success = 'Invoice downloaded successfully';
        } catch (e) {
            this.error = e.message || 'Failed to download invoice';
        }
    },

    async viewOrderDetail(order) {
        this.selectedOrder = order;
        this.selectedOrderInvoices = [];
        this.loadingInvoices = true;

        try {
            this.selectedOrderInvoices = await this.loadOrderInvoices(order.id);
        } catch (e) {
            console.error('Failed to load invoices:', e);
        }

        this.loadingInvoices = false;
    },

    closeOrderDetail() {
        this.selectedOrder = null;
        this.selectedOrderInvoices = [];
    },

    async reorder(order) {
        this.loading = true;
        this.error = null;
        let addedCount = 0;

        try {
            // Ensure cart module is loaded and we have a cart
            await this.loadModule('cart');
            await this.ensureCart();

            // Add each item from the order to cart
            for (const item of order.items) {
                // Skip items that were fully canceled or refunded
                const qtyToAdd = parseInt(item.qtyOrdered) - parseInt(item.qtyCanceled || 0);
                if (qtyToAdd <= 0) continue;

                // Skip parent configurable products (only add children)
                if (item.productType === 'configurable') continue;

                try {
                    // Use the cart module's addToCart or direct API call
                    const res = await fetch(`/api/guest-carts/${this.cartId}/items`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(this.token && { 'Authorization': 'Bearer ' + this.token })
                        },
                        body: JSON.stringify({
                            sku: item.sku,
                            qty: qtyToAdd
                        })
                    });

                    if (res.ok) {
                        addedCount++;
                    } else {
                        const data = await res.json();
                        console.warn(`Could not add ${item.sku}:`, data.message || 'Unknown error');
                    }
                } catch (itemErr) {
                    console.warn(`Failed to add item ${item.sku}:`, itemErr);
                }
            }

            await this.loadCart();

            if (addedCount > 0) {
                this.success = `${addedCount} item${addedCount > 1 ? 's' : ''} added to cart`;
                this.navigate('cart');
            } else {
                this.error = 'Could not add any items to cart. Products may be out of stock.';
            }
        } catch (e) {
            this.error = e.message || 'Failed to reorder';
        }

        this.loading = false;
    },

    printOrder(order) {
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            this.error = 'Please allow popups to print the order';
            return;
        }

        const formatPrice = (price) => '$' + (parseFloat(price) || 0).toFixed(2);
        const formatDate = (date) => new Date(date).toLocaleDateString('en-AU', {
            day: 'numeric', month: 'long', year: 'numeric'
        });

        const formatAddress = (addr) => {
            if (!addr) return 'N/A';
            return `${addr.firstName} ${addr.lastName}<br>
                ${addr.street?.join(', ') || addr.street || ''}<br>
                ${addr.city}, ${addr.region || ''} ${addr.postcode}<br>
                ${addr.countryId}${addr.telephone ? '<br>T: ' + addr.telephone : ''}`;
        };

        const items = order.items.filter(i => i.productType !== 'configurable').map(item => `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                    ${item.name}<br>
                    <small style="color: #666;">SKU: ${item.sku}</small>
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">
                    ${formatPrice(item.priceInclTax || item.price)}
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: center;">
                    ${item.qtyOrdered}
                    ${item.qtyRefunded > 0 ? `<br><small style="color: #dc2626;">Refunded: ${item.qtyRefunded}</small>` : ''}
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">
                    ${formatPrice(item.rowTotalInclTax || item.rowTotal)}
                </td>
            </tr>
        `).join('');

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Order #${order.incrementId}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
                    h1 { margin-bottom: 5px; }
                    .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 14px; background: #f3f4f6; }
                    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
                    .box { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
                    .box h3 { margin-top: 0; font-size: 14px; color: #666; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th { background: #f9fafb; padding: 10px 8px; text-align: left; font-size: 12px; text-transform: uppercase; }
                    .totals { margin-top: 20px; text-align: right; }
                    .totals div { padding: 4px 0; }
                    .totals .total { font-size: 18px; font-weight: bold; border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <h1>Order #${order.incrementId}</h1>
                <p style="color: #666;">Order Date: ${formatDate(order.createdAt)}</p>
                <span class="status">${order.status}</span>

                <div class="grid">
                    <div class="box">
                        <h3>SHIPPING ADDRESS</h3>
                        ${formatAddress(order.shippingAddress)}
                    </div>
                    <div class="box">
                        <h3>BILLING ADDRESS</h3>
                        ${formatAddress(order.billingAddress)}
                    </div>
                </div>

                <div class="grid">
                    <div class="box">
                        <h3>SHIPPING METHOD</h3>
                        ${order.shippingDescription || 'N/A'}
                    </div>
                    <div class="box">
                        <h3>PAYMENT METHOD</h3>
                        ${order.paymentMethodTitle || order.paymentMethod || 'N/A'}
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items}
                    </tbody>
                </table>

                <div class="totals">
                    <div>Subtotal: ${formatPrice(order.prices?.subtotalInclTax || order.prices?.subtotal)}</div>
                    ${order.prices?.discountAmount ? `<div style="color: #16a34a;">Discount: -${formatPrice(Math.abs(order.prices.discountAmount))}</div>` : ''}
                    <div>Shipping: ${formatPrice(order.prices?.shippingAmountInclTax || order.prices?.shippingAmount)}</div>
                    ${order.prices?.giftcardAmount > 0 ? `<div style="color: #9333ea;">Gift Card: -${formatPrice(order.prices.giftcardAmount)}</div>` : ''}
                    <div>GST: ${formatPrice(order.prices?.taxAmount)}</div>
                    <div class="total">Grand Total: ${formatPrice(order.prices?.grandTotal)}</div>
                </div>

                <script>window.onload = () => window.print();</script>
            </body>
            </html>
        `);
        printWindow.document.close();
    },

    async addAddress() {
        if (!this.token) {
            this.error = 'Please log in to add an address';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            // Convert street string to array (API expects array)
            const addressData = {
                ...this.newAddress,
                street: this.newAddress.street ? this.newAddress.street.split('\n').filter(line => line.trim()) : []
            };
            // If only one line without newlines, wrap it in an array
            if (addressData.street.length === 0 && this.newAddress.street) {
                addressData.street = [this.newAddress.street.trim()];
            }

            const res = await fetch('/api/customers/me/addresses', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token
                },
                body: JSON.stringify(addressData)
            });

            const data = await res.json();

            if (res.status === 401) {
                this.logout();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || 'Failed to add address');
            }

            // API returns address directly (not wrapped in 'address' property)
            if (data.id) {
                this.addresses.push(data);
            }
            this.success = 'Address added successfully';
            this.showAddressForm = false;
            this.resetAddressForm();
        } catch (e) {
            this.error = e.message || 'Failed to add address';
        }
        this.loading = false;
    },

    resetAddressForm() {
        this.newAddress = {
            firstName: this.customer?.firstName || '',
            lastName: this.customer?.lastName || '',
            street: '',
            city: '',
            postcode: '',
            region: '',
            regionId: null,
            countryId: 'AU',
            telephone: '',
            isDefaultBilling: false,
            isDefaultShipping: false
        };
    },

    async useAddressForCheckout(address) {
        // Ensure countries are loaded before setting address
        if (this.countries.length === 0) {
            await this.loadCountries();
        }

        const shippingData = {
            firstName: address.firstName || '',
            lastName: address.lastName || '',
            street: Array.isArray(address.street) ? address.street.join(', ') : (address.street || ''),
            city: address.city || '',
            postcode: address.postcode || '',
            region: address.region || '',
            regionId: address.regionId || null,
            countryId: address.countryId || 'AU',
            telephone: address.telephone || ''
        };

        this.navigate('checkout');

        setTimeout(() => {
            this.checkout.shipping = shippingData;
        }, 50);

        this.success = 'Address selected for checkout';
    },

    get newAddressRegions() {
        if (!this.countries || !Array.isArray(this.countries)) return [];
        const country = this.countries.find(c => c.id === this.newAddress.countryId);
        return country?.available_regions || [];
    },

    editAddress(address) {
        this.editingAddressId = address.id;
        this.newAddress = {
            firstName: address.firstName || '',
            lastName: address.lastName || '',
            street: Array.isArray(address.street) ? address.street.join('\n') : (address.street || ''),
            city: address.city || '',
            postcode: address.postcode || '',
            region: address.region || '',
            regionId: address.regionId || null,
            countryId: address.countryId || 'AU',
            telephone: address.telephone || '',
            isDefaultBilling: address.isDefaultBilling || false,
            isDefaultShipping: address.isDefaultShipping || false
        };
        this.showAddressForm = true;
    },

    async updateAddress() {
        if (!this.token || !this.editingAddressId) return;

        this.loading = true;
        this.error = null;
        try {
            // Convert street string to array (API expects array)
            const addressData = {
                ...this.newAddress,
                street: this.newAddress.street ? this.newAddress.street.split('\n').filter(line => line.trim()) : []
            };
            // If only one line without newlines, wrap it in an array
            if (addressData.street.length === 0 && this.newAddress.street) {
                addressData.street = [this.newAddress.street.trim()];
            }

            const res = await fetch('/api/customers/me/addresses/' + this.editingAddressId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token
                },
                body: JSON.stringify(addressData)
            });

            const data = await res.json();

            if (res.status === 401) {
                this.clearAuth();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || 'Failed to update address');
            }

            // API returns address directly (not wrapped in 'address' property)
            const index = this.addresses.findIndex(a => a.id === this.editingAddressId);
            if (index !== -1 && data.id) {
                this.addresses[index] = data;
            }

            this.success = 'Address updated successfully';
            this.showAddressForm = false;
            this.editingAddressId = null;
            this.resetAddressForm();
        } catch (e) {
            this.error = e.message || 'Failed to update address';
        }
        this.loading = false;
    },

    async deleteAddress(addressId) {
        if (!this.token) return;
        if (!confirm('Are you sure you want to delete this address?')) return;

        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/api/customers/me/addresses/' + addressId, {
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + this.token
                }
            });

            const data = await res.json();

            if (res.status === 401) {
                this.clearAuth();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || 'Failed to delete address');
            }

            this.addresses = this.addresses.filter(a => a.id !== addressId);
            this.success = 'Address deleted';
        } catch (e) {
            this.error = e.message || 'Failed to delete address';
        }
        this.loading = false;
    },

    async saveAddress() {
        if (this.editingAddressId) {
            await this.updateAddress();
        } else {
            await this.addAddress();
        }
    },

    // Profile management (profileForm state is in store.js)
    initProfileForm() {
        if (this.customer) {
            this.profileForm = {
                firstName: this.customer.firstName || '',
                lastName: this.customer.lastName || '',
                email: this.customer.email || ''
            };
        }
    },

    async updateProfile() {
        if (!this.token) {
            this.error = 'Please log in to update your profile';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/api/customers/me', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token
                },
                body: JSON.stringify(this.profileForm)
            });

            const data = await res.json();

            if (res.status === 401) {
                this.logout();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || data.detail || 'Failed to update profile');
            }

            // Update local customer data
            this.customer = { ...this.customer, ...this.profileForm };
            localStorage.setItem('customer', JSON.stringify(this.customer));
            this.success = 'Profile updated successfully';
        } catch (e) {
            this.error = e.message || 'Failed to update profile';
        }
        this.loading = false;
    },

    // Password change (passwordForm state is in store.js)
    resetPasswordForm() {
        this.passwordForm = {
            currentPassword: '',
            newPassword: '',
            confirmPassword: ''
        };
    },

    async changePassword() {
        if (!this.token) {
            this.error = 'Please log in to change your password';
            return;
        }

        if (this.passwordForm.newPassword !== this.passwordForm.confirmPassword) {
            this.error = 'New passwords do not match';
            return;
        }

        if (this.passwordForm.newPassword.length < 8) {
            this.error = 'New password must be at least 8 characters';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/api/customers/me/password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token
                },
                body: JSON.stringify({
                    currentPassword: this.passwordForm.currentPassword,
                    newPassword: this.passwordForm.newPassword
                })
            });

            const data = await res.json();

            if (res.status === 401) {
                this.logout();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || data.detail || 'Failed to change password');
            }

            this.success = 'Password changed successfully';
            this.resetPasswordForm();
        } catch (e) {
            this.error = e.message || 'Failed to change password';
        }
        this.loading = false;
    },

    // Forgot password (forgotPasswordEmail state is in store.js)
    async forgotPassword() {
        if (!this.forgotPasswordEmail) {
            this.error = 'Please enter your email address';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/api/auth/forgot-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: this.forgotPasswordEmail })
            });

            const data = await res.json();

            // Always show success (API doesn't reveal if email exists)
            this.success = data.message || 'If an account exists with this email, a password reset link has been sent.';
            this.forgotPasswordEmail = '';
        } catch (e) {
            this.error = e.message || 'Failed to send reset email';
        }
        this.loading = false;
    },

    // Reset password (resetForm state is in store.js)
    async resetPassword() {
        if (!this.resetForm.email || !this.resetForm.token || !this.resetForm.newPassword) {
            this.error = 'Please fill in all fields';
            return;
        }

        if (this.resetForm.newPassword !== this.resetForm.confirmPassword) {
            this.error = 'Passwords do not match';
            return;
        }

        if (this.resetForm.newPassword.length < 8) {
            this.error = 'Password must be at least 8 characters';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            const res = await fetch('/api/auth/reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: this.resetForm.email,
                    token: this.resetForm.token,
                    newPassword: this.resetForm.newPassword
                })
            });

            const data = await res.json();

            if (data.error) {
                throw new Error(data.message || 'Failed to reset password');
            }

            this.success = 'Password has been reset. You can now log in with your new password.';
            this.resetForm = { email: '', token: '', newPassword: '', confirmPassword: '' };
            this.navigate('login');
        } catch (e) {
            this.error = e.message || 'Failed to reset password';
        }
        this.loading = false;
    },

    // Newsletter subscription toggle
    async toggleNewsletter(subscribe) {
        if (!this.token || !this.customer?.email) {
            this.error = 'Please log in to manage newsletter subscription';
            return;
        }

        this.loading = true;
        this.error = null;
        try {
            const endpoint = subscribe ? '/api/newsletter/subscribe' : '/api/newsletter/unsubscribe';
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + this.token
                },
                body: JSON.stringify({ email: this.customer.email })
            });

            const data = await res.json();

            if (res.status === 401) {
                this.logout();
                this.error = 'Session expired. Please log in again.';
                return;
            }

            if (data.error) {
                throw new Error(data.message || 'Newsletter operation failed');
            }

            // Update customer subscription status
            if (this.customer) {
                this.customer.isSubscribed = data.isSubscribed;
                localStorage.setItem('customer', JSON.stringify(this.customer));
            }

            this.success = data.message || (subscribe ? 'Subscribed to newsletter' : 'Unsubscribed from newsletter');
        } catch (e) {
            this.error = e.message || 'Newsletter operation failed';
            // Reload account data to get correct state
            await this.loadAccountData();
        }
        this.loading = false;
    }
};
