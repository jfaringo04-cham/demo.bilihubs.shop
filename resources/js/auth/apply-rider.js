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

const initRiderPolicies = (policies) => {
    const companySelect = document.getElementById('logistic_id');
    const details = document.getElementById('riderPolicyDetails');
    const message = document.getElementById('riderPolicyMessage');
    const agreement = document.getElementById('accept_rider_policy');
    const version = document.getElementById('rider_policy_version');
    const terms = document.getElementById('riderTermsText');
    const privacy = document.getElementById('riderPrivacyText');

    const update = () => {
        const policy = companySelect.value ? policies[companySelect.value] : null;
        const available = Boolean(policy?.version);

        agreement.checked = false;
        agreement.disabled = !available;
        version.value = available ? policy.version : '';
        terms.textContent = available ? policy.terms : '';
        privacy.textContent = available ? policy.privacy : '';
        details.hidden = !available;
        details.querySelectorAll('details').forEach((panel) => { panel.open = false; });

        if (available) {
            message.textContent = `Review the policies for ${policy.name} before applying.`;
        } else {
            message.textContent = companySelect.value
                ? 'This company has not published its rider policies yet.'
                : 'Select a logistics company to view its rider policies.';
        }
    };

    companySelect.addEventListener('change', update);
    update();
};

const initRiderPasswordToggles = () => {
    document.querySelectorAll('#riderApplyForm [data-rider-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const icon = button.querySelector('i');
        const label = button.dataset.riderPasswordToggle;
        button.hidden = false;

        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${label}`);
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
        });
    });
};

const initRiderPasswordFeedback = () => {
    const password = document.getElementById('password');
    const confirmation = document.getElementById('password_confirmation');
    const rules = document.getElementById('riderPasswordRules').querySelectorAll('[data-password-rule]');
    const progress = document.getElementById('riderPasswordProgress');
    const match = document.getElementById('riderPasswordMatch');

    const update = () => {
        const value = password.value;
        const checks = {
            length: [...value].length >= 8,
            case: /\p{Ll}.*\p{Lu}|\p{Lu}.*\p{Ll}/u.test(value),
            number: /\p{N}/u.test(value),
            symbol: /[\p{Z}\p{S}\p{P}]/u.test(value),
        };
        let met = 0;

        rules.forEach((rule) => {
            const passed = checks[rule.dataset.passwordRule];
            rule.querySelector('[data-password-check]').hidden = !passed;
            rule.classList.toggle('text-success', passed);
            rule.classList.toggle('text-muted', !passed);
            if (passed) met++;
        });

        const progressMessage = `${met} of ${rules.length} password requirements met.`;
        if (progress.textContent !== progressMessage) progress.textContent = progressMessage;

        const hasConfirmation = confirmation.value !== '';
        const matches = hasConfirmation && value === confirmation.value;
        match.classList.toggle('text-success', matches);
        match.classList.toggle('text-danger', hasConfirmation && !matches);
        const matchMessage = !hasConfirmation ? '' : matches ? 'Passwords match.' : 'Passwords do not match.';
        if (match.textContent !== matchMessage) match.textContent = matchMessage;
    };

    password.addEventListener('input', update);
    confirmation.addEventListener('input', update);
    update();
};

const initApplyRider = () => {
    const data = readPayload('auth-page-data') || {};
    initSubmitLock('riderApplyForm', 'Submitting...');
    initRiderPasswordFeedback();
    initRiderPasswordToggles();
    initRiderPolicies(data.riderPolicies || {});
    document.getElementById('riderApplicationErrors')?.focus();
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
