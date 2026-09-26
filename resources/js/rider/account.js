import { initAddressCascade } from '../shared/address-cascade.js';

const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initAccount = () => {
    const data = readPayload('rider-account-data') || {};

    initAddressCascade({
        regionSelect: document.getElementById('regionSelect'),
        provinceSelect: document.getElementById('provinceSelect'),
        municipalitySelect: document.getElementById('municipalitySelect'),
        barangaySelect: document.getElementById('barangaySelect'),
        municipalityField: document.getElementById('municipality-field'),
        savedRegion: data.selectedRegion || '',
        savedProvince: data.selectedProvince || '',
        savedMunicipality: data.selectedMunicipality || '',
        savedBarangay: data.selectedBarangay || '',
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccount, { once: true });
} else {
    initAccount();
}

export { initAccount };
