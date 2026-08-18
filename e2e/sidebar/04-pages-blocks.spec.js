import { expect, test } from '../fixtures.js';
import * as B from '../support/builder.js';
import { uniqueValue } from '../support/unique.js';
import {
    COMPONENT_LIBRARY, contentFieldsFor, flatFields, libraryItems,
} from '../support/schema.js';

/**
 * Every block the library offers, and every field its settings panel renders.
 *
 * The list is not written out here — it is read from the application's own schema
 * (`contentFields.js`, `COMPONENT_LIBRARY`), so a field added to a block is covered the day it is
 * added rather than the day somebody remembers to write a test.
 *
 * The obvious objection is that a test generated from the schema agrees with the schema by
 * construction. What answers it is the assertion: a typed value has to survive **a save and a
 * reload**, which is true or false regardless of where the field list came from. A wrong schema
 * gives a failing test, not a lying one.
 *
 * Slow by nature — a save and a reload per block. Tagged @deep so `npm run e2e:fast` can skip it.
 */
const ITEMS = libraryItems(COMPONENT_LIBRARY);

/* Containers are covered by the builder spec: they hold blocks rather than carrying content. */
const BLOCKS = ITEMS.filter((item) => ! ['section', 'row', 'column'].includes(item.type));

/** The fixture page every block is added to and removed from. */
const FIXTURE = 'Contact';

test.describe('Pages · Blocks', () => {
    test('the library offers every block type the server accepts', async () => {
        /* A type added to the server without a library entry would go untested here and nobody
           would notice, so the count is pinned. `column` is deliberately absent from the library:
           columns only ever arrive with a row. */
        expect(ITEMS.length, ITEMS.map((i) => i.type).join(', ')).toBe(33);
        expect(ITEMS.filter((i) => i.type === 'column')).toHaveLength(0);
    });

    for (const item of BLOCKS) {
        const fields = flatFields(contentFieldsFor(item.type));

        test.describe(item.label, () => {
            test(`@deep adds, renders and deletes`, async ({ page }) => {
                await B.openBuilder(page, FIXTURE);
                await B.addBlock(page, item.label);

                await expect(B.selectedBlock(page)).toBeVisible();
                await expect(B.canvas(page).locator('.cms-block__label-tag').first())
                    .toContainText(item.label);

                await B.saveDraft(page);
                await B.deleteSelected(page);
                await B.saveDraft(page);
            });

            /* Types without a schema fall through to the legacy field chain; they are covered by
               the add/delete test above and by the builder spec's own field cases. */
            if (fields.length === 0) return;


            test(`@deep keeps every content field through a save`, async ({ page }) => {
                await B.openBuilder(page, FIXTURE);
                await B.addBlock(page, item.label);

                const typed = new Map();
                const picked = new Set();
                /* Something the block actually renders as text, to find it again after a reload.
                   An image URL is not it — the block draws the picture, not the address. */
                let marker = null;

                for (const f of fields) {
                    const scope = { within: f.within };
                    const control = B.input(page, f.label, scope);

                    if (await control.count() === 0) continue;

                    if (f.type === 'text' || f.type === 'textarea') {
                        const value = uniqueValue(f.label);
                        await B.fillField(page, f.label, value, scope);
                        typed.set(`${f.within || ''}|${f.label}`, value);
                        marker = marker ?? value;
                    }

                    if (f.type === 'number') {
                        await B.fillField(page, f.label, '3', scope);
                        typed.set(`${f.within || ''}|${f.label}`, '3');
                    }

                    if (f.type === 'select') {
                        const values = await control.locator('option').evaluateAll(
                            (options) => options.map((o) => o.value).filter(Boolean),
                        );

                        if (values.length > 0) {
                            await control.selectOption(values[values.length - 1]);
                            typed.set(`${f.within || ''}|${f.label}`, values[values.length - 1]);
                        }
                    }

                    /* Chosen from the library, because the address box is gone. Filling it by label is
                       not an option and skipping it is not either: `B.input` would now hand back the
                       "Describe the image" box in the same `.cms-field`, where a typed path round-trips
                       perfectly and proves nothing — and a block like `image` renders its caption only
                       beside a picture, so leaving the picture out left nothing on the canvas to find
                       the block by afterwards. */
                    if (f.type === 'image') {
                        await B.chooseImage(page, f.label, scope);
                        picked.add(`${f.within || ''}|${f.label}`);
                    }
                }

                expect(typed.size + picked.size, `${item.label} had no fillable field`).toBeGreaterThan(0);

                await B.saveAndReload(page);

                if (marker) await B.selectBlock(page, marker);
                else await B.selectLastBlock(page);

                for (const [key, value] of typed) {
                    const [within, label] = key.split('|');
                    const scope = { within: within || null };

                    await expect(B.input(page, label, scope), `${item.label} · ${label}`)
                        .toHaveValue(value);
                }

                for (const key of picked) {
                    const [within, label] = key.split('|');
                    const row = B.field(page, label, { within: within || null })
                        .locator('.cms-media-pick-row__name');

                    await expect(row, `${item.label} · ${label}`).not.toHaveText('No image yet');
                }

                await B.deleteSelected(page);
                await B.saveDraft(page);
            });

            const toggles = fields.filter((f) => f.type === 'toggle');

            if (toggles.length > 0) {
                test(`@deep keeps every switch through a save`, async ({ page }) => {
                    await B.openBuilder(page, FIXTURE);
                    await B.addBlock(page, item.label);

                    const flipped = [];

                    for (const f of toggles) {
                        const control = B.field(page, f.label, { within: f.within })
                            .getByRole('switch');

                        if (await control.count() === 0) continue;

                        const was = await control.getAttribute('aria-checked');
                        await control.click();
                        await expect(control).toHaveAttribute('aria-checked', was === 'true' ? 'false' : 'true');

                        flipped.push([f, was === 'true' ? 'false' : 'true']);
                    }

                    /* Some switches are rendered only once their block is configured — a link's
                       "Show arrow" needs the link — so finding none is not automatically a fault.
                       What is not acceptable is a skip that does not say what it looked for: this
                       message read "no switch rendered" for five blocks that render one, and the
                       reason was a locator in the helper rather than anything in the application. */
                    test.skip(
                        flipped.length === 0,
                        `no switch rendered for ${item.label} — looked for ${toggles.map((f) => f.label).join(', ')}`,
                    );

                    /* A switch alone gives nothing to find the block by after a reload. */
                    const marker = uniqueValue('Marker');
                    const heading = B.input(page, 'Heading');
                    if (await heading.count() > 0) await B.fillField(page, 'Heading', marker);

                    await B.saveAndReload(page);
                    await (await heading.count() > 0
                        ? B.selectBlock(page, marker)
                        : B.selectLastBlock(page));

                    for (const [f, expected] of flipped) {
                        await expect(
                            B.field(page, f.label, { within: f.within }).getByRole('switch'),
                            `${item.label} · ${f.label}`,
                        ).toHaveAttribute('aria-checked', expected);
                    }

                    await B.deleteSelected(page);
                    await B.saveDraft(page);
                });
            }
        });
    }
});
