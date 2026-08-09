/**
 * The application's own field definitions, borrowed by the tests.
 *
 * `contentFields.js`, `repeaters.js` and `constants.js` are pure data — no React, no JSX — so the
 * suite can import them and generate a test per field. Adding a field to a block therefore extends
 * coverage on its own, instead of being quietly untested until somebody remembers.
 *
 * The obvious objection is that a test derived from the schema agrees with the schema by
 * construction. What defends against that is what the generated tests assert: a typed value has to
 * survive a save and a page reload, which is true or false regardless of where the field list came
 * from. A wrong schema produces a test that fails, not one that lies.
 */
export { contentFieldsFor } from '../../resources/js/cms/builder/contentFields.js';
export { repeatersFor } from '../../resources/js/cms/builder/repeaters.js';
export { COMPONENT_LIBRARY } from '../../resources/js/cms/data/constants.js';
export { defaultSectionData } from '../../resources/js/sections/defaults.js';

/** Every type the library offers, flattened out of its groups. */
export function libraryItems(library) {
    return library.flatMap((group) => group.items.map((item) => ({ ...item, group: group.name })));
}

/** Flattens a content schema, so a group's fields are tested like any other. */
export function flatFields(fields) {
    return (fields || []).flatMap((field) => (field.group ? field.fields.map((f) => ({ ...f, within: field.title })) : [field]));
}
