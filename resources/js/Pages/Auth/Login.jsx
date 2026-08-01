import { Head, useForm } from '@inertiajs/react';
import '../../../css/cms.css';

export default function Login({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e) {
        e.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Sign in — Seniors Property Advisors CMS" />
            <div className="cms-shell cms-signin">
                <form className="cms-signin__card" onSubmit={submit}>
                    <div className="cms-signin__brand">
                        <div className="cms-sidebar__mark">SP</div>
                        <div>
                            <div className="cms-signin__name">Seniors Property Advisors</div>
                            <div className="cms-signin__sub">Content management</div>
                        </div>
                    </div>

                    {status ? <div className="cms-signin__status">{status}</div> : null}

                    <label className="cms-field">
                        <span className="cms-field-label">Email address</span>
                        <input
                            type="email"
                            className="cms-input"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                            autoFocus
                            required
                        />
                    </label>

                    <label className="cms-field">
                        <span className="cms-field-label">Password</span>
                        <input
                            type="password"
                            className="cms-input"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                            required
                        />
                    </label>

                    {errors.email || errors.password ? (
                        <div className="cms-signin__error">{errors.email || errors.password}</div>
                    ) : null}

                    <label className="cms-signin__remember">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span>Keep me signed in on this device</span>
                    </label>

                    <button type="submit" className="cms-btn cms-btn--primary cms-signin__submit" disabled={processing}>
                        {processing ? 'Signing in…' : 'Sign in'}
                    </button>
                </form>
            </div>
        </>
    );
}
