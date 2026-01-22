/**
 * Maho Newsletter Toggle Component
 *
 * iOS-style toggle switch for newsletter subscription using the REST API.
 *
 * Usage:
 *   <div id="newsletter-toggle" data-email="user@example.com"></div>
 *   <script src="/js/maho/newsletter-toggle.js"></script>
 *   <script>
 *     new NewsletterToggle('newsletter-toggle', {
 *       email: 'user@example.com', // Optional, can also use data-email
 *       authToken: 'Bearer xxx',   // Optional, for authenticated users
 *       onSubscribe: (data) => console.log('Subscribed:', data),
 *       onUnsubscribe: (data) => console.log('Unsubscribed:', data),
 *       onError: (error) => console.error('Error:', error)
 *     });
 *   </script>
 *
 * @copyright Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license   https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class NewsletterToggle {
    constructor(elementId, options = {}) {
        this.container = document.getElementById(elementId);
        if (!this.container) {
            console.error(`NewsletterToggle: Element #${elementId} not found`);
            return;
        }

        this.options = {
            email: options.email || this.container.dataset.email || '',
            authToken: options.authToken || null,
            apiBase: options.apiBase || '/api',
            labels: {
                subscribed: options.labels?.subscribed || 'Subscribed',
                unsubscribed: options.labels?.unsubscribed || 'Unsubscribe',
                loading: options.labels?.loading || 'Loading...',
                subscribe: options.labels?.subscribe || 'Subscribe',
            },
            onSubscribe: options.onSubscribe || (() => {}),
            onUnsubscribe: options.onUnsubscribe || (() => {}),
            onError: options.onError || ((error) => console.error('Newsletter error:', error)),
            onStatusChange: options.onStatusChange || (() => {}),
        };

        this.isSubscribed = false;
        this.isLoading = false;

        this.render();
        this.init();
    }

    render() {
        this.container.innerHTML = `
            <div class="newsletter-toggle-wrapper">
                <label class="newsletter-toggle">
                    <input type="checkbox" class="newsletter-toggle-input" disabled>
                    <span class="newsletter-toggle-slider"></span>
                </label>
                <span class="newsletter-toggle-label">${this.options.labels.loading}</span>
            </div>
            <style>
                .newsletter-toggle-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                }

                .newsletter-toggle {
                    position: relative;
                    display: inline-block;
                    width: 51px;
                    height: 31px;
                    flex-shrink: 0;
                }

                .newsletter-toggle-input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .newsletter-toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #e9e9eb;
                    transition: background-color 0.3s ease, opacity 0.3s ease;
                    border-radius: 31px;
                }

                .newsletter-toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 27px;
                    width: 27px;
                    left: 2px;
                    bottom: 2px;
                    background-color: white;
                    transition: transform 0.3s ease;
                    border-radius: 50%;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                }

                .newsletter-toggle-input:checked + .newsletter-toggle-slider {
                    background-color: #34c759;
                }

                .newsletter-toggle-input:checked + .newsletter-toggle-slider:before {
                    transform: translateX(20px);
                }

                .newsletter-toggle-input:disabled + .newsletter-toggle-slider {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .newsletter-toggle-label {
                    font-size: 14px;
                    color: #333;
                    user-select: none;
                }

                .newsletter-toggle-wrapper.loading .newsletter-toggle-label {
                    color: #999;
                }

                .newsletter-toggle-message {
                    margin-top: 8px;
                    font-size: 13px;
                    color: #666;
                }

                .newsletter-toggle-message.success {
                    color: #34c759;
                }

                .newsletter-toggle-message.error {
                    color: #ff3b30;
                }
            </style>
        `;

        this.checkbox = this.container.querySelector('.newsletter-toggle-input');
        this.label = this.container.querySelector('.newsletter-toggle-label');
        this.wrapper = this.container.querySelector('.newsletter-toggle-wrapper');
    }

    async init() {
        // If we have an auth token, fetch the current status
        if (this.options.authToken) {
            await this.fetchStatus();
        } else if (this.options.email) {
            // For guests, we can't check status via API, so start as unsubscribed
            this.setSubscribed(false);
            this.setLoading(false);
        } else {
            this.setLoading(false);
        }

        // Add event listener
        this.checkbox.addEventListener('change', () => this.handleToggle());
    }

    async fetchStatus() {
        this.setLoading(true);
        try {
            const response = await mahoFetch(`${this.options.apiBase}/newsletter/status`, {
                headers: this.getHeaders(),
            });

            if (!response.ok) {
                throw new Error('Failed to fetch newsletter status');
            }

            const data = await response.json();
            this.setSubscribed(data.isSubscribed);
            this.options.email = data.email || this.options.email;
        } catch (error) {
            this.options.onError(error);
            this.setSubscribed(false);
        } finally {
            this.setLoading(false);
        }
    }

    async handleToggle() {
        if (this.isLoading) return;

        const newState = this.checkbox.checked;
        this.setLoading(true);

        try {
            const endpoint = newState ? 'subscribe' : 'unsubscribe';
            const response = await mahoFetch(`${this.options.apiBase}/newsletter/${endpoint}`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({ email: this.options.email }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Newsletter operation failed');
            }

            this.setSubscribed(data.isSubscribed);
            this.showMessage(data.message, 'success');

            if (data.isSubscribed) {
                this.options.onSubscribe(data);
            } else {
                this.options.onUnsubscribe(data);
            }
            this.options.onStatusChange(data.isSubscribed, data);
        } catch (error) {
            // Revert the checkbox state
            this.checkbox.checked = !newState;
            this.showMessage(error.message, 'error');
            this.options.onError(error);
        } finally {
            this.setLoading(false);
        }
    }

    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        if (this.options.authToken) {
            headers['Authorization'] = this.options.authToken;
        }

        return headers;
    }

    setLoading(loading) {
        this.isLoading = loading;
        this.checkbox.disabled = loading;
        this.wrapper.classList.toggle('loading', loading);

        if (loading) {
            this.label.textContent = this.options.labels.loading;
        } else {
            this.updateLabel();
        }
    }

    setSubscribed(subscribed) {
        this.isSubscribed = subscribed;
        this.checkbox.checked = subscribed;
        this.updateLabel();
    }

    updateLabel() {
        this.label.textContent = this.isSubscribed
            ? this.options.labels.subscribed
            : this.options.labels.subscribe;
    }

    showMessage(message, type = 'info') {
        // Remove existing message
        const existingMessage = this.container.querySelector('.newsletter-toggle-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Add new message
        const messageEl = document.createElement('div');
        messageEl.className = `newsletter-toggle-message ${type}`;
        messageEl.textContent = message;
        this.wrapper.after(messageEl);

        // Auto-remove after 5 seconds
        setTimeout(() => messageEl.remove(), 5000);
    }

    // Public methods for external control
    subscribe() {
        if (!this.isSubscribed && !this.isLoading) {
            this.checkbox.checked = true;
            this.handleToggle();
        }
    }

    unsubscribe() {
        if (this.isSubscribed && !this.isLoading) {
            this.checkbox.checked = false;
            this.handleToggle();
        }
    }

    getStatus() {
        return {
            isSubscribed: this.isSubscribed,
            email: this.options.email,
            isLoading: this.isLoading,
        };
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NewsletterToggle;
}
