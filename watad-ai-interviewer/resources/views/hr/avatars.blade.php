@extends('layouts.app')
@section('title', 'Avatars · Watad')
@section('heading', 'Interviewer avatars')
@section('content')
@php
    $styles = ['friendly','formal','probing','rapid','socratic'];
    $genders = ['female','male','neutral'];
@endphp
<div class="grid md:grid-cols-2 gap-5">
    @foreach($avatars as $avatar)
        <form method="POST" action="{{ route('hr.avatars.update', $avatar) }}"
              class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 space-y-3">
            @csrf @method('PUT')
            <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold">{{ mb_substr($avatar->name,0,1) }}</span>
                <input name="name" value="{{ $avatar->name }}" class="font-semibold border-b border-transparent focus:border-slate-300 bg-transparent">
                <label class="ms-auto text-xs flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($avatar->is_active)> active</label>
            </div>
            <input name="role_label" value="{{ $avatar->role_label }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <div class="grid grid-cols-3 gap-2 text-sm">
                <select name="gender" class="rounded-lg border border-slate-300 px-2 py-2">@foreach($genders as $g)<option @selected($avatar->gender===$g)>{{ $g }}</option>@endforeach</select>
                <select name="questioning_style" class="rounded-lg border border-slate-300 px-2 py-2">@foreach($styles as $s)<option @selected($avatar->questioning_style===$s)>{{ $s }}</option>@endforeach</select>
                <select name="language" class="rounded-lg border border-slate-300 px-2 py-2"><option value="en" @selected($avatar->language==='en')>EN</option><option value="ar" @selected($avatar->language==='ar')>AR</option></select>
            </div>
            <textarea name="personality" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $avatar->personality }}</textarea>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <input name="video_provider" placeholder="video provider (tavus/heygen)" value="{{ $avatar->video_provider }}" class="rounded-lg border border-slate-300 px-2 py-2">
                <input name="video_replica_id" placeholder="replica/avatar id" value="{{ $avatar->video_replica_id }}" class="rounded-lg border border-slate-300 px-2 py-2">
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">Save</button>
        </form>
    @endforeach
</div>
@endsection
