@extends('layouts.app')
@section('title', 'User')

@section('content')
    @php($header = 'Kelola User')

    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <div></div>
            <a href="{{ route('users.create') }}" class="btn-primary">+ User Baru</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="py-2">Nama</th>
                        <th class="py-2">Email</th>
                        <th class="py-2">Role</th>
                        <th class="py-2">Status</th>
                        <th class="py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($users as $u)
                        <tr>
                            <td class="py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full border border-gray-200 bg-indigo-50 grid place-items-center overflow-hidden shrink-0">
                                        @if ($u->imageUrl())
                                            <img src="{{ $u->imageUrl() }}" alt="Foto {{ $u->name }}" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-sm font-semibold text-indigo-600">{{ $u->initials() }}</span>
                                        @endif
                                    </div>
                                    <span class="font-medium">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td class="py-3">{{ $u->email }}</td>
                            <td class="py-3">
                                <span class="badge {{ $u->role === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }} capitalize">{{ $u->role }}</span>
                            </td>
                            <td class="py-3">
                                @if ($u->is_active)
                                    <span class="badge bg-green-100 text-green-700">Aktif</span>
                                @else
                                    <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                                @endif
                            </td>
                            <td class="py-3 text-right">
                                <a href="{{ route('users.edit', $u) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline ml-2"
                                      onsubmit="return confirm('Hapus user ini?');">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection
