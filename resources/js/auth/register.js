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

const initRoleSections = () => {
    const roleSelect = document.getElementById('role');
    const sellerSection = document.getElementById('seller-section');
    const logisticSection = document.getElementById('logistic-section');
    const businessPermitField = document.getElementById('business-permit-field');
    if (!roleSelect) return;

    const updateSections = () => {
        sellerSection?.classList.toggle('d-none', roleSelect.value !== 'seller');
        logisticSection?.classList.toggle('d-none', roleSelect.value !== 'logistic');
        businessPermitField?.classList.toggle('d-none', roleSelect.value !== 'seller');
    };

    roleSelect.addEventListener('change', updateSections);
    updateSections();
};

const initGeocoding = (geocodeKey) => {
    const addressInput = document.getElementById('api_address');
    const regionSelect = document.getElementById('region');
    const regionNameInput = document.getElementById('region_name');
    if (!addressInput || !regionSelect || !geocodeKey) return;

    addressInput.addEventListener('blur', async () => {
        const address = addressInput.value.trim();
        if (!address) return;

        try {
            const response = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(address)}&key=${encodeURIComponent(geocodeKey)}`);
            const data = await response.json();
            if (data.status !== 'OK' || !data.results?.length) return;

            const regionName = data.results[0].address_components
                .find((component) => component.types.includes('administrative_area_level_1'))
                ?.long_name;
            if (!regionName) return;

            const regions = await fetch('/api/addresses/regions').then((result) => result.json());
            const match = regions.find((region) => region.name.toLowerCase().includes(regionName.toLowerCase()) || regionName.toLowerCase().includes(region.name.toLowerCase()));
            if (!match) return;

            regionSelect.value = match.code;
            if (regionNameInput) regionNameInput.value = match.name;
            regionSelect.dispatchEvent(new Event('change'));
        } catch {
        }
    });
};

const initSubmitLockForRegister = () => initSubmitLock('registerForm', 'Submitting...');

const initRegister = () => {
    const data = readPayload('auth-page-data') || {};
    initSubmitLockForRegister();
    initRoleSections();
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
    initGeocoding(data.geocodeKey || '');
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegister, { once: true });
} else {
    initRegister();
}

export { initRegister };
