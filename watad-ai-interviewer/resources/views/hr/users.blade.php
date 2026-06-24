@extends('layouts.app')
@section('title', 'Users · Watad')
@section('heading', 'Users')
@section('content')
<x-page-header title="Users" />

<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('hr.users.store') }}" class="card self-start space-y-2 p-4 lg:col-span-1">
        @csrf
        <h3 class="text-sm font-semibold text-slate-800">Invite user</h3>
        <input name="name" placeholder="Name" required class="input">
        <input name="email" type="email" placeholder="Email" required class="input">
        <input name="password" type="password" placeholder="Temp password" required class="input">
        <div class="space-y-1 text-sm text-slate-600">
            @foreach($roles as $role)
                <label class="flex items-center gap-2"><input type="checkbox" name="roles[]" value="{{ $role->id }}" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"> {{ $role->name }}</label>
            @endforeach
        </div>
        <button class="btn-primary">Create user</button>
    </form>

    <div class="card overflow-hidden lg:col-span-2">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-slate-500">
                <tr><th class="px-5 py-3 text-start font-medium">User</th><th class="px-5 text-start font-medium">Roles</th></tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr class="border-b border-slate-50 align-top {{ $user->is_active ? '' : 'opacity-60' }}" x-data="{ editUser: false }">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-700">{{ $user->name }}</span>
                            @unless($user->is_active)<span class="badge-soft bg-slate-100 text-slate-500">Inactive</span>@endunless
                        </div>
                        <div class="text-xs text-slate-400">{{ $user->email }}</div>
                        @can('users.update')
                            <div class="mt-1 flex items-center gap-3">
                                <button @click="editUser = !editUser" class="text-xs font-medium text-brand">Edit</button>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('hr.users.status', $user) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="text-xs {{ $user->is_active ? 'text-amber-600' : 'text-emerald-600' }}">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @endif
                            </div>
                            <form x-show="editUser" x-cloak method="POST" action="{{ route('hr.users.update', $user) }}" class="mt-2 space-y-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $user->name }}" required class="input">
                                <input name="email" type="email" value="{{ $user->email }}" required class="input">
                                <input name="password" type="password" placeholder="New password (optional)" class="input">
                                <button class="btn-primary px-3 py-1.5 text-xs">Save</button>
                            </form>
                        @endcan
                    </td>
                    <td class="px-5 py-3">
                        <form method="POST" action="{{ route('hr.users.roles', $user) }}" class="flex flex-wrap items-center gap-2">
                            @csrf @method('PUT')
                            @foreach($roles as $role)
                                <label class="flex items-center gap-1 text-xs text-slate-600">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role->id)) class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
                                    {{ $role->name }}
                                </label>
                            @endforeach
                            <button class="btn-ghost px-2.5 py-1 text-xs">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
