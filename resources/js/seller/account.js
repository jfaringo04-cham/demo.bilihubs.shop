import { initAddressCascade } from '../shared/address-cascade.js';

document.addEventListener('DOMContentLoaded', function () {
  function parseJsonElement(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent);
    } catch {
      return null;
    }
  }

  const data = parseJsonElement('seller-account-data');

  const birthdayInput = document.querySelector('input[name="birthday"]:not([hidden])');
  const ageInput = document.querySelector('input[name="age"][hidden]');
  if (birthdayInput && ageInput) {
    birthdayInput.addEventListener('change', function () {
      const birthday = new Date(this.value);
      const today = new Date();
      let age = today.getFullYear() - birthday.getFullYear();
      const monthDiff = today.getMonth() - birthday.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
        age--;
      }
      ageInput.value = age >= 0 ? age : '';
    });
  }

  const regionSelect = document.getElementById('region');
  const provinceSelect = document.getElementById('province');
  const municipalitySelect = document.getElementById('municipality');
  const barangaySelect = document.getElementById('barangay');
  const municipalityField = document.getElementById('municipality-field');
  const regionHidden = document.getElementById('region-hidden');
  const provinceHidden = document.getElementById('province-hidden');
  const municipalityHidden = document.getElementById('municipality-hidden');
  const barangayHidden = document.getElementById('barangay-hidden');

  const saved = data?.saved ?? {
    region: regionSelect?.dataset.saved ?? '',
    province: provinceSelect?.dataset.saved ?? '',
    municipality: municipalitySelect?.dataset.saved ?? '',
    barangay: barangaySelect?.dataset.saved ?? '',
  };

  initAddressCascade({
    regionSelect,
    provinceSelect,
    municipalitySelect,
    barangaySelect,
    municipalityField,
    regionHidden,
    provinceHidden,
    municipalityHidden,
    barangayHidden,
    savedRegion: saved.region,
    savedProvince: saved.province,
    savedMunicipality: saved.municipality,
    savedBarangay: saved.barangay,
  });
});