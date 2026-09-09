export const passwordRules = [
    {
        id: 'min',
        label: 'At least 12 characters',
        test: (password) => password.length >= 12,
    },
    {
        id: 'mixed',
        label: 'Uppercase and lowercase letters',
        test: (password) => /(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u.test(password),
    },
    {
        id: 'letters',
        label: 'At least one letter',
        test: (password) => /\p{L}/u.test(password),
    },
    {
        id: 'numbers',
        label: 'At least one number',
        test: (password) => /\p{N}/u.test(password),
    },
    {
        id: 'symbols',
        label: 'At least one symbol',
        test: (password) => /\p{Z}|\p{S}|\p{P}/u.test(password),
    },
];

export function unmetPasswordRules(password) {
    if (!password) {
        return [];
    }

    return passwordRules.filter((rule) => !rule.test(password));
}

export function passwordMeetsRequirements(password) {
    return password.length > 0 && unmetPasswordRules(password).length === 0;
}

export function passwordsMatch(password, confirmation) {
    return password === confirmation;
}
