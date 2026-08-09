import { useEffect, useState } from 'react';

/**
 * The value, once it has stopped changing. Typing should not be a request per keystroke.
 *
 * No AbortController here, unlike the search palette: this feeds an Inertia visit, and Inertia
 * cancels the one already in flight itself.
 */
export function useDebounced(value, ms = 250) {
    const [settled, setSettled] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setSettled(value), ms);

        return () => clearTimeout(timer);
    }, [value, ms]);

    return settled;
}
