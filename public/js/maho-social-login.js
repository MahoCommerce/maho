// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

(() => {
    'use strict';

    const SDK_URLS = {
        google: 'https://accounts.google.com/gsi/client',
        apple: 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js',
        facebook: 'https://connect.facebook.net/en_US/sdk.js',
    };
    const ONE_TAP_DISMISSED_KEY = 'mahoOneTapDismissed';
    const sdkPromises = new Map();

    function loadSdk(code) {
        if (!sdkPromises.has(code)) {
            sdkPromises.set(code, new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = SDK_URLS[code];
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error(`Failed to load ${code} SDK`));
                document.head.appendChild(script);
            }));
        }
        return sdkPromises.get(code);
    }

    class MahoSocialLogin {
        constructor(container) {
            this.container = container;
            this.config = JSON.parse(container.dataset.config);
            this.googleNonce = null;
        }

        provider(code) {
            return this.config.providers.find((provider) => provider.code === code);
        }

        async fetchNonce() {
            const result = await mahoFetch(this.config.nonceUrl, { method: 'POST', loaderArea: false });
            return result.nonce;
        }

        init() {
            for (const button of this.container.querySelectorAll('[data-provider]')) {
                button.addEventListener('click', () => {
                    const code = button.dataset.provider;
                    const login = code === 'apple' ? this.loginApple() : this.loginFacebook();
                    login.catch((error) => this.showError(error));
                });
            }
            if (this.provider('google')) {
                this.initGoogle().catch((error) => console.error('Google Sign-In init failed:', error));
            }
        }

        async initGoogle() {
            const target = this.container.querySelector('.social-login-google-button');
            if (!target) {
                return;
            }
            const [, nonce] = await Promise.all([loadSdk('google'), this.fetchNonce()]);
            this.initializeGoogle(nonce);
            this.renderGoogleButton(target);
            this.maybePromptOneTap();
        }

        initializeGoogle(nonce) {
            this.googleNonce = nonce;
            google.accounts.id.initialize({
                client_id: this.provider('google').clientId,
                nonce,
                auto_select: false,
                callback: (response) => {
                    this.submit('google', response.credential, { nonce: this.googleNonce })
                        .catch((error) => this.showError(error));
                },
            });
        }

        renderGoogleButton(target) {
            // The observer always fires once after observe(); a hidden container
            // (e.g. an inactive tab) has zero width then, so the single render
            // happens as soon as the container is actually visible
            const observer = new ResizeObserver(() => {
                if (target.clientWidth > 0 && !target.dataset.rendered) {
                    target.dataset.rendered = '1';
                    const width = Math.min(Math.max(Math.floor(target.clientWidth), 200), 400);
                    target.replaceChildren();
                    google.accounts.id.renderButton(target, {
                        theme: 'outline',
                        size: 'large',
                        text: 'continue_with',
                        width,
                    });
                    observer.disconnect();
                }
            });
            observer.observe(target);
        }

        maybePromptOneTap() {
            const oneTap = this.provider('google')?.oneTap
                && !window.matchMedia('(max-width: 767px)').matches
                && !localStorage.getItem(ONE_TAP_DISMISSED_KEY);
            if (!oneTap) {
                return;
            }
            google.accounts.id.prompt((moment) => {
                if (moment.isSkippedMoment?.() || moment.isDismissedMoment?.()) {
                    localStorage.setItem(ONE_TAP_DISMISSED_KEY, '1');
                }
            });
        }

        async loginApple() {
            const [, nonce] = await Promise.all([loadSdk('apple'), this.fetchNonce()]);
            // Derive from the store's login URL so base paths and store codes
            // in URLs are respected; strip any query (SID) and trailing slash
            const loginUrl = new URL(this.config.loginUrl, window.location.origin);
            AppleID.auth.init({
                clientId: this.provider('apple').serviceId,
                scope: 'name email',
                redirectURI: loginUrl.origin + loginUrl.pathname.replace(/\/$/, ''),
                usePopup: true,
                nonce,
            });
            const response = await AppleID.auth.signIn();
            const extra = { nonce };
            // Apple sends the user's name only in the first authorization response
            if (response.user?.name) {
                extra.firstname = response.user.name.firstName ?? '';
                extra.lastname = response.user.name.lastName ?? '';
            }
            await this.submit('apple', response.authorization.id_token, extra);
        }

        async loginFacebook() {
            await loadSdk('facebook');
            if (!this.fbInitialized) {
                FB.init({
                    appId: this.provider('facebook').appId,
                    cookie: true,
                    xfbml: false,
                    version: 'v19.0',
                });
                this.fbInitialized = true;
            }
            const authResponse = await new Promise((resolve) => {
                FB.login((response) => resolve(response.authResponse), { scope: 'email,public_profile' });
            });
            if (!authResponse?.accessToken) {
                return;
            }
            await this.submit('facebook', authResponse.accessToken);
        }

        async submit(provider, token, extra = {}) {
            // The post-login destination comes from the session's before-auth
            // URL on the server, same as the regular login form
            const body = new URLSearchParams({ provider, token, ...extra });
            try {
                const result = await mahoFetch(this.config.loginUrl, { method: 'POST', body });
                window.location.href = result.redirect;
            } catch (error) {
                if (provider === 'google') {
                    this.refreshGoogleNonce();
                }
                throw error;
            }
        }

        refreshGoogleNonce() {
            this.fetchNonce().then((nonce) => this.initializeGoogle(nonce)).catch(() => {});
        }

        showError(error) {
            const target = this.container.querySelector('.social-login-error');
            if (!target) {
                return;
            }
            const message = error instanceof MahoError ? error.message : this.config.strings.errorGeneric;
            target.textContent = message;
            target.hidden = false;
        }
    }

    function initAll() {
        for (const container of document.querySelectorAll('.social-login-buttons[data-config]')) {
            if (!container.dataset.socialLoginReady) {
                container.dataset.socialLoginReady = '1';
                new MahoSocialLogin(container).init();
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
