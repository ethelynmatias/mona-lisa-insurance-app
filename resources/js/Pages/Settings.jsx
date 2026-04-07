import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function Alert({ type, message, onClose }) {
    const styles = type === 'success'
        ? 'bg-green-50 border-green-200 text-green-700'
        : 'bg-red-50 border-red-200 text-red-700';

    return (
        <div className={`flex items-center justify-between gap-3 px-4 py-3 border rounded-lg text-sm ${styles}`}>
            <span>{message}</span>
            <button onClick={onClose} className="flex-shrink-0 opacity-60 hover:opacity-100">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    );
}

function FormField({ label, error, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

function Input({ type = 'text', value, onChange, placeholder, className = '' }) {
    return (
        <input
            type={type}
            value={value}
            onChange={e => onChange(e.target.value)}
            placeholder={placeholder}
            className={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg
                focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent ${className}`}
        />
    );
}

// ──────────────────────────────────────────
//  Profile section
// ──────────────────────────────────────────
function ProfileSection({ user }) {
    const [name, setName]   = useState(user.name);
    const [email, setEmail] = useState(user.email);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        router.put('/settings/profile', { name, email }, {
            preserveScroll: true,
            onError:  err  => { setErrors(err); setSaving(false); },
            onSuccess: ()  => setSaving(false),
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <FormField label="Full Name" error={errors.name}>
                <Input value={name} onChange={setName} placeholder="Full name" />
            </FormField>
            <FormField label="Email Address" error={errors.email}>
                <Input type="email" value={email} onChange={setEmail} placeholder="email@example.com" />
            </FormField>
            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg
                        hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    {saving ? 'Saving…' : 'Save Profile'}
                </button>
            </div>
        </form>
    );
}

// ──────────────────────────────────────────
//  Password section
// ──────────────────────────────────────────
function PasswordSection() {
    const [form, setForm]     = useState({ current_password: '', password: '', password_confirmation: '' });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    function set(field) { return v => setForm(f => ({ ...f, [field]: v })); }

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        router.put('/settings/password', form, {
            preserveScroll: true,
            onError:  err => { setErrors(err); setSaving(false); },
            onSuccess: () => { setForm({ current_password: '', password: '', password_confirmation: '' }); setSaving(false); },
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <FormField label="Current Password" error={errors.current_password}>
                <Input type="password" value={form.current_password} onChange={set('current_password')} placeholder="••••••••" />
            </FormField>
            <FormField label="New Password" error={errors.password}>
                <Input type="password" value={form.password} onChange={set('password')} placeholder="••••••••" />
                <p className="mt-1 text-xs text-gray-400">Min. 8 characters with uppercase, lowercase, and numbers.</p>
            </FormField>
            <FormField label="Confirm New Password" error={errors.password_confirmation}>
                <Input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} placeholder="••••••••" />
            </FormField>
            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg
                        hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    {saving ? 'Updating…' : 'Update Password'}
                </button>
            </div>
        </form>
    );
}

// ──────────────────────────────────────────
//  Create user section (admin only)
// ──────────────────────────────────────────
function CreateUserSection() {
    const blank = { name: '', email: '', password: '', password_confirmation: '', role: 'manager' };
    const [form, setForm]     = useState(blank);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    function set(field) { return v => setForm(f => ({ ...f, [field]: v })); }

    function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        router.post('/settings/users', form, {
            preserveScroll: true,
            onError:  err => { setErrors(err); setSaving(false); },
            onSuccess: () => { setForm(blank); setSaving(false); },
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Full Name" error={errors.name}>
                    <Input value={form.name} onChange={set('name')} placeholder="Full name" />
                </FormField>
                <FormField label="Email Address" error={errors.email}>
                    <Input type="email" value={form.email} onChange={set('email')} placeholder="email@example.com" />
                </FormField>
                <FormField label="Password" error={errors.password}>
                    <Input type="password" value={form.password} onChange={set('password')} placeholder="••••••••" />
                </FormField>
                <FormField label="Confirm Password" error={errors.password_confirmation}>
                    <Input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} placeholder="••••••••" />
                </FormField>
            </div>
            <FormField label="Role" error={errors.role}>
                <select
                    value={form.role}
                    onChange={e => set('role')(e.target.value)}
                    className="w-full sm:w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg
                        focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </FormField>
            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={saving}
                    className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg
                        hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    {saving ? 'Creating…' : 'Create User'}
                </button>
            </div>
        </form>
    );
}

// ──────────────────────────────────────────
//  Users list (admin only)
// ──────────────────────────────────────────
function UsersSection({ users, currentUserId }) {
    function handleDelete(user) {
        if (!confirm(`Delete user "${user.name}"? This cannot be undone.`)) return;
        router.delete(`/settings/users/${user.id}`, { preserveScroll: true });
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-gray-100 bg-gray-50/50">
                        <th className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
                        <th className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                        <th className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th className="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Created</th>
                        <th className="px-4 py-3" />
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {users.map(user => (
                        <tr key={user.id} className="hover:bg-gray-50/50">
                            <td className="px-4 py-3 font-medium text-gray-900">
                                {user.name}
                                {user.id === currentUserId && (
                                    <span className="ml-2 text-xs text-gray-400">(you)</span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-gray-500">{user.email}</td>
                            <td className="px-4 py-3">
                                <span className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full capitalize
                                    ${user.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                                    {user.role}
                                </span>
                            </td>
                            <td className="px-4 py-3 text-gray-400 text-xs">
                                {new Date(user.created_at).toLocaleDateString()}
                            </td>
                            <td className="px-4 py-3 text-right">
                                {user.id !== currentUserId && (
                                    <button
                                        onClick={() => handleDelete(user)}
                                        className="text-xs text-red-500 hover:text-red-700 font-medium transition-colors"
                                    >
                                        Delete
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ──────────────────────────────────────────
//  Page
// ──────────────────────────────────────────
export default function Settings() {
    const { auth, users = [], flash = {} } = usePage().props;
    const user    = auth.user;
    const isAdmin = user.role === 'admin';

    const [flashMsg, setFlashMsg] = useState(flash);

    // Sync flash from Inertia on each visit
    const currentFlash = usePage().props.flash ?? {};

    return (
        <AuthenticatedLayout title="Settings">
            <div className="max-w-3xl space-y-6">

                {/* Flash messages */}
                {currentFlash.success && (
                    <Alert type="success" message={currentFlash.success} onClose={() => {}} />
                )}
                {currentFlash.error && (
                    <Alert type="error" message={currentFlash.error} onClose={() => {}} />
                )}

                {/* Profile */}
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-sm font-semibold text-gray-900 mb-1">Profile</h2>
                    <p className="text-xs text-gray-400 mb-5">Update your name and email address.</p>
                    <ProfileSection user={user} />
                </div>

                {/* Password */}
                <div className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-sm font-semibold text-gray-900 mb-1">Change Password</h2>
                    <p className="text-xs text-gray-400 mb-5">Choose a strong password.</p>
                    <PasswordSection />
                </div>

                {/* Admin: Create User */}
                {isAdmin && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-sm font-semibold text-gray-900 mb-1">Create User</h2>
                        <p className="text-xs text-gray-400 mb-5">Add a new admin or manager account.</p>
                        <CreateUserSection />
                    </div>
                )}

                {/* Admin: Users List */}
                {isAdmin && users.length > 0 && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-900">All Users</h2>
                            <p className="text-xs text-gray-400 mt-0.5">{users.length} account{users.length !== 1 ? 's' : ''}</p>
                        </div>
                        <UsersSection users={users} currentUserId={user.id} />
                    </div>
                )}

            </div>
        </AuthenticatedLayout>
    );
}
