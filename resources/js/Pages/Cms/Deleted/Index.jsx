import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { relative } from '../../../cms/relativeTime';
import { useCmsToast } from '../../../cms/ToastContext';

const THING = { article: 'article', question: 'question', testimonial: 'testimonial' };

export default function DeletedIndex({ items = [], auth }) {
    const flash = useCmsToast();
    const canDestroy = auth?.can?.['content.delete'] === true;
    const [pending, setPending] = useState(null);

    const restore = (item) => router.post(`/cms/deleted/${item.kind}/${item.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => flash(`${item.label} restored`),
    });

    return (
        <div className="cms-page">
            {items.length === 0 ? (
                <div className="cms-media-empty">
                    Nothing has been deleted. Anything you delete from the blog, FAQ or testimonial
                    screens waits here until somebody removes it for good.
                </div>
            ) : (
                <div className="cms-faq-list">
                    {items.map((item) => (
                        <div key={`${item.kind}-${item.id}`} className="cms-faq-row">
                            <Badge tone="muted" small>{THING[item.kind] || item.kind}</Badge>

                            <span className="cms-faq-row__q">
                                {item.label}
                                <small style={{ display: 'block', color: 'var(--cms-text-mid)', fontSize: 12 }}>
                                    deleted {item.deletedAt ? relative(item.deletedAt) : ''}
                                </small>
                            </span>

                            <button type="button" className="cms-btn cms-btn--xs" onClick={() => restore(item)}>
                                Restore
                            </button>

                            {canDestroy ? (
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                    onClick={() => setPending(item)}
                                >
                                    Delete for good
                                </button>
                            ) : null}
                        </div>
                    ))}
                </div>
            )}

            <ConfirmModal
                open={pending !== null}
                danger
                title="Delete this for good?"
                lead="This one cannot be undone. Everything else on this screen can be restored; this removes it from the database."
                detail={pending?.label}
                confirmLabel="Delete for good"
                onClose={() => setPending(null)}
                onConfirm={() => {
                    router.delete(`/cms/deleted/${pending.kind}/${pending.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash(`${pending.label} deleted for good`),
                    });
                    setPending(null);
                }}
            />
        </div>
    );
}

DeletedIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;
