import { spacingClasses } from './spacing';

export default function FinderStartBlock({ data, anchor, actions = {} }) {
    return (
        <div id={anchor} className={`block-finder-start ${spacingClasses(data)}`.trim()}>
            <button type="button" className="btn primary lg" onClick={() => actions['open-finder']?.()}>
                {data.buttonLabel || 'Start Here'} <span className="arr">→</span>
            </button>
            {data.note ? <p className="block-finder-start__note">{data.note}</p> : null}
        </div>
    );
}
