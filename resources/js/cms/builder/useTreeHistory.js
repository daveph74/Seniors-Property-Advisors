import { useCallback, useRef, useState } from 'react';

const MAX_DEPTH = 50;
const COALESCE_MS = 500;

export default function useTreeHistory(initial, selectionRef, onSelect) {
    const [blocks, setTree] = useState(initial);
    const treeRef = useRef(blocks);
    const past = useRef([]);
    const future = useRef([]);
    const lastTag = useRef(null);
    const lastAt = useRef(0);
    const [depth, setDepth] = useState({ undo: 0, redo: 0 });

    const commit = useCallback((updater, tag = null) => {
        const prev = treeRef.current;
        const next = typeof updater === 'function' ? updater(prev) : updater;

        if (next === prev) return;

        const now = Date.now();
        const merge = tag !== null && tag === lastTag.current && now - lastAt.current < COALESCE_MS;

        if (!merge) {
            past.current.push({ tree: prev, selectedId: selectionRef.current });

            if (past.current.length > MAX_DEPTH) past.current.shift();

            future.current = [];
        }

        lastTag.current = tag;
        lastAt.current = now;
        treeRef.current = next;
        setTree(next);
        setDepth({ undo: past.current.length, redo: future.current.length });
    }, [selectionRef]);

    const step = useCallback((from, to) => {
        const entry = from.current.pop();

        if (!entry) return false;

        to.current.push({ tree: treeRef.current, selectedId: selectionRef.current });
        lastTag.current = null;
        treeRef.current = entry.tree;
        setTree(entry.tree);
        onSelect(entry.selectedId);
        setDepth({ undo: past.current.length, redo: future.current.length });

        return true;
    }, [onSelect, selectionRef]);

    const undo = useCallback(() => step(past, future), [step]);
    const redo = useCallback(() => step(future, past), [step]);

    const reset = useCallback((tree) => {
        past.current = [];
        future.current = [];
        lastTag.current = null;
        treeRef.current = tree;
        setTree(tree);
        setDepth({ undo: 0, redo: 0 });
    }, []);

    return { blocks, commit, undo, redo, reset, canUndo: depth.undo > 0, canRedo: depth.redo > 0 };
}
