// SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
// SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
// SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
// SPDX-License-Identifier: AFL-3.0

class Translate {
    constructor(data) {
        this.data = new Map(Object.entries(data));
    }

    translate(text, ...args) {
        const translated = this.data.get(text) ?? text;
        return translated.replaceAll(/%[ds]/g, (match) => args.shift() ?? match);
    }

    add(keyOrObject, value) {
        if (arguments.length > 1) {
            this.data.set(keyOrObject, value);
        } else if (typeof keyOrObject == 'object') {
            Object.entries(keyOrObject).forEach(([key, value]) => {
                this.data.set(key, value);
            });
        }
    }
}
