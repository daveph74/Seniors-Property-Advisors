export default function PageSettingsPanel({ page, onSave }) {
    const seo = page.seo || {};

    const saveTitle = (value) => {
        const title = value.trim();

        if (!title || title === page.title) return;

        onSave({ title, seo });
    };

    const saveSeo = (key, value) => {
        const next = value.trim();

        if (next === (seo[key] || '')) return;

        onSave({ title: page.title, seo: { ...seo, [key]: next || null } });
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

            <div className="cms-field">
                <label className="cms-field-label">URL</label>
                <input className="cms-input" value={page.url || '/'} readOnly disabled />
                <div className="cms-hint">Page addresses are fixed for now.</div>
            </div>
        </div>
    );
}
