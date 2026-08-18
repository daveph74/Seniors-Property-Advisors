export const PASSWORD_MINIMUM = 10;

export const PASSWORD_RULES = [
    { id: 'length', label: `At least ${PASSWORD_MINIMUM} characters long`, test: (v) => v.length >= PASSWORD_MINIMUM },
    { id: 'upper', label: 'At least one uppercase letter', test: (v) => /\p{Lu}/u.test(v) },
    { id: 'lower', label: 'At least one lowercase letter', test: (v) => /\p{Ll}/u.test(v) },
    { id: 'number', label: 'At least one number', test: (v) => /\p{N}/u.test(v) },
    { id: 'symbol', label: 'At least one special character', test: (v) => /[\p{Z}\p{S}\p{P}]/u.test(v) },
];

export function passwordMeetsPolicy(value) {
    return PASSWORD_RULES.every((rule) => rule.test(value ?? ''));
}
