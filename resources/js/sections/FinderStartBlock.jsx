import { useId, useRef, useState } from 'react';
import AddressAutocomplete from '../components/AddressAutocomplete';
import { spacingClasses } from './spacing';

export default function FinderStartBlock({ data, anchor, actions = {} }) {
    const inputId = `${useId()}-area`;
    const inputRef = useRef(null);
    const [location, setLocation] = useState(null);

    const start = (e) => {
        e.preventDefault();
        const typed = inputRef.current?.value.trim();
        actions['open-finder']?.(location ?? (typed ? { suburb: typed, freeText: true } : null));
    };

    return (
        <div id={anchor} className={`block-finder-start ${spacingClasses(data)}`.trim()}>
            <form className="block-finder-start__form" onSubmit={start}>
                <label className="sr-only" htmlFor={inputId}>
                    {data.prompt || 'Your suburb or postcode'}
                </label>
                <AddressAutocomplete
                    id={inputId}
                    kind="suburb"
                    required={false}
                    value={location}
                    onChange={setLocation}
                    placeholder={data.prompt}
                    inputRef={inputRef}
                />
                <button type="submit" className="btn primary">
                    {data.buttonLabel || 'Start Here'}
                </button>
            </form>
            {data.note ? <p className="block-finder-start__note">{data.note}</p> : null}
        </div>
    );
}
