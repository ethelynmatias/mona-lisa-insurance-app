import { useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
            <div className="w-full max-w-md">
                {/* Card */}
                <div className="bg-white rounded-2xl shadow-xl p-8">
                    {/* Logo + heading */}
                    <div className="text-center mb-8">
                        <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 mb-4">
                            <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                <path fillRule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900">Mona Lisa Insurance</h1>
                        <p className="text-gray-500 text-sm mt-1">Sign in to your account</p>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        {/* Email */}
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="you@example.com"
                                autoComplete="email"
                                autoFocus
                                className={`w-full px-4 py-2.5 rounded-lg border text-sm transition-colors outline-none
                                    focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                    ${errors.email ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
                            />
                            {errors.email && (
                                <p className="mt-1.5 text-xs text-red-600">{errors.email}</p>
                            )}
                        </div>

                        {/* Password */}
                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1.5">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                autoComplete="current-password"
                                className={`w-full px-4 py-2.5 rounded-lg border text-sm transition-colors outline-none
                                    focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                    ${errors.password ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-white'}`}
                            />
                            {errors.password && (
                                <p className="mt-1.5 text-xs text-red-600">{errors.password}</p>
                            )}
                        </div>

                        {/* Remember me */}
                        <div className="flex items-center">
                            <input
                                id="remember"
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            />
                            <label htmlFor="remember" className="ml-2 text-sm text-gray-600">
                                Remember me
                            </label>
                        </div>

                        {/* Submit */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400
                                text-white text-sm font-semibold rounded-lg transition-colors
                                focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>
                </div>

                <p className="text-center text-xs text-gray-400 mt-6">
                    &copy; {new Date().getFullYear()} Mona Lisa Insurance. All rights reserved.
                </p>
            </div>
        </div>
    );
}
