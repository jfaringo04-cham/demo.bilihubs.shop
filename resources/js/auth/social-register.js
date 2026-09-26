import { initAddressCascade } from '../shared/address-cascade.js';
import { initAgeFromBirthday } from '../shared/age-from-birthday.js';

const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initSocialRegister = () => {
    const data = readPayload('auth-page-data') || {};
    const roleSelect = document.getElementById('role');
    const sellerSection = document.getElementById('seller-section');
    const businessPermitField = document.getElementById('business-permit-field');

    const updateRoleSections = () => {
        const seller = roleSelect?.value === 'seller';
        sellerSection?.classList.toggle('d-none', !seller);
        businessPermitField?.classList.toggle('d-none', !seller);
    };
    roleSelect?.addEventListener('change', updateRoleSections);
    updateRoleSections();

    initAgeFromBirthday();
    initAddressCascade({
        regionSelect: document.getElementById('region'),
        provinceSelect: document.getElementById('province'),
        municipalitySelect: document.getElementById('municipality'),
        barangaySelect: document.getElementById('barangay'),
        municipalityField: document.getElementById('municipality-field'),
        regionNameInput: document.getElementById('region_name'),
        provinceNameInput: document.getElementById('province_name'),
        municipalityNameInput: document.getElementById('municipality_name'),
        barangayNameInput: document.getElementById('barangay_name'),
        savedRegion: data.region || '',
        savedProvince: data.province || '',
        savedMunicipality: data.municipality || '',
        savedBarangay: data.barangay || '',
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSocialRegister, { once: true });
} else {
    initSocialRegister();
}

export { initSocialRegister };
