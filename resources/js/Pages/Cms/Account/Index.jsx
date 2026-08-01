import { useForm } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { useCmsToast } from '../../../cms/ToastContext';
import { exact } from '../../../cms/relativeTime';

export default function AccountIndex({ account }) {
    const flash = useCmsToast();
    const { data, setData, patch, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();

        patch('/cms/account/password', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                flash('Password changed');
            },
        });
    }

    return (
        <div className="cms-page" style={{ maxWidth: 640 }}>
            <div className="cms-settings-content">
                <section className="cms-settings-section">
                    <h2 className="cms-settings-section__title">Who you are signed in as</h2>
                    <p className="cms-settings-section__lead">
                        Your name, email address and role are set by a super administrator. Your name is what
                        appears against the content you change.
                    </p>

                    <dl className="cms-account-facts">
                        <dt>Name</dt><dd>{account.name}</dd>
                        <dt>Email address</dt><dd>{account.email}</dd>
                        <dt>Role</dt><dd>{account.roleLabel}</dd>
                        <dt>Last sign-in</dt>
                        <dd>{account.lastLoginAt ? exact(account.lastLoginAt) : 'This is your first'}</dd>
                    </dl>
                </section>

                <form className="cms-settings-section" onSubmit={submit}>
                    <h2 className="cms-settings-section__title">Change your password</h2>
                    <p className="cms-settings-section__lead">
                        Changing it signs you out everywhere else, on this computer and any other.
                    </p>

                    <div className="cms-field">
                        <label className="cms-field-label">Current password</label>
                        <input
                            type="password"
                            className="cms-input"
                            autoComplete="current-password"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                        />
                        {errors.current_password ? <div className="cms-field-error">{errors.current_password}</div> : null}
                    </div>

                    <div className="cms-field">
                        <label className="cms-field-label">New password</label>
                        <input
                            type="password"
                            className="cms-input"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <div className="cms-hint">At least 10 characters.</div>
                        {errors.password ? <div className="cms-field-error">{errors.password}</div> : null}
                    </div>

                    <div className="cms-field">
                        <label className="cms-field-label">Confirm new password</label>
                        <input
                            type="password"
                            className="cms-input"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </div>

                    <div className="cms-modal__actions" style={{ marginTop: 18 }}>
                        <button
                            type="submit"
                            className="cms-btn cms-btn--primary"
                            style={{ height: 36, padding: '0 18px' }}
                            disabled={processing || ! data.current_password || ! data.password}
                        >
                            {processing ? 'Saving…' : 'Update password'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

AccountIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;
