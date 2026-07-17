@php $user = auth()->user(); @endphp
<nav x-data="{ open: false }" class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    @if ($brand->logoUrl())
                        <div class="h-8 w-8 rounded-lg overflow-hidden">
                            <img src="{{ $brand->logoUrl() }}" alt="Logo {{ $brand->app_name }}" class="h-full w-full object-cover">
                        </div>
                    @else
                        <div class="h-8 w-8 rounded-lg bg-indigo-600 text-white grid place-items-center font-bold">{{ $brand->initial() }}</div>
                    @endif
                    <span class="font-semibold text-gray-900">{{ $brand->app_name }}</span>
                </a>

                <div class="hidden md:flex items-center gap-1">
                    @if ($user?->isAdmin())
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                        <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                            <button @click="open = !open" @click.outside="open = false"
                                    class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-indigo-700 hover:bg-gray-50 inline-flex items-center gap-1
                                           {{ request()->routeIs('products.*') || request()->routeIs('stock_in.*') || request()->routeIs('platform_deductions.*') || request()->routeIs('reports.products') || request()->routeIs('reports.stock*') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                Produk
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition class="absolute mt-1 w-56 rounded-md bg-white shadow-lg border border-gray-200 z-20">
                                <a href="{{ route('products.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Produk</a>
                                <a href="{{ route('stock_in.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Input Barang Masuk</a>
                                <a href="{{ route('platform_deductions.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Kelola Potongan</a>
                                <a href="{{ route('reports.products') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 border-t">Laporan Produk</a>
                                <a href="{{ route('reports.stock') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Laporan Stok</a>
                            </div>
                        </div>
                        <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                            <button @click="open = !open" @click.outside="open = false"
                                    class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-indigo-700 hover:bg-gray-50 inline-flex items-center gap-1
                                           {{ request()->routeIs('orders.*') || request()->routeIs('reports.orders') || request()->routeIs('returns.*') || request()->routeIs('reports.returns') || request()->routeIs('reports.live') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                Pesanan
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition class="absolute mt-1 w-56 rounded-md bg-white shadow-lg border border-gray-200 z-20">
                                <a href="{{ route('orders.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Daftar Pesanan</a>
                                <a href="{{ route('orders.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Tambah Pesanan</a>
                                <a href="{{ route('reports.orders') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 border-t">Laporan Pesanan</a>
                                <a href="{{ route('returns.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 border-t">Kelola Return</a>
                                <a href="{{ route('reports.returns') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Laporan Return</a>
                                <a href="{{ route('reports.live') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Laporan Live</a>
                            </div>
                        </div>
                        <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                            <button @click="open = !open" @click.outside="open = false"
                                    class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:text-indigo-700 hover:bg-gray-50 inline-flex items-center gap-1
                                           {{ request()->routeIs('orders.import*') ? 'bg-indigo-50 text-indigo-700' : '' }}">
                                Import
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition class="absolute mt-1 w-48 rounded-md bg-white shadow-lg border border-gray-200 z-20">
                                <a href="{{ route('orders.import.pdf.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Import PDF Label</a>
                                <a href="{{ route('orders.import.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Import CSV</a>
                                <a href="{{ route('combo_mappings.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 border-t">Combo Mapping</a>
                            </div>
                        </div>
                        <x-nav-link :href="route('scan.index')" :active="request()->routeIs('scan.*')">Scan</x-nav-link>
                        <x-nav-link :href="route('reports.packing')" :active="request()->routeIs('reports.packing*')">Laporan Packing</x-nav-link>
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">User</x-nav-link>
                        <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">Pengaturan</x-nav-link>
                    @elseif ($user?->isPacking())
                        <x-nav-link :href="route('scan.index')" :active="request()->routeIs('scan.*')">Scan</x-nav-link>
                        <x-nav-link :href="route('reports.packing')" :active="request()->routeIs('reports.packing*')">Laporan Packing</x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden md:flex items-center gap-3">
                @auth
                    {{-- Avatar + nama: avatar di-link ke halaman edit user
                         sendiri kalau admin (bisa edit profil sendiri),
                         kalau packing user cuma display saja. --}}
                    @if ($user->isAdmin())
                        <a href="{{ route('users.edit', $user) }}" class="flex items-center gap-3 hover:bg-gray-50 rounded-md px-2 py-1 -mr-2"
                           title="Edit profil saya">
                            <div class="h-9 w-9 rounded-full border border-gray-200 bg-indigo-50 grid place-items-center overflow-hidden shrink-0">
                                @if ($user->imageUrl())
                                    <img src="{{ $user->imageUrl() }}" alt="Foto {{ $user->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-indigo-600">{{ $user->initials() }}</span>
                                @endif
                            </div>
                            <div class="text-right text-sm leading-tight">
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-gray-500 capitalize">{{ $user->role }}</div>
                            </div>
                        </a>
                    @else
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full border border-gray-200 bg-indigo-50 grid place-items-center overflow-hidden shrink-0">
                                @if ($user->imageUrl())
                                    <img src="{{ $user->imageUrl() }}" alt="Foto {{ $user->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-indigo-600">{{ $user->initials() }}</span>
                                @endif
                            </div>
                            <div class="text-right text-sm leading-tight">
                                <div class="font-medium">{{ $user->name }}</div>
                                <div class="text-xs text-gray-500 capitalize">{{ $user->role }}</div>
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">Logout</button>
                    </form>
                @endauth
            </div>

            <div class="md:hidden flex items-center">
                <button @click="open = !open" class="p-2 rounded-md text-gray-600 hover:bg-gray-100">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" class="md:hidden border-t border-gray-200">
        <div class="px-4 py-3 space-y-1">
            @if ($user?->isAdmin())
                <x-responsive-nav-link :href="route('dashboard')">Dashboard</x-responsive-nav-link>
                <div class="px-3 pt-2 pb-1 text-xs uppercase text-gray-400">Produk</div>
                <x-responsive-nav-link :href="route('products.index')">Produk</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stock_in.create')">Input Barang Masuk</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('platform_deductions.index')">Kelola Potongan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.products')">Laporan Produk</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.stock')">Laporan Stok</x-responsive-nav-link>
                <div class="px-3 pt-2 pb-1 text-xs uppercase text-gray-400">Pesanan</div>
                <x-responsive-nav-link :href="route('orders.index')">Daftar Pesanan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('orders.create')">Tambah Pesanan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.orders')">Laporan Pesanan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('returns.index')">Kelola Return</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.returns')">Laporan Return</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.live')">Laporan Live</x-responsive-nav-link>
                <div class="px-3 pt-2 pb-1 text-xs uppercase text-gray-400">Import</div>
                <x-responsive-nav-link :href="route('orders.import.pdf.show')">Import PDF</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('orders.import.show')">Import CSV</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('combo_mappings.index')">Combo Mapping</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('scan.index')">Scan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.packing')">Laporan Packing</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.index')">User</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('settings.edit')">Pengaturan</x-responsive-nav-link>
            @elseif ($user?->isPacking())
                <x-responsive-nav-link :href="route('scan.index')">Scan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.packing')">Laporan Packing</x-responsive-nav-link>
            @endif
            @auth
                <div class="pt-3 border-t mt-2 text-sm">
                    <div class="px-3 pb-2 flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full border border-gray-200 bg-indigo-50 grid place-items-center overflow-hidden shrink-0">
                            @if ($user->imageUrl())
                                <img src="{{ $user->imageUrl() }}" alt="Foto {{ $user->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-sm font-semibold text-indigo-600">{{ $user->initials() }}</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-gray-500 capitalize">{{ $user->role }}</div>
                        </div>
                    </div>
                    @if ($user->isAdmin())
                        <a href="{{ route('users.edit', $user) }}" class="block px-3 py-2 text-sm text-indigo-700 hover:bg-gray-50">Edit Profil Saya</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="px-3">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </div>
</nav>
