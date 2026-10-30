<x-layouts.admin title="Users" :active="'users'" heading="Travellers" subtitle="Search, pause or promote any Sofor account.">

    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.tabs :tabs="['all' => 'All', 'active' => 'Active', 'paused' => 'Paused']" :current="$filters['status']" label="Account status" />
                <x-admin.tabs :tabs="['all' => 'Everyone', 'admin' => 'Admins', 'user' => 'Travellers']" :current="$filters['role']" param="role" label="Role" />
            </div>

            <x-admin.search :value="$filters['q']" placeholder="Name, username, email or phone"
                            :keep="['status' => $filters['status'], 'role' => $filters['role']]" class="sm:ml-auto sm:w-72" />
        </div>

        <x-card padding="p-0" class="overflow-x-auto">
            {{-- Below xl each traveller is a card; from a tablet up, contact, trips and account share a line. --}}
            <table class="max-xl:table-cards w-full text-left text-[13px] md:[--card-cols:4]">
                <thead class="border-b border-line text-[10px] uppercase tracking-wide text-faint">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Traveller</th>
                        <th class="px-3 py-2.5 font-semibold">Contact</th>
                        <th class="px-3 py-2.5 text-center font-semibold">Trips</th>
                        <th class="px-3 py-2.5 font-semibold">Account</th>
                        <th class="px-4 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-surface-2">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.show', $user) }}" class="block truncate font-semibold text-ink hover:text-brand">{{ $user->name }}</a>
                                        <p class="truncate text-[10.5px] text-faint">{{ '@'.$user->username }} - joined {{ $user->created_at->format('d M Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="col-span-3 px-3 py-2.5 text-muted md:col-span-2" data-label="Contact">
                                <p class="flex items-center gap-2">
                                    <span class="min-w-0 truncate">{{ $user->email ?: '-' }}</span>
                                    @if ($user->email)
                                        <x-verified :at="$user->hasVerifiedEmail() ? $user->email_verified_at : null" />
                                    @endif
                                </p>
                                <p class="mt-1 flex items-center gap-2 text-[10.5px] text-faint">
                                    <span class="num">{{ $user->phone ? \App\Support\Phone::pretty($user->phone) : '-' }}</span>
                                    @if ($user->phone)
                                        <x-verified :at="$user->hasVerifiedPhone() ? $user->phone_verified_at : null" />
                                    @endif
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-center text-muted" data-label="Trips">
                                {{ $user->owned_trips_count }} led / {{ $user->memberships_count }} joined
                            </td>
                            <td class="col-span-2 px-3 py-2.5 md:col-span-1" data-label="Account">
                                <span class="flex flex-wrap gap-1">
                                    @if ($user->isAdmin())
                                        <x-badge tone="violet" size="sm">Admin</x-badge>
                                    @endif
                                    <x-badge :tone="$user->is_active ? 'lime' : 'rose'" size="sm">{{ $user->is_active ? 'Active' : 'Paused' }}</x-badge>
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1.5 xl:justify-end">
                                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                        @csrf
                                        <x-admin.action :tone="$user->is_active ? 'warning' : 'default'">{{ $user->is_active ? 'Pause' : 'Activate' }}</x-admin.action>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="role" value="{{ $user->isAdmin() ? 'user' : 'admin' }}">
                                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                                        <x-admin.action>{{ $user->isAdmin() ? 'Demote' : 'Make admin' }}</x-admin.action>
                                    </form>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ml-auto xl:ml-0"
                                          onsubmit="return confirm(@js('Delete '.$user->name.' and all their data? This cannot be undone.'))">
                                        @csrf
                                        @method('DELETE')
                                        <x-admin.action icon="trash" tone="danger" label="Delete user" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-muted">No travellers match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        {{ $users->links() }}
    </div>
</x-layouts.admin>
