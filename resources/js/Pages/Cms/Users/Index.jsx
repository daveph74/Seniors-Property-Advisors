import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { useCmsToast } from '../../../cms/ToastContext';
import { relative } from '../../../cms/relativeTime';

const BLANK = { name: '', email: '', role: 'client_admin', active: true, password: '' };

export default function UsersIndex({ users = [], roles = {} }) {
    const flash = useCmsToast();
    const me = usePage().props.auth?.user;
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(BLANK);
    const [errors, setErrors] = useState({});
    const [pendingDelete, setPendingDelete] = useState(null);

    const open = (user) => {
        setErrors({});
        setEditing(user ? user.id : 'new');
        setForm(user
            ? { name: user.name, email: user.email, role: user.role, active: user.active, password: '' }
            : BLANK);
    };

    const save = () => {
        const body = { ...form };

        if (! body.password) delete body.password;

        const done = {
            preserveScroll: true,
            onSuccess: () => { setEditing(null); flash('Account saved'); },
            onError: setErrors,
        };

        if (editing === 'new') {
            router.post('/cms/users', body, done);
        } else {
            router.patch(`/cms/users/${editing}`, body, done);
        }
    };

    const setActive = (user, active) => router.patch(`/cms/users/${user.id}`, {
        name: user.name,
        email: user.email,
        role: user.role,
        active,
    }, {
        preserveScroll: true,
        onSuccess: () => flash(active ? 'Account enabled' : 'Account disabled'),
        onError: (bag) => flash(Object.values(bag)[0] || 'That change was not allowed'),
    });

    return (
        <div className="cms-page cms-page--medium">
            <div style={{ display: 'flex', alignItems: 'center', marginBottom: 16 }}>
                <p style={{ margin: 0, fontSize: 13.5, color: 'var(--cms-text-mid)' }}>
                    Two roles keep permissions simple: Super Administrators manage everything, Client
                    Administrators manage content.
                </p>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary"
                    style={{ marginLeft: 'auto', flex: 'none' }}
                    onClick={() => open(null)}
                >
                    Add a user
                </button>
            </div>

            <div className="cms-table">
                <div className="cms-table__head-row cms-table__row--users">
                    <div>User</div><div>Role</div><div>Status</div><div>Last sign-in</div><div />
                </div>
                {users.map((u) => (
                    <div key={u.id} className="cms-table__row cms-table__row--users">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
                            <span className="cms-user-chip__avatar" style={{ width: 30, height: 30 }}>{u.initials}</span>
                            <div style={{ minWidth: 0 }}>
                                <div className="cms-table__cell-sub" style={{ fontSize: 13.5, fontWeight: 500, color: 'var(--cms-ink)' }}>
                                    {u.name}
                                    {u.id === me?.id ? <span className="cms-hint" style={{ marginLeft: 6 }}>you</span> : null}
                                </div>
                                <div className="cms-table__cell-sub">{u.email}</div>
                            </div>
                        </div>
                        <div className="cms-table__cell">{u.roleLabel}</div>
                        <div>
                            <Badge tone={u.active ? 'success' : 'neutral'}>{u.active ? 'Active' : 'Disabled'}</Badge>
                        </div>
                        <div className="cms-table__cell">{u.lastLoginAt ? relative(u.lastLoginAt) : 'Never'}</div>
                        <div style={{ display: 'flex', gap: 6, justifySelf: 'end' }}>
                            <button type="button" className="cms-btn cms-btn--xs" onClick={() => open(u)}>Edit</button>
                            {u.active
                                ? <button type="button" className="cms-btn cms-btn--xs" onClick={() => setActive(u, false)}>Disable</button>
                                : <button type="button" className="cms-btn cms-btn--xs" onClick={() => setActive(u, true)}>Enable</button>}
                            {u.id === me?.id ? null : (
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                    onClick={() => setPendingDelete(u)}
                                >
                                    Delete
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <ConfirmModal
                open={pendingDelete !== null}
                danger
                title="Delete this account?"
                lead="They lose access immediately. Content they published keeps their name against it. Disabling the account instead keeps it for later."
                detail={pendingDelete ? `${pendingDelete.name} · ${pendingDelete.email}` : ''}
                confirmLabel="Delete"
                onClose={() => setPendingDelete(null)}
                onConfirm={() => {
                    router.delete(`/cms/users/${pendingDelete.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Account deleted'),
                        onError: (bag) => flash(Object.values(bag)[0] || 'That account could not be deleted'),
                    });
                    setPendingDelete(null);
                }}
            />

            {editing !== null ? (
                <>
                    <div className="cms-overlay cms-anim-fade" onClick={() => setEditing(null)} />
                    <div className="cms-modal cms-anim-modal">
                        <h3 className="cms-modal__title">
                            {editing === 'new' ? 'Add a user' : 'Edit user'}
                        </h3>

                        <div className="cms-field">
                            <label className="cms-field-label">Full name</label>
                            <input
                                className="cms-input"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                            {errors.name ? <div className="cms-field-error">{errors.name}</div> : null}
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Email address</label>
                            <input
                                className="cms-input"
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                            />
                            {errors.email ? <div className="cms-field-error">{errors.email}</div> : null}
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Role</label>
                            <select
                                className="cms-select"
                                value={form.role}
                                onChange={(e) => setForm({ ...form, role: e.target.value })}
                            >
                                {Object.entries(roles).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <div className="cms-hint">
                                Client Administrators cannot delete content, restore archived pages, manage
                                users or reach settings.
                            </div>
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">
                                {editing === 'new' ? 'Password' : 'New password'}
                            </label>
                            <input
                                className="cms-input"
                                type="password"
                                autoComplete="new-password"
                                value={form.password}
                                placeholder={editing === 'new' ? '' : 'Leave empty to keep the current one'}
                                onChange={(e) => setForm({ ...form, password: e.target.value })}
                            />
                            <div className="cms-hint">At least 10 characters.</div>
                            {errors.password ? <div className="cms-field-error">{errors.password}</div> : null}
                        </div>

                        <div className="cms-modal__actions">
                            <button type="button" className="cms-btn" onClick={() => setEditing(null)}>Cancel</button>
                            <button
                                type="button"
                                className="cms-btn cms-btn--primary"
                                style={{ height: 36, padding: '0 18px' }}
                                disabled={! form.name.trim() || ! form.email.trim()}
                                onClick={save}
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </>
            ) : null}
        </div>
    );
}

UsersIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;
