import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { useCmsToast } from '../../../cms/ToastContext';
import { exact } from '../../../cms/relativeTime';
import PasswordChecklist from '../../../cms/components/PasswordChecklist';
import { passwordMeetsPolicy } from '../../../cms/passwordPolicy';
import { EyeIcon, HideIcon, WarningIcon } from '../../../cms/components/icons';

export default function AccountIndex({ account }) {
    const flash = useCmsToast();
    const { auth } = usePage().props;
    const [reveal, setReveal] = useState(false);
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
                {auth.mustChangePassword ? (
                    <div className="cms-impact-banner">
                        <WarningIcon size={17} stroke="#8A5300" />
                        <div className="cms-impact-banner__text">
                            <strong style={{ color: 'var(--cms-warning-text)' }}>
                                You are still using the password this account was given.
                            </strong>
                            {' '}Choose one of your own below. Until you do, anybody who was told the
                            original can sign in as you.
                        </div>
                    </div>
                ) : null}

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
                        <div className="cms-reveal">
                            <input
                                type={reveal ? 'text' : 'password'}
                                className="cms-input"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <button
                                type="button"
                                className="cms-reveal__btn"
                                onClick={() => setReveal(! reveal)}
                                aria-label={reveal ? 'Hide password' : 'Show password'}
                            >
                                {reveal ? <HideIcon size={15} /> : <EyeIcon size={15} />}
                            </button>
                        </div>
                        <PasswordChecklist value={data.password} />
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
                            disabled={processing || ! data.current_password || ! passwordMeetsPolicy(data.password)}
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
