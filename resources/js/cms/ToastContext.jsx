import { createContext, useContext, useState } from 'react';
import { ToastStack } from './components/ui';

const ToastContext = createContext(() => {});

let id = 0;

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const flash = (message) => {
        const toastId = ++id;
        setToasts((t) => [...t, { id: toastId, message }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== toastId)), 2600);
    };

    return (
        <ToastContext.Provider value={flash}>
            {children}
            <ToastStack toasts={toasts} />
        </ToastContext.Provider>
    );
}

export function useCmsToast() {
    return useContext(ToastContext);
}
