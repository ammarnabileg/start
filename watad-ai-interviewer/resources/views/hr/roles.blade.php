@extends('layouts.app')
@section('title', 'Roles & Permissions · Watad')
@section('heading', 'Roles & Permissions')
@section('content')
<div x-data="{ create: false }">
<x-page-header title="Roles & Permissions">
    @can('roles.create')
        <button @click="create = !create" class="btn-primary">＋ New role</button>
    @endcan
</x-page-header>

<p class="mb-6 max-w-2xl text-sm text-slate-500">
    Control exactly what each role can do — <strong>View, Create, Edit, Delete</strong> for every
    resource, plus special abilities. The Super Admin always has full control, and you can create
    unlimited custom roles.
</p>

@can('roles.create')
<form x-show="create" x-cloak method="POST" action="{{ route('hr.roles.store') }}"
      class="card mb-6 flex flex-wrap items-end gap-3 p-5">
    @csrf
    <div><label class="label">Role name</label><input name="name" required placeholder="e.g. Sales Interviewer" class="input"></div>
    <div class="flex-1"><label class="label">Description</label><input name="description" class="input"></div>
    <button class="btn-primary">Create role</button>
</form>
@endcan

<div class="space-y-6">
    @foreach($roles as $role)
        @php($isSuper = $role->slug === 'super_admin')
        @php($held = $role->permissions->pluck('slug')->all())
        <form method="POST" action="{{ route('hr.roles.update', $role) }}" class="card p-5"
              x-data="{ all: {{ $isSuper ? 'true' : 'false' }} }">
            @csrf @method('PUT')
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-800">{{ $role->name }}</h3>
                    <span class="text-xs text-slate-400">{{ $role->slug }}</span>
                </div>
                @if($isSuper)
                    <span class="badge-soft bg-emerald-100 text-emerald-700">Full control (locked)</span>
                @else
                    <button class="btn-primary">Save</button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-500">
                            <th class="py-2 text-start font-medium">Resource</th>
                            @foreach($actions as $key => $label)
                                <th class="px-2 text-center font-medium">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($resources as $resource => $label)
                        <tr class="border-b border-slate-50">
                            <td class="py-2 text-slate-700">{{ $label }}</td>
                            @foreach($actions as $action => $actionLabel)
                                @php($slug = "{$resource}.{$action}")
                                <td class="px-2 text-center">
                                    <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                           @checked($isSuper || in_array($slug, $held, true))
                                           @disabled($isSuper)
                                           class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if($extra)
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Special abilities</div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($extra as $slug => $meta)
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="permissions[]" value="{{ $slug }}"
                                       @checked($isSuper || in_array($slug, $held, true))
                                       @disabled($isSuper)
                                       class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
                                {{ $meta[0] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </form>
    @endforeach
</div>
</div>
@endsection
