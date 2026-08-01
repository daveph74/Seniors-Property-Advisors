import { useState } from 'react';
import ImageField from './ImageField';
import ConfirmModal from '../components/ConfirmModal';

export default function PageSettingsPanel({ page, onSave }) {
    const seo = page.seo || {};
    const isHome = (page.url || '/') === '/';
    const currentSlug = (page.url || '/').replace(/^\//, '');
    const [pendingSlug, setPendingSlug] = useState(null);

    const base = { title: page.title, navLabel: page.navLabel || '', seo };

    const saveTitle = (value) => {
        const title = value.trim();

        if (! title || title === page.title) return;

        onSave({ ...base, title });
    };

    const saveNavLabel = (value) => {
        const navLabel = value.trim();

        if (navLabel === (page.navLabel || '')) return;

        onSave({ ...base, navLabel });
    };

    const saveSeo = (key, value) => {
        const next = typeof value === 'string' ? value.trim() : value;

        if (next === (seo[key] || '')) return;

        onSave({ ...base, seo: { ...seo, [key]: next || null } });
    };

    const askSlug = (value) => {
        const slug = value.trim().replace(/^\/+|\/+$/g, '');

        if (! slug || slug === currentSlug) return;

        if (page.status === 'published') {
            setPendingSlug(slug);

            return;
        }

        onSave({ ...base, slug });
    };

    const onEnter = (e) => e.key === 'Enter' && e.target.blur();

    return (
        <div className="cms-builder-right__body">
            <div className="cms-field">
                <label className="cms-field-label">Page name</label>
                <input
                    key={`name-${page.title}`}
                    className="cms-input"
                    defaultValue={page.title}
                    onBlur={(e) => saveTitle(e.target.value)}
                    onKeyDown={onEnter}
                />
                <div className="cms-hint">What this page is called in the CMS, and the browser tab title unless you set one below.</div>
            </div>

            <div className="cms-field">
                <label className="cms-field-label">Menu label</label>
                <input
                    key={`nav-${page.navLabel || ''}`}
                    className="cms-input"
                    defaultValue={page.navLabel || ''}
                    placeholder={page.title}
                    onBlur={(e) => saveNavLabel(e.target.value)}
                    onKeyDown={onEnter}
                />
                <div className="cms-hint">The shorter wording used in the site menu.</div>
            </div>

            <div className="cms-field">
                <label className="cms-field-label">Browser tab title</label>
                <input
                    key={`seo-title-${seo.title || ''}`}
                    className="cms-input"
                    defaultValue={seo.title || ''}
                    placeholder={page.title}
                    onBlur={(e) => saveSeo('title', e.target.value)}
                    onKeyDown={onEnter}
                />
                <div className="cms-hint">Shown on the browser tab and in search results.</div>
            </div>

            <div className="cms-field">
                <label className="cms-field-label">Search description</label>
                <textarea
                    key={`seo-desc-${seo.description || ''}`}
                    className="cms-textarea"
                    rows={3}
                    defaultValue={seo.description || ''}
                    onBlur={(e) => saveSeo('description', e.target.value)}
                />
                <div className="cms-hint">The summary under the link in search results.</div>
            </div>

            <ImageField
                label="Sharing image"
                value={seo.image || ''}
                hint="Shown when the page is shared on Facebook or LinkedIn."
                onChange={(value) => saveSeo('image', value)}
            />

            <div className="cms-field">
                <label className="cms-field-label">Web address</label>
                <input
                    key={`slug-${currentSlug}`}
                    className="cms-input"
                    defaultValue={currentSlug}
                    disabled={isHome}
                    onBlur={(e) => askSlug(e.target.value)}
                    onKeyDown={onEnter}
                />
                <div className="cms-hint">
                    {isHome
                        ? 'The home page always lives at the site root.'
                        : 'Lowercase letters, numbers and hyphens. Changing this on a live page leaves a redirect behind.'}
                </div>
            </div>

            <ConfirmModal
                open={pendingSlug !== null}
                title="Change this page's web address?"
                lead="This page is live, so anyone using the old address needs to be sent to the new one."
                detail={pendingSlug ? `/${currentSlug}  →  /${pendingSlug}` : null}
                confirmLabel="Change it"
                onClose={() => setPendingSlug(null)}
                onConfirm={() => {
                    onSave({ ...base, slug: pendingSlug });
                    setPendingSlug(null);
                }}
            />
        </div>
    );
}
