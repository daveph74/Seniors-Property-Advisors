import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import ImageField from '../../../cms/builder/ImageField';
import MediaLibraryModal from '../../../cms/builder/MediaLibraryModal';
import RichTextEditor from '../../../cms/components/RichTextEditor';
import { useCmsToast } from '../../../cms/ToastContext';
import { STATUS_LABEL, STATUS_TONE } from '../../../cms/data/mockData';

const dateValue = (iso) => (iso ? String(iso).slice(0, 10) : '');

/**
 * Inertia reuses the component when it navigates between two pages that render the same
 * one, so useForm would keep the state it started with — after creating an article the
 * form would still be the empty "new" form, showing no slug. Keying on the id forces a
 * fresh form per article.
 */
export default function BlogEditor(props) {
    return <ArticleForm key={props.article?.id ?? 'new'} {...props} />;
}

function ArticleForm({ article, categories = [], defaultAuthor, auth }) {
    const flash = useCmsToast();
    const isNew = article === null;
    const canDelete = auth?.can?.['content.delete'] === true;

    const { data, setData, post, patch, processing, errors } = useForm({
        title: article?.title ?? '',
        slug: article?.slug ?? '',
        summary: article?.summary ?? '',
        body: article?.body ?? '',
        featured_image: article?.image ?? '',
        author_name: article?.author ?? defaultAuthor ?? '',
        published_at: dateValue(article?.publishedAt),
        categories: article?.categoryIds ?? [],
        seo: {
            title: article?.seo?.title ?? '',
            description: article?.seo?.description ?? '',
            image: article?.seo?.image ?? '',
        },
    });

    const [pickImageInto, setPickImageInto] = useState(null);
    const [pendingDelete, setPendingDelete] = useState(false);
    const [renamed, setRenamed] = useState(false);

    const save = (onDone) => {
        if (isNew) {
            post('/cms/blog', { onSuccess: () => flash('Article created') });

            return;
        }

        patch(`/cms/blog/${article.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setRenamed(false);
                flash('Article saved');
                onDone?.();
            },
        });
    };

    const act = (verb, message) => router.post(`/cms/blog/${article.id}/${verb}`, {}, {
        preserveScroll: true,
        onSuccess: () => flash(message),
        onError: (bag) => flash(Object.values(bag)[0] || 'That was not allowed'),
    });

    const toggleCategory = (id) => setData(
        'categories',
        data.categories.includes(id)
            ? data.categories.filter((c) => c !== id)
            : [...data.categories, id],
    );

    const status = article?.status ?? 'draft';

    return (
        <div className="cms-page" style={{ maxWidth: 1120 }}>
            <div className="cms-toolbar">
                <Link href="/cms/blog" className="cms-btn cms-btn--sm">&larr; All articles</Link>

                {isNew ? null : <Badge tone={STATUS_TONE[status]}>{STATUS_LABEL[status] || status}</Badge>}

                <div className="cms-spacer" style={{ display: 'flex', gap: 8 }}>
                    {isNew ? null : (
                        <>
                            <a
                                className="cms-btn cms-btn--sm"
                                href={`/cms/blog/${article.id}/preview`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Preview
                            </a>
                            {status === 'published'
                                ? <button type="button" className="cms-btn cms-btn--sm" onClick={() => act('unpublish', 'Taken off the website')}>Unpublish</button>
                                : <button type="button" className="cms-btn cms-btn--sm" onClick={() => act('publish', 'Published')}>Publish</button>}
                            {status === 'archived'
                                ? <button type="button" className="cms-btn cms-btn--sm" onClick={() => act('unarchive', 'Restored')}>Restore</button>
                                : <button type="button" className="cms-btn cms-btn--sm" onClick={() => act('archive', 'Archived')}>Archive</button>}
                            {canDelete ? (
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--sm cms-btn--danger-outline"
                                    onClick={() => setPendingDelete(true)}
                                >
                                    Delete
                                </button>
                            ) : null}
                        </>
                    )}
                    <button
                        type="button"
                        className="cms-btn cms-btn--primary"
                        disabled={processing || ! data.title.trim()}
                        onClick={() => save()}
                    >
                        {processing ? 'Saving…' : isNew ? 'Create article' : 'Save'}
                    </button>
                </div>
            </div>

            <div className="cms-blog-editor">
                <div className="cms-settings-content">
                    <section className="cms-settings-section">
                        <div className="cms-field">
                            <label className="cms-field-label">Title</label>
                            <input
                                className="cms-input"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                            />
                            {errors.title ? <div className="cms-field-error">{errors.title}</div> : null}
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Short summary</label>
                            <textarea
                                className="cms-textarea"
                                rows={2}
                                value={data.summary}
                                onChange={(e) => setData('summary', e.target.value)}
                            />
                            <div className="cms-hint">Shown on the article cards. Left empty, the opening of the article is used.</div>
                            {errors.summary ? <div className="cms-field-error">{errors.summary}</div> : null}
                        </div>
                    </section>

                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Article</h2>
                        <p className="cms-settings-section__lead">
                            Type as you would in a letter. Select some words, then use the buttons above to
                            make them a heading, bold, a list, a quote or a link.
                        </p>

                        <RichTextEditor
                            value={data.body}
                            onChange={(body) => setData('body', body)}
                            onPickImage={(insert) => setPickImageInto(() => insert)}
                        />

                        {errors.body ? <div className="cms-field-error">{errors.body}</div> : null}
                    </section>
                </div>

                <aside className="cms-blog-side">
                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Featured image</h2>
                        <ImageField
                            value={data.featured_image}
                            onChange={(src) => setData('featured_image', src)}
                            hint="Used on the article cards and at the top of the article."
                        />
                    </section>

                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Details</h2>

                        <div className="cms-field">
                            <label className="cms-field-label">Author name</label>
                            <input
                                className="cms-input"
                                value={data.author_name}
                                onChange={(e) => setData('author_name', e.target.value)}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Published date</label>
                            <input
                                type="date"
                                className="cms-input"
                                value={data.published_at}
                                onChange={(e) => setData('published_at', e.target.value)}
                            />
                            <div className="cms-hint">
                                The date readers see. It does not schedule anything — publishing is what puts
                                an article on the website.
                            </div>
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Web address</label>
                            <input
                                className="cms-input"
                                value={data.slug}
                                placeholder="Made from the title"
                                onChange={(e) => {
                                    setData('slug', e.target.value);
                                    if (! isNew && e.target.value !== article.slug) setRenamed(true);
                                }}
                            />
                            <div className="cms-hint">/blog/{data.slug || 'made-from-the-title'}</div>
                            {renamed && status === 'published' ? (
                                <div className="cms-field-error">
                                    Changing this after publishing breaks any link already shared to the old
                                    address.
                                </div>
                            ) : null}
                            {errors.slug ? <div className="cms-field-error">{errors.slug}</div> : null}
                        </div>
                    </section>

                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Categories</h2>
                        {categories.length === 0 ? (
                            <p className="cms-hint" style={{ margin: 0 }}>
                                No categories yet. Add them on the article list.
                            </p>
                        ) : categories.map((c) => (
                            <label key={c.id} className="cms-check">
                                <input
                                    type="checkbox"
                                    checked={data.categories.includes(c.id)}
                                    onChange={() => toggleCategory(c.id)}
                                />
                                <span>{c.name}</span>
                                {c.active ? null : <Badge tone="neutral" small>Disabled</Badge>}
                            </label>
                        ))}
                    </section>

                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Search and sharing</h2>

                        <div className="cms-field">
                            <label className="cms-field-label">SEO title</label>
                            <input
                                className="cms-input"
                                value={data.seo.title}
                                placeholder={data.title}
                                onChange={(e) => setData('seo', { ...data.seo, title: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">SEO description</label>
                            <textarea
                                className="cms-textarea"
                                rows={3}
                                value={data.seo.description}
                                onChange={(e) => setData('seo', { ...data.seo, description: e.target.value })}
                            />
                        </div>

                        <ImageField
                            label="Social sharing image"
                            value={data.seo.image}
                            onChange={(src) => setData('seo', { ...data.seo, image: src })}
                            hint="Shown when the article is shared. The featured image is used if this is empty."
                        />
                    </section>
                </aside>
            </div>

            <MediaLibraryModal
                open={pickImageInto !== null}
                onClose={() => setPickImageInto(null)}
                onPick={(media) => {
                    pickImageInto?.(media.url ?? media);
                    setPickImageInto(null);
                }}
            />

            <ConfirmModal
                open={pendingDelete}
                danger
                title="Delete this article?"
                lead="It disappears from the website and cannot be brought back. Archiving instead keeps it out of sight but recoverable."
                detail={article?.title}
                confirmLabel="Delete"
                onClose={() => setPendingDelete(false)}
                onConfirm={() => {
                    router.delete(`/cms/blog/${article.id}`, { onSuccess: () => flash('Article deleted') });
                    setPendingDelete(false);
                }}
            />
        </div>
    );
}

BlogEditor.layout = (page) => <CmsLayout>{page}</CmsLayout>;
