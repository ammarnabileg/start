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
                <tr class="border-b border-slate-50 align-top">
                    <td class="px-5 py-3"><div class="font-medium text-slate-700">{{ $user->name }}</div><div class="text-xs text-slate-400">{{ $user->email }}</div></td>
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
