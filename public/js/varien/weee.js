// SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
// SPDX-FileCopyrightText: 2023 The OpenMage Contributors <https://openmage.org>
// SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
// SPDX-License-Identifier: AFL-3.0
function taxToggle(detailsId, switcherId, expandedClassName) {
    const detailsElement = document.getElementById(detailsId);
    const switcherElement = document.getElementById(switcherId);

    if (!detailsElement || !switcherElement) {
        console.error('Required elements not found');
        return;
    }

    const isCurrentlyHidden = detailsElement.style.display === 'none';
    detailsElement.style.display = isCurrentlyHidden ? 'block' : 'none';
    switcherElement.classList.toggle(expandedClassName, isCurrentlyHidden);
}
