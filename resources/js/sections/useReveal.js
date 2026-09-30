import { useEffect, useRef, useState } from 'react';

export const ANIMATIONS = ['fade-up', 'fade-in', 'fade-left', 'fade-right', 'zoom-in'];
const DELAYS = ['100', '200', '300'];

export default function useReveal(requested, requestedDelay, editing = false) {
    const animation = ! editing && ANIMATIONS.includes(requested) ? requested : null;
    const delay = animation && DELAYS.includes(String(requestedDelay)) ? String(requestedDelay) : null;
    const [inView, setInView] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (! animation || ! ref.current) return undefined;

        if (! ('IntersectionObserver' in window)) {
            setInView(true);

            return undefined;
        }

        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setInView(true);
                observer.disconnect();
            }
        }, { threshold: 0 });

        observer.observe(ref.current);

        return () => observer.disconnect();
    }, [animation]);

    const classes = [
        animation ? `reveal reveal--${animation}` : '',
        delay ? `reveal--delay-${delay}` : '',
        inView ? 'is-in-view' : '',
    ].filter(Boolean).join(' ');

    return { ref, animation, classes };
}
