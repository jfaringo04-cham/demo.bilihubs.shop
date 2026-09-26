import { initAddressCascade } from '../shared/address-cascade.js';
import { initAgeFromBirthday } from '../shared/age-from-birthday.js';
import { initSubmitLock } from '../shared/submit-lock.js';

const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initApplyRider = () => {
    const data = readPayload('auth-page-data') || {};
    initSubmitLock('riderApplyForm', 'Submitting...');
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
    document.addEventListener('DOMContentLoaded', initApplyRider, { once: true });
} else {
    initApplyRider();
}

export { initApplyRider };
