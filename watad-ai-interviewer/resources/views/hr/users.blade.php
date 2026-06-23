@extends('layouts.app')
@section('title', 'Users · Watad')
@section('heading', 'Users & roles')
@section('content')
<div class="grid lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ route('hr.users.store') }}"
          class="lg:col-span-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-2 self-start">
        @csrf
        <h3 class="font-semibold text-sm">Invite user</h3>
        <input name="name" placeholder="Name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="email" type="email" placeholder="Email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="password" type="password" placeholder="Temp password" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="text-sm space-y-1">
            @foreach($roles as $role)
                <label class="flex items-center gap-2"><input type="checkbox" name="roles[]" value="{{ $role->id }}"> {{ $role->name }}</label>
            @endforeach
        </div>
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-white text-sm">Create user</button>
    </form>

    <div class="lg:col-span-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-slate-500 border-b border-slate-100 dark:border-slate-800">
                <tr><th class="text-start font-medium p-3">User</th><th class="text-start font-medium">Roles</th></tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr class="border-b border-slate-50 dark:border-slate-800/50 align-top">
                    <td class="p-3"><div class="font-medium">{{ $user->name }}</div><div class="text-xs text-slate-500">{{ $user->email }}</div></td>
                    <td class="p-3">
                        <form method="POST" action="{{ route('hr.users.roles', $user) }}" class="flex flex-wrap gap-2 items-center">
                            @csrf @method('PUT')
                            @foreach($roles as $role)
                                <label class="flex items-center gap-1 text-xs">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role->id))>
                                    {{ $role->name }}
                                </label>
                            @endforeach
                            <button class="rounded bg-slate-800 px-2 py-1 text-white text-xs">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
