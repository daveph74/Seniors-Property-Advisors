import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import FindMyAgentModal from './FindMyAgentModal';

const FinderContext = createContext(null);

/**
 * Owns the Find My Agent modal for the whole public site and renders it once.
 * Every CTA across the pages opens it through `useFinder().openFinder`.
 */
export function FinderProvider({ children }) {
    const [open, setOpen] = useState(false);

    const openFinder = useCallback(() => setOpen(true), []);
    const closeFinder = useCallback(() => setOpen(false), []);

    const value = useMemo(() => ({ openFinder, closeFinder }), [openFinder, closeFinder]);

    return (
        <FinderContext.Provider value={value}>
            {children}
            <FindMyAgentModal open={open} onClose={closeFinder} />
        </FinderContext.Provider>
    );
}

export function useFinder() {
    const ctx = useContext(FinderContext);

    if (!ctx) {
        throw new Error('useFinder must be used inside a FinderProvider');
    }

    return ctx;
}
