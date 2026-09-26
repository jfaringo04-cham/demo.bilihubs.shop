const ncrRegionCode = '1300000000';

async function fetchJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

function populateSelect(select, items, savedValue, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.code;
        option.textContent = item.name;
        option.dataset.name = item.name;
        if (String(item.code) === String(savedValue || '') || item.name === savedValue) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function syncSelectedName(select, input) {
    if (!input) return;
    const option = select?.options[select.selectedIndex];
    input.value = option?.value ? option.dataset.name || option.textContent : '';
}

export async function initAddressCascade(config) {
    const {
        regionSelect,
        provinceSelect,
        municipalitySelect,
        barangaySelect,
        municipalityField,
        regionHidden,
        provinceHidden,
        municipalityHidden,
        barangayHidden,
        regionNameInput,
        provinceNameInput,
        municipalityNameInput,
        barangayNameInput,
        savedRegion = '',
        savedProvince = '',
        savedMunicipality = '',
        savedBarangay = '',
    } = config;

    if (!regionSelect || !provinceSelect || !municipalitySelect || !barangaySelect) return;

    regionSelect.addEventListener('change', () => {
        if (regionHidden) regionHidden.value = regionSelect.value;
        syncSelectedName(regionSelect, regionNameInput);
        populateSelect(provinceSelect, [], '', 'Select Province/City');
        populateSelect(municipalitySelect, [], '', 'Select Municipality');
        populateSelect(barangaySelect, [], '', 'Select Barangay');
        barangaySelect.disabled = true;
        municipalitySelect.disabled = true;
        provinceSelect.disabled = true;

        if (!regionSelect.value) return;

        provinceSelect.disabled = false;
        if (regionSelect.value === ncrRegionCode) {
            if (municipalityField) municipalityField.style.display = 'none';
            fetchJson(`/api/addresses/regions/${regionSelect.value}/cities`)
                .then((items) => {
                    populateSelect(provinceSelect, items, savedProvince, 'Select Province/City');
                    if (savedProvince) provinceSelect.dispatchEvent(new Event('change'));
                })
                .catch(() => {});
            return;
        }

        if (municipalityField) municipalityField.style.display = 'block';
        fetchJson(`/api/addresses/regions/${regionSelect.value}/provinces`)
            .then((items) => {
                populateSelect(provinceSelect, items, savedProvince, 'Select Province/City');
                if (savedProvince) provinceSelect.dispatchEvent(new Event('change'));
            })
            .catch(() => {});
    });

    provinceSelect.addEventListener('change', () => {
        if (provinceHidden) provinceHidden.value = provinceSelect.value;
        syncSelectedName(provinceSelect, provinceNameInput);
        populateSelect(municipalitySelect, [], '', 'Select Municipality');
        populateSelect(barangaySelect, [], '', 'Select Barangay');
        barangaySelect.disabled = true;
        municipalitySelect.disabled = true;

        if (!provinceSelect.value) return;

        const selectedProvince = provinceSelect.options[provinceSelect.selectedIndex];
        if (regionSelect.value === ncrRegionCode) {
            barangaySelect.disabled = false;
            municipalitySelect.disabled = false;
            if (municipalityField) municipalityField.style.display = 'none';
            if (selectedProvince) {
                municipalitySelect.innerHTML = '';
                const option = document.createElement('option');
                option.value = selectedProvince.value;
                option.textContent = selectedProvince.textContent;
                option.dataset.name = selectedProvince.dataset.name || selectedProvince.textContent;
                option.selected = true;
                municipalitySelect.appendChild(option);
                if (municipalityHidden) municipalityHidden.value = option.value;
                if (municipalityNameInput) municipalityNameInput.value = option.dataset.name;
            }
            fetchJson(`/api/addresses/cities/${provinceSelect.value}/barangays`)
                .then((items) => populateSelect(barangaySelect, items, savedBarangay, 'Select Barangay'))
                .catch(() => {});
            return;
        }

        if (municipalityField) municipalityField.style.display = 'block';
        municipalitySelect.disabled = false;
        fetchJson(`/api/addresses/provinces/${provinceSelect.value}/municipalities`)
            .then((items) => {
                populateSelect(municipalitySelect, items, savedMunicipality, 'Select Municipality');
                if (savedMunicipality) municipalitySelect.dispatchEvent(new Event('change'));
            })
            .catch(() => {});
    });

    municipalitySelect.addEventListener('change', () => {
        if (municipalityHidden) municipalityHidden.value = municipalitySelect.value;
        syncSelectedName(municipalitySelect, municipalityNameInput);
        populateSelect(barangaySelect, [], '', 'Select Barangay');
        barangaySelect.disabled = true;

        if (!municipalitySelect.value) return;

        barangaySelect.disabled = false;
        fetchJson(`/api/addresses/municipalities/${municipalitySelect.value}/barangays`)
            .then((items) => populateSelect(barangaySelect, items, savedBarangay, 'Select Barangay'))
            .catch(() => {});
    });

    barangaySelect.addEventListener('change', () => {
        if (barangayHidden) barangayHidden.value = barangaySelect.value;
        syncSelectedName(barangaySelect, barangayNameInput);
    });

    try {
        const regions = await fetchJson('/api/addresses/regions');
        populateSelect(regionSelect, regions, savedRegion, 'Select Region');
        syncSelectedName(regionSelect, regionNameInput);
        if (savedRegion) regionSelect.dispatchEvent(new Event('change'));
    } catch {
        populateSelect(regionSelect, [], '', 'Select Region');
    }
}
