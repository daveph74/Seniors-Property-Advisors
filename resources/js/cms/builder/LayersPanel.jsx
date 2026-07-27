import { LayersIcon, LockIcon, ChevronUpSmallIcon, ChevronDownSmallIcon } from '../components/icons';

export default function LayersPanel({ blocks, selectedId, onSelect, onMoveUp, onMoveDown }) {
    return (
        <div className="cms-builder-left__body">
            <div className="cms-component-group__title" style={{ marginBottom: 8 }}>Page structure</div>
            {blocks.map((b, i) => (
                <div
                    key={b.id}
                    onClick={() => onSelect(b.id)}
                    className={`cms-layer-row ${b.id === selectedId ? 'cms-layer-row--active' : ''}`}
                >
                    <LayersIcon />
                    <span className="cms-layer-row__label">{b.label}</span>
                    {b.locked ? <LockIcon /> : null}
                    <button
                        type="button"
                        title="Move up"
                        className="cms-layer-row__move"
                        onClick={(e) => { e.stopPropagation(); onMoveUp(b.id); }}
                        disabled={i === 0}
                    >
                        <ChevronUpSmallIcon />
                    </button>
                    <button
                        type="button"
                        title="Move down"
                        className="cms-layer-row__move"
                        onClick={(e) => { e.stopPropagation(); onMoveDown(b.id); }}
                        disabled={i === blocks.length - 1}
                    >
                        <ChevronDownSmallIcon />
                    </button>
                </div>
            ))}
        </div>
    );
}
