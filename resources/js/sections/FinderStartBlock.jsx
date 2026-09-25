import { useId, useState } from 'react';
import { spacingClasses } from './spacing';

export default function FinderStartBlock({ data, anchor, actions = {} }) {
    const inputId = `${useId()}-area`;
    const [area, setArea] = useState('');

    const start = (e) => {
        e.preventDefault();
        actions['open-finder']?.(area.trim());
    };

    return (
        <div id={anchor} className={`block-finder-start ${spacingClasses(data)}`.trim()}>
            <form className="block-finder-start__form" onSubmit={start}>
                <label className="sr-only" htmlFor={inputId}>
                    {data.prompt || 'Your suburb or postcode'}
                </label>
                <input
                    id={inputId}
                    type="text"
                    autoComplete="postal-code"
                    placeholder={data.prompt}
                    value={area}
                    onChange={(e) => setArea(e.target.value)}
                />
                <button type="submit" className="btn primary">
                    {data.buttonLabel || 'Start Here'}
                </button>
            </form>
            {data.note ? <p className="block-finder-start__note">{data.note}</p> : null}
        </div>
    );
}
