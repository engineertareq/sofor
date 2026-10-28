<x-layouts.admin title="Users" :active="'users'" heading="Travellers" subtitle="Search, pause or promote any Sofor account." wide>

    <div class="flex flex-col gap-5">
        <x-card padding="p-4">
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                    @foreach (['all' => 'All', 'active' => 'Active', 'paused' => 'Paused'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                           @class([
                               'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                               'bg-surface text-ink shadow-soft' => $filters['status'] === $key,
                               'text-muted hover:text-ink' => $filters['status'] !== $key,
                           ])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="flex items-center gap-1 rounded-2xl border border-line bg-surface-2 p-1">
                    @foreach (['all' => 'Everyone', 'admin' => 'Admins', 'user' => 'Travellers'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['role' => $key, 'page' => null]) }}"
                           @class([
                               'rounded-xl px-3.5 py-2 text-[13px] font-semibold transition',
                               'bg-surface text-ink shadow-soft' => $filters['role'] === $key,
                               'text-muted hover:text-ink' => $filters['role'] !== $key,
                           ])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="lg:ml-auto lg:w-72">
                    <x-input name="q" icon="search" placeholder="Name, username, email or phone" :value="$filters['q']" />
                </div>
            </form>
        </x-card>

        <x-card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-[13px]">
                    <thead class="border-b border-line text-[10px] uppercase tracking-wide text-faint">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Traveller</th>
                            <th class="px-3 py-3 font-semibold">Contact</th>
                            <th class="px-3 py-3 text-center font-semibold">Trips</th>
                            <th class="px-3 py-3 text-center font-semibold">Role</th>
                            <th class="px-3 py-3 text-center font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($users as $user)
                            <tr class="transition hover:bg-surface-2">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$user->name" :gradient="$user->avatarGradient()" :src="$user->avatarUrl()" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.users.show', $user) }}" class="truncate font-medium text-ink hover:text-brand">{{ $user->name }}</a>
                                            <p class="text-[10px] text-faint">{{ '@'.$user->username }} - joined {{ $user->created_at->format('d M Y') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-muted">
                                    <p class="truncate">{{ $user->email ?: '-' }}</p>
                                    <p class="text-[10px] text-faint">{{ $user->phone ? \App\Support\Phone::pretty($user->phone) : '-' }}</p>
                                </td>
                                <td class="px-3 py-3 text-center text-muted">
                                    {{ $user->owned_trips_count }} led / {{ $user->memberships_count }} joined
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <x-badge :tone="$user->isAdmin() ? 'violet' : 'slate'">{{ $user->isAdmin() ? 'Admin' : 'User' }}</x-badge>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <x-badge :tone="$user->is_active ? 'lime' : 'rose'">{{ $user->is_active ? 'Active' : 'Paused' }}</x-badge>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                                            @csrf
                                            <button type="submit" class="rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                                                {{ $user->is_active ? 'Pause' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="role" value="{{ $user->isAdmin() ? 'user' : 'admin' }}">
                                            <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                                            <button type="submit" class="rounded-xl border border-line bg-surface px-2.5 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink">
                                                {{ $user->isAdmin() ? 'Demote' : 'Make admin' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm(@js('Delete '.$user->name.' and all their data? This cannot be undone.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex size-8 items-center justify-center rounded-xl text-faint transition hover:bg-negative-tint hover:text-negative" aria-label="Delete user">
                                                <x-icon name="trash" class="size-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-muted">No travellers match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $users->links() }}
    </div>
</x-layouts.admin>
