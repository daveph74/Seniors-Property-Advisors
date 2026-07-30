import { useState } from 'react';
import { Toggle } from '../components/ui';
import { ChevronUpSmallIcon, ChevronDownSmallIcon, TrashIcon, PlusIcon } from '../components/icons';
import { readPath, writePath, blankItem } from './repeaters';
import ImageField from './ImageField';

const SUMMARY_KINDS = ['text', 'textarea', 'image'];

function summarise(item, fields) {
    if (typeof item === 'string') return item;

    const first = fields.find((f) => SUMMARY_KINDS.includes(f.type));

    return first ? String(readPath(item, first.path) || '') : '';
}

export default function RepeaterEditor({ collection, items, onChange }) {
    const { key, title, fields } = collection;
    const [openIndex, setOpenIndex] = useState(null);

    const replace = (index, next) => onChange(items.map((it, i) => (i === index ? next : it)));

    const move = (index, dir) => {
        const j = index + dir;

        if (j < 0 || j >= items.length) return;

        const next = items.slice();
        [next[index], next[j]] = [next[j], next[index]];
        onChange(next);
        setOpenIndex(openIndex === index ? j : openIndex);
    };

    const remove = (index) => {
        onChange(items.filter((_, i) => i !== index));
        setOpenIndex(null);
    };

    return (
        <div className="cms-field">
            <label className="cms-field-label">{title} · {items.length}</label>

            {items.map((item, index) => (
                <div key={index} className={`cms-rep__item ${openIndex === index ? 'cms-rep__item--open' : ''}`}>
                    <div className="cms-rep__head" onClick={() => setOpenIndex(openIndex === index ? null : index)}>
                        <span className="cms-rep__num">{index + 1}</span>
                        <span className="cms-rep__summary">{summarise(item, fields) || <em>Empty</em>}</span>
                        <button type="button" title="Move up" className="cms-icon-btn-sm" disabled={index === 0} onClick={(e) => { e.stopPropagation(); move(index, -1); }}>
                            <ChevronUpSmallIcon />
                        </button>
                        <button type="button" title="Move down" className="cms-icon-btn-sm" disabled={index === items.length - 1} onClick={(e) => { e.stopPropagation(); move(index, 1); }}>
                            <ChevronDownSmallIcon />
                        </button>
                        <button type="button" title="Remove" className="cms-icon-btn-sm cms-icon-btn-sm--danger" onClick={(e) => { e.stopPropagation(); remove(index); }}>
                            <TrashIcon />
                        </button>
                    </div>

                    {openIndex === index && (
                        <div className="cms-rep__body">
                            {fields.map((f) => {
                                const value = readPath(item, f.path);
                                const set = (v) => replace(index, writePath(item, f.path, v));

                                if (f.type === 'toggle') {
                                    return (
                                        <div key={f.path} className="cms-toggle-row">
                                            <span className="cms-toggle-row__label">{f.label}</span>
                                            <Toggle on={f.whenAbsent ? value !== false : !!value} onChange={set} />
                                        </div>
                                    );
                                }

                                if (f.type === 'image') {
                                    return (
                                        <ImageField
                                            key={f.path}
                                            label={f.label}
                                            value={value}
                                            alt={f.altPath ? readPath(item, f.altPath) : null}
                                            onChange={set}
                                            onAltChange={f.altPath ? (v) => replace(index, writePath(item, f.altPath, v)) : null}
                                        />
                                    );
                                }

                                return (
                                    <div key={f.path} className="cms-field">
                                        <label className="cms-field-label">{f.label}</label>
                                        {f.type === 'textarea' && (
                                            <textarea className="cms-textarea" rows={3} value={value} onChange={(e) => set(e.target.value)} />
                                        )}
                                        {f.type === 'select' && (
                                            <select className="cms-select" value={value} onChange={(e) => set(e.target.value)}>
                                                {f.options.map((o) => (
                                                    <option key={o} value={o}>{o === '' ? 'Nothing' : o}</option>
                                                ))}
                                            </select>
                                        )}
                                        {f.type === 'text' && (
                                            <input className="cms-input" value={value} onChange={(e) => set(e.target.value)} />
                                        )}
                                        {f.type === 'number' && (
                                            <input
                                                className="cms-input"
                                                type="number"
                                                value={value}
                                                onChange={(e) => set(e.target.value === '' ? '' : Number(e.target.value))}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            ))}

            <button
                type="button"
                className="cms-btn cms-btn--sm cms-btn--block"
                onClick={() => { onChange([...items, blankItem(fields)]); setOpenIndex(items.length); }}
            >
                <PlusIcon size={14} stroke="currentColor" />
                Add to {title.toLowerCase()}
            </button>
        </div>
    );
}
