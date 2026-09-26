import { initAgeFromBirthday } from '../shared/age-from-birthday.js';

const initRiders = () => {
    initAgeFromBirthday();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRiders, { once: true });
} else {
    initRiders();
}

export { initRiders };
