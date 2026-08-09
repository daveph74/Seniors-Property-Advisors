import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { SearchIcon } from '../components/icons';

const DEBOUNCE = 200;

export default function SearchPalette({ open, onClose }) {
    const [term, setTerm] = useState('');
    const [groups, setGroups] = useState([]);
    const [searching, setSearching] = useState(false);
    const [active, setActive] = useState(0);
    const input = useRef(null);

    const flat = groups.flatMap((g) => g.results);
    const short = term.trim().length < 2;

    useEffect(() => {
        if (open) input.current?.focus();
        else { setTerm(''); setGroups([]); setActive(0); }
    }, [open]);

    useEffect(() => {
        if (! open) return undefined;

        if (short) { setGroups([]); setSearching(false); return undefined; }

        setSearching(true);

        /* Aborted on the next keystroke, so a slow answer for "he" cannot land after "helen"
           and overwrite it. */
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/cms/search?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (! response.ok) throw new Error(response.status);

                const body = await response.json();
                setGroups(body.groups);
                setActive(0);
            } catch (e) {
                if (e.name !== 'AbortError') setGroups([]);
            } finally {
                if (! controller.signal.aborted) setSearching(false);
            }
        }, DEBOUNCE);

        return () => { clearTimeout(timer); controller.abort(); };
    }, [term, open]);

    if (! open) return null;

    const go = (result) => { onClose(); router.visit(result.href); };

    const onKeyDown = (e) => {
        if (e.key === 'Escape') { onClose(); return; }
        if (flat.length === 0) return;

        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((i) => (i + 1) % flat.length); }
        if (e.key === 'ArrowUp') { e.preventDefault(); setActive((i) => (i - 1 + flat.length) % flat.length); }
        if (e.key === 'Enter') { e.preventDefault(); go(flat[active]); }
    };

    let index = -1;

    return (
        <>
            <button type="button" className="cms-overlay" aria-label="Close the search" onClick={onClose} />
            <div className="cms-palette" role="dialog" aria-modal="true" aria-label="Search the CMS">
                <div className="cms-palette__field">
                    <SearchIcon size={16} />
                    <input
                        ref={input}
                        className="cms-palette__input"
                        placeholder="Search pages, articles, media…"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        onKeyDown={onKeyDown}
                        aria-controls="cms-palette-results"
                    />
                    <span className="cms-header__kbd">Esc</span>
                </div>

                {/* Nothing at all until there is something to say. An empty panel explaining that
                    it is empty reads as an error, and the field already invites the typing. */}
                {short ? null : (
                    <div className="cms-palette__results" id="cms-palette-results" role="listbox">
                        {searching && groups.length === 0 ? (
                            <p className="cms-palette__hint">Searching…</p>
                        ) : groups.length === 0 ? (
                            <p className="cms-palette__hint">Nothing matches “{term}”.</p>
                        ) : groups.map((group) => (
                            <div className="cms-palette__group" key={group.label}>
                                <div className="cms-palette__group-label">{group.label}</div>
                                {group.results.map((result) => {
                                    index += 1;
                                    const mine = index;

                                    return (
                                        <button
                                            type="button"
                                            key={`${group.label}-${result.id}`}
                                            role="option"
                                            aria-selected={active === mine}
                                            className={`cms-palette__result${active === mine ? ' cms-palette__result--active' : ''}`}
                                            onMouseEnter={() => setActive(mine)}
                                            onClick={() => go(result)}
                                        >
                                            <span className="cms-palette__result-title">{result.title}</span>
                                            {result.meta ? <span className="cms-palette__result-meta">{result.meta}</span> : null}
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
