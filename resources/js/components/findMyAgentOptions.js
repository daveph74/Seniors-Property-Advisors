/**
 * The answers Agent Finder offers.
 *
 * A mirror of `app/Enquiries/FindMyAgentOptions.php`, which is the one that decides — the server
 * validates against it and the CMS reads its labels. `FindMyAgentOptionsParityTest` fails if these
 * two drift, because nothing else would notice.
 *
 * `value` is what gets stored and must not change. It used to be the card's position in this array,
 * which made reordering the cards a silent rewrite of every answer already in the database.
 */
export const PROPERTY_TYPES = [
    { value: 'house', label: 'House', note: 'Free standing' },
    { value: 'townhouse', label: 'Townhouse', note: 'Attached / villa' },
    { value: 'apartment', label: 'Apartment', note: 'Unit / strata' },
    { value: 'acreage', label: 'Acreage', note: 'Rural / lifestyle' },
];

export const TIMELINES = [
    { value: 'within_3_months', label: 'Within 3 months' },
    { value: 'in_3_6_months', label: 'In 3 – 6 months' },
    { value: 'in_6_12_months', label: 'In 6 – 12 months' },
    { value: 'just_exploring', label: 'Just exploring' },
];

export const BEST_TIMES = [
    { value: 'morning', label: 'Morning' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'evening', label: 'Evening' },
];

export function labelFor(options, value) {
    return options.find((o) => o.value === value)?.label ?? null;
}
