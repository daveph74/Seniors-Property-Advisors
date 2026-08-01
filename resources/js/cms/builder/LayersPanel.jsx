import { Fragment } from 'react';
import { LayersIcon, LockIcon, ChevronUpSmallIcon, ChevronDownSmallIcon } from '../components/icons';

function LayerRow({ block, index, total, depth, selectedId, onSelect, onMoveUp, onMoveDown }) {
    const children = block.children || [];

    return (
        <Fragment>
            <div
                onClick={() => onSelect(block.id)}
                className={`cms-layer-row ${block.id === selectedId ? 'cms-layer-row--active' : ''}`}
                style={{ paddingLeft: 8 + depth * 14 }}
            >
                <LayersIcon />
                <span className="cms-layer-row__label">{block.label}</span>
                {block.locked ? <LockIcon /> : null}
                <button
                    type="button"
                    title="Move up"
                    className="cms-layer-row__move"
                    onClick={(e) => { e.stopPropagation(); onMoveUp(block.id); }}
                    disabled={index === 0}
                >
                    <ChevronUpSmallIcon />
                </button>
                <button
                    type="button"
                    title="Move down"
                    className="cms-layer-row__move"
                    onClick={(e) => { e.stopPropagation(); onMoveDown(block.id); }}
                    disabled={index === total - 1}
                >
                    <ChevronDownSmallIcon />
                </button>
            </div>
            {children.map((c, j) => (
                <LayerRow
                    key={c.id}
                    block={c}
                    index={j}
                    total={children.length}
                    depth={depth + 1}
                    selectedId={selectedId}
                    onSelect={onSelect}
                    onMoveUp={onMoveUp}
                    onMoveDown={onMoveDown}
                />
            ))}
        </Fragment>
    );
}

export default function LayersPanel({ blocks, selectedId, onSelect, onMoveUp, onMoveDown }) {
    return (
        <div className="cms-builder-left__body">
            <div className="cms-component-group__title" style={{ marginBottom: 8 }}>Page structure</div>
            {blocks.map((b, i) => (
                <LayerRow
                    key={b.id}
                    block={b}
                    index={i}
                    total={blocks.length}
                    depth={0}
                    selectedId={selectedId}
                    onSelect={onSelect}
                    onMoveUp={onMoveUp}
                    onMoveDown={onMoveDown}
                />
            ))}
        </div>
    );
}
