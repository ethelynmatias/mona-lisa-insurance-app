import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const stats = [
    {
        label: 'Active Policies',
        value: '1,284',
        change: '+12%',
        positive: true,
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        ),
        color: 'bg-blue-50 text-blue-600',
    },
    {
        label: 'Open Claims',
        value: '48',
        change: '-5%',
        positive: false,
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
        ),
        color: 'bg-amber-50 text-amber-600',
    },
    {
        label: 'Total Clients',
        value: '3,572',
        change: '+8%',
        positive: true,
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        ),
        color: 'bg-green-50 text-green-600',
    },
    {
        label: 'Monthly Revenue',
        value: '$94,210',
        change: '+18%',
        positive: true,
        icon: (
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
        color: 'bg-purple-50 text-purple-600',
    },
];

const recentClaims = [
    { id: 'CLM-0041', client: 'James Reyes',    policy: 'Auto Insurance',   amount: '$3,200', status: 'Pending',  date: 'Apr 6, 2026' },
    { id: 'CLM-0040', client: 'Maria Santos',   policy: 'Home Insurance',   amount: '$8,500', status: 'Approved', date: 'Apr 5, 2026' },
    { id: 'CLM-0039', client: 'Carlos Mendoza', policy: 'Life Insurance',   amount: '$15,000', status: 'Review',   date: 'Apr 4, 2026' },
    { id: 'CLM-0038', client: 'Ana Reyes',      policy: 'Health Insurance', amount: '$1,750', status: 'Approved', date: 'Apr 3, 2026' },
    { id: 'CLM-0037', client: 'Luis Torres',    policy: 'Auto Insurance',   amount: '$4,100', status: 'Denied',   date: 'Apr 2, 2026' },
];

const statusStyles = {
    Pending:  'bg-amber-100 text-amber-700',
    Approved: 'bg-green-100 text-green-700',
    Review:   'bg-blue-100 text-blue-700',
    Denied:   'bg-red-100 text-red-700',
};

const recentClients = [
    { name: 'Sophia Lim',    email: 'sophia@email.com',   policies: 2, joined: 'Apr 6, 2026' },
    { name: 'Marco Dela Cruz', email: 'marco@email.com',  policies: 1, joined: 'Apr 5, 2026' },
    { name: 'Elena Ramos',   email: 'elena@email.com',    policies: 3, joined: 'Apr 4, 2026' },
];

export default function Dashboard() {
    return (
        <AuthenticatedLayout title="Dashboard">
            <div className="space-y-6">

                {/* Stats grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    {stats.map((stat) => (
                        <div key={stat.label} className="bg-white rounded-xl border border-gray-200 p-5">
                            <div className="flex items-center justify-between mb-4">
                                <span className="text-sm font-medium text-gray-500">{stat.label}</span>
                                <div className={`p-2 rounded-lg ${stat.color}`}>{stat.icon}</div>
                            </div>
                            <div className="flex items-end justify-between">
                                <span className="text-2xl font-bold text-gray-900">{stat.value}</span>
                                <span className={`text-xs font-medium px-2 py-1 rounded-full ${
                                    stat.positive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                                }`}>
                                    {stat.change} vs last month
                                </span>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Recent claims + new clients */}
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">

                    {/* Recent claims table */}
                    <div className="xl:col-span-2 bg-white rounded-xl border border-gray-200">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-900">Recent Claims</h2>
                            <button className="text-xs text-blue-600 hover:underline font-medium">View all</button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                                        <th className="px-5 py-3 font-medium">Claim ID</th>
                                        <th className="px-5 py-3 font-medium">Client</th>
                                        <th className="px-5 py-3 font-medium hidden md:table-cell">Policy</th>
                                        <th className="px-5 py-3 font-medium">Amount</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {recentClaims.map((claim) => (
                                        <tr key={claim.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-5 py-3.5 font-mono text-xs text-gray-500">{claim.id}</td>
                                            <td className="px-5 py-3.5">
                                                <span className="font-medium text-gray-900">{claim.client}</span>
                                                <p className="text-xs text-gray-400">{claim.date}</p>
                                            </td>
                                            <td className="px-5 py-3.5 text-gray-600 hidden md:table-cell">{claim.policy}</td>
                                            <td className="px-5 py-3.5 font-medium text-gray-900">{claim.amount}</td>
                                            <td className="px-5 py-3.5">
                                                <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${statusStyles[claim.status]}`}>
                                                    {claim.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* New clients */}
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-900">New Clients</h2>
                            <button className="text-xs text-blue-600 hover:underline font-medium">View all</button>
                        </div>
                        <div className="divide-y divide-gray-50">
                            {recentClients.map((client) => (
                                <div key={client.email} className="px-5 py-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
                                    <div className="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold flex-shrink-0">
                                        {client.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{client.name}</p>
                                        <p className="text-xs text-gray-400 truncate">{client.email}</p>
                                    </div>
                                    <div className="text-right flex-shrink-0">
                                        <p className="text-xs font-medium text-gray-900">{client.policies} {client.policies === 1 ? 'policy' : 'policies'}</p>
                                        <p className="text-xs text-gray-400">{client.joined}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Quick actions */}
                        <div className="px-5 py-4 border-t border-gray-100">
                            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Quick Actions</h3>
                            <div className="space-y-2">
                                <button className="w-full flex items-center gap-2 px-3 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    New Policy
                                </button>
                                <button className="w-full flex items-center gap-2 px-3 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    File a Claim
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
