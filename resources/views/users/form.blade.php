@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'User Baru')

@section('content')
    @php($header = $user->exists ? 'Edit User: '.$user->name : 'User Baru')

    <div class="card max-w-xl">
        {{-- enctype="multipart/form-data" wajib supaya upload foto profil
             ke-route ke server. Kalau kosong, image field di-skip & data
             user lain (nama, email, password) tetap ke-submit normal. --}}
        <form method="POST"
              action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
              enctype="multipart/form-data"
              class="space-y-4"
              x-data="{
                  preview: @js($user->exists ? $user->imageUrl() : null),
                  removed: false,
                  pickFile(event) {
                      const file = event.target.files[0];
                      if (!file) return;
                      this.removed = false;
                      this.preview = URL.createObjectURL(file);
                  },
                  removeImage() {
                      this.preview = null;
                      this.removed = true;
                      this.$refs.fileInput.value = '';
                  },
              }">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            {{-- Foto profil --}}
            <div>
                <label class="label">Foto Profil</label>
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 rounded-full border border-gray-200 bg-indigo-50 grid place-items-center overflow-hidden shrink-0">
                        <template x-if="preview">
                            <img :src="preview" alt="Foto profil" class="h-full w-full object-cover">
                        </template>
                        <template x-if="!preview">
                            <span class="text-2xl font-semibold text-indigo-600">{{ $user->initials() }}</span>
                        </template>
                    </div>
                    <div class="flex-1 space-y-2">
                        <input type="file"
                               name="image"
                               accept="image/*"
                               x-ref="fileInput"
                               @change="pickFile($event)"
                               class="block w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500">JPG, PNG, GIF, atau WEBP. Maks 2 MB.</p>
                        @if ($user->exists && $user->image)
                            <label class="inline-flex items-center gap-2 text-xs text-red-600 cursor-pointer"
                                   x-show="preview || removed">
                                <input type="checkbox"
                                       name="remove_image"
                                       value="1"
                                       x-model="removed"
                                       @change="if (removed) removeImage()"
                                       class="rounded border-red-300">
                                Hapus foto saat simpan
                            </label>
                        @endif
                    </div>
                </div>
                @error('image')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label">Nama</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="input">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Email <span class="text-xs text-gray-500">(dipakai untuk login)</span></label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Username <span class="text-xs text-gray-500">(opsional, bisa dipakai untuk login)</span></label>
                <input name="username" value="{{ old('username', $user->username) }}" class="input">
                @error('username')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Role</label>
                <select name="role" class="input">
                    <option value="packing" @selected(old('role', $user->role) === 'packing')>Packing</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
            </div>
            <div>
                <label class="label">Password {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}</label>
                <input type="password" name="password" class="input" autocomplete="new-password">
                @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-gray-300">
                Akun aktif
            </label>

            <div class="flex gap-2">
                <button class="btn-primary" type="submit">Simpan</button>
                <a href="{{ route('users.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
