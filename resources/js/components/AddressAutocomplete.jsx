import { useEffect, useId, useRef, useState } from 'react';

const DEBOUNCE_MS = 250;
const MIN_CHARS = 2;

/**
 * Per kind: the key the typed or picked line is kept under, and the words the
 * list uses. An address answer's line is its street; a suburb answer's is the suburb.
 */
const KINDS = {
    address: { line: 'street', searching: 'Searching addresses…', found: 'addresses found', none: 'No matching address' },
    suburb: { line: 'suburb', searching: 'Searching suburbs…', found: 'suburbs found', none: 'No matching suburb' },
};

/**
 * Combobox backed by Google Places, proxied through /api/suburbs so the API key
 * stays server-side. `kind` picks street addresses (Agent Finder's first
 * question) or suburbs and postcodes (the start box).
 *
 * Degrades to a plain text field: if the lookup returns nothing or fails, the
 * typed value is still captured on blur as free text, so the form is always
 * completable.
 */
export default function AddressAutocomplete({
    id,
    value,
    onChange,
    placeholder,
    kind = 'address',
    required = true,
    active = true,
    disabled = false,
    invalid = false,
    describedBy,
    inputRef,
}) {
    const words = KINDS[kind] ?? KINDS.address;
    const listId = `${useId()}-${kind}`;
    // Seeded from the current selection: step 1 unmounts when the wizard
    // advances, so pressing Back must show the answer again, not a blank field.
    const [query, setQuery] = useState(() => value?.description ?? value?.[words.line] ?? '');
    const [suggestions, setSuggestions] = useState([]);
    const [openList, setOpenList] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);

    const fieldRef = useRef(null);
    const abortRef = useRef(null);
    // One Places session token per typing session — that's how autocomplete is
    // billed as a session rather than per keystroke.
    const sessionRef = useRef(null);
    // Set while a suggestion is being applied, so the fetch effect doesn't
    // immediately re-query with the text we just wrote into the input.
    const skipFetchRef = useRef(false);

    const newSession = () =>
        (sessionRef.current =
            typeof crypto !== 'undefined' && crypto.randomUUID
                ? crypto.randomUUID()
                : String(Math.random()).slice(2));

    // Clear everything when the modal closes so a reopened form is empty.
    useEffect(() => {
        if (active) return;
        abortRef.current?.abort();
        setQuery('');
        setSuggestions([]);
        setOpenList(false);
        setActiveIndex(-1);
        setLoading(false);
        setSearched(false);
        sessionRef.current = null;
    }, [active]);

    // Note: no effect syncs `query` from a cleared `value`. Editing after a
    // selection clears `value` on the same keystroke, so such an effect would
    // wipe the text the visitor just typed. The `active` reset above is the
    // only path that needs to clear the field.

    useEffect(() => {
        if (!active || disabled) return;

        if (skipFetchRef.current) {
            skipFetchRef.current = false;
            return;
        }

        const trimmed = query.trim();
        if (trimmed.length < MIN_CHARS) {
            abortRef.current?.abort();
            setSuggestions([]);
            setSearched(false);
            setLoading(false);
            return;
        }

        setLoading(true);
        const timer = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            if (!sessionRef.current) newSession();

            try {
                const params = new URLSearchParams({ q: trimmed, session: sessionRef.current, kind });
                const res = await fetch(`/api/suburbs?${params}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                const data = res.ok ? await res.json() : { suggestions: [] };
                setSuggestions(data.suggestions ?? []);
                setActiveIndex(-1);
                setOpenList(true);
                setSearched(true);
            } catch (err) {
                if (err.name === 'AbortError') return;
                setSuggestions([]);
                setSearched(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [query, active, disabled]);

    // Dismiss the list on an outside click without stealing the click itself.
    useEffect(() => {
        if (!openList) return;
        const onDocClick = (e) => {
            if (!fieldRef.current?.contains(e.target)) setOpenList(false);
        };
        document.addEventListener('click', onDocClick);
        return () => document.removeEventListener('click', onDocClick);
    }, [openList]);

    const select = async (suggestion) => {
        skipFetchRef.current = true;
        setQuery(suggestion.description ?? suggestion.label);
        setOpenList(false);
        setActiveIndex(-1);
        setSuggestions([]);

        // Optimistic: record the pick immediately, enrich once details land.
        onChange({
            placeId: suggestion.id,
            [words.line]: suggestion.label,
            description: suggestion.description ?? suggestion.label,
        });

        try {
            const params = new URLSearchParams({ place_id: suggestion.id });
            const res = await fetch(`/api/suburbs?${params}`, {
                headers: { Accept: 'application/json' },
            });
            const place = res.ok ? (await res.json()).place : null;
            if (place) {
                onChange({
                    placeId: place.place_id,
                    street: kind === 'address' ? place.street ?? suggestion.label : null,
                    suburb: place.suburb ?? null,
                    state: place.state ?? null,
                    postcode: place.postcode ?? null,
                    lat: place.lat ?? null,
                    lng: place.lng ?? null,
                    description: suggestion.description ?? suggestion.label,
                });
            }
        } catch {
            // Keep the optimistic value — the picked line is the part that matters.
        } finally {
            sessionRef.current = null; // Next keystroke starts a fresh billing session.
        }
    };

    const handleKeyDown = (e) => {
        const count = suggestions.length;

        if (e.key === 'Escape') {
            if (openList) {
                // The modal closes on Escape at the document level, so stop this
                // one here — dismissing the list must not close the whole form.
                e.stopPropagation();
                setOpenList(false);
                setActiveIndex(-1);
            }
            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!count) return;
            e.preventDefault();
            if (!openList) {
                setOpenList(true);
                return;
            }
            const step = e.key === 'ArrowDown' ? 1 : -1;
            setActiveIndex((i) => (i + step + count) % count);
            return;
        }

        if (!openList || !count) return;

        if (e.key === 'Home') {
            e.preventDefault();
            setActiveIndex(0);
        } else if (e.key === 'End') {
            e.preventDefault();
            setActiveIndex(count - 1);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            select(suggestions[activeIndex]);
        }
    };

    // Nothing was picked from the list — keep what they typed rather than
    // silently discarding it.
    const handleBlur = () => {
        const trimmed = query.trim();
        if (!value && trimmed) onChange({ [words.line]: trimmed, freeText: true });
    };

    const showList = openList && (suggestions.length > 0 || (searched && !loading));

    let status = '';
    if (loading) status = words.searching;
    else if (searched && suggestions.length) status = `${suggestions.length} ${words.found}`;
    else if (searched) status = words.none;

    return (
        <div className="combo" ref={fieldRef}>
            <input
                id={id}
                type="text"
                role="combobox"
                autoComplete="off"
                ref={inputRef}
                aria-expanded={showList}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={activeIndex >= 0 ? `${listId}-${activeIndex}` : undefined}
                aria-required={required ? 'true' : undefined}
                aria-invalid={invalid ? 'true' : undefined}
                aria-describedby={describedBy}
                placeholder={placeholder}
                disabled={disabled}
                value={query}
                onChange={(e) => {
                    setQuery(e.target.value);
                    // Never leave a stale selection sitting behind edited text.
                    if (value) onChange(null);
                }}
                onKeyDown={handleKeyDown}
                onBlur={handleBlur}
                onFocus={() => suggestions.length && setOpenList(true)}
            />

            {showList && (
                <ul className="combo-list" id={listId} role="listbox">
                    {suggestions.length > 0 ? (
                        suggestions.map((s, i) => (
                            <li
                                key={s.id}
                                id={`${listId}-${i}`}
                                role="option"
                                aria-selected={i === activeIndex}
                                className={`combo-option${i === activeIndex ? ' active' : ''}`}
                                // mousedown, not click: blur would close the list first.
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    select(s);
                                }}
                                onMouseEnter={() => setActiveIndex(i)}
                            >
                                <span className="combo-main">{s.label}</span>
                                {s.secondary && <span className="combo-sub">{s.secondary}</span>}
                            </li>
                        ))
                    ) : (
                        <li className="combo-empty">
                            {words.none} – you can type it in yourself.
                        </li>
                    )}
                </ul>
            )}

            <span className="sr-only" role="status" aria-live="polite">
                {status}
            </span>
        </div>
    );
}
