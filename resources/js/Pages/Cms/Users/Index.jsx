import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { USERS } from '../../../cms/data/mockData';

export default function UsersIndex() {
    const flash = useCmsToast();

    return (
        <div className="cms-page cms-page--medium">
            <div style={{ display: 'flex', alignItems: 'center', marginBottom: 16 }}>
                <p style={{ margin: 0, fontSize: 13.5, color: 'var(--cms-text-mid)' }}>
                    Two roles keep permissions simple: Super Administrators manage everything, Client Administrators manage content.
                </p>
                <button type="button" className="cms-btn cms-btn--primary" style={{ marginLeft: 'auto' }} onClick={() => flash('Invite sent')}>
                    Invite user
                </button>
            </div>

            <div className="cms-table">
                <div className="cms-table__head-row cms-table__row--users">
                    <div>User</div><div>Role</div><div>Status</div><div>Last login</div><div />
                </div>
                {USERS.map((u) => (
                    <div key={u.email} className="cms-table__row cms-table__row--users">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
                            <span className="cms-user-chip__avatar" style={{ width: 30, height: 30 }}>{u.initials}</span>
                            <div style={{ minWidth: 0 }}>
                                <div className="cms-table__cell-sub" style={{ fontSize: 13.5, fontWeight: 500, color: 'var(--cms-ink)' }}>{u.name}</div>
                                <div className="cms-table__cell-sub">{u.email}</div>
                            </div>
                        </div>
                        <div className="cms-table__cell">{u.role}</div>
                        <div>
                            <Badge tone={u.active ? 'success' : 'neutral'}>{u.active ? 'Active' : 'Disabled'}</Badge>
                        </div>
                        <div className="cms-table__cell">{u.last}</div>
                        <button type="button" className="cms-btn cms-btn--xs" style={{ justifySelf: 'end' }} onClick={() => flash(`Managing ${u.name}`)}>Manage</button>
                    </div>
                ))}
            </div>
        </div>
    );
}

UsersIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;
