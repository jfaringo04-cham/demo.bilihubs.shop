const calculateAge = (value) => {
    const birthday = new Date(value);
    if (!value || Number.isNaN(birthday.getTime())) return '';

    const today = new Date();
    let age = today.getFullYear() - birthday.getFullYear();
    const monthDifference = today.getMonth() - birthday.getMonth();
    if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthday.getDate())) {
        age -= 1;
    }

    return age >= 0 ? String(age) : '';
};

const initAgeFromBirthday = (birthdayInput = document.getElementById('birthday'), ageInput = document.getElementById('age')) => {
    if (!birthdayInput || !ageInput) return;

    birthdayInput.addEventListener('change', () => {
        ageInput.value = calculateAge(birthdayInput.value);
    });
};

export { calculateAge, initAgeFromBirthday };
