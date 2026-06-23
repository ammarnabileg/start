@extends('layouts.app')
@section('title', 'Avatars · Watad')
@section('heading', 'Avatars')
@section('content')
@php
    $styles  = ['friendly', 'formal', 'probing', 'rapid', 'socratic'];
    $genders = ['female', 'male', 'neutral'];
@endphp
<x-page-header title="Interviewer avatars" />

<div class="grid gap-5 md:grid-cols-2">
    @foreach($avatars as $avatar)
        <form method="POST" action="{{ route('hr.avatars.update', $avatar) }}" class="card space-y-3 p-5">
            @csrf @method('PUT')
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-light font-bold text-brand">{{ mb_substr($avatar->name,0,1) }}</span>
                <input name="name" value="{{ $avatar->name }}" class="border-b border-transparent bg-transparent font-semibold text-slate-800 focus:border-slate-300 focus:outline-none">
                <label class="ms-auto flex items-center gap-1 text-xs text-slate-500"><input type="checkbox" name="is_active" value="1" @checked($avatar->is_active)> active</label>
            </div>
            <input name="role_label" value="{{ $avatar->role_label }}" class="input">
            <div class="grid grid-cols-3 gap-2">
                <select name="gender" class="input">@foreach($genders as $g)<option @selected($avatar->gender===$g)>{{ $g }}</option>@endforeach</select>
                <select name="questioning_style" class="input">@foreach($styles as $s)<option @selected($avatar->questioning_style===$s)>{{ $s }}</option>@endforeach</select>
                <select name="language" class="input"><option value="en" @selected($avatar->language==='en')>EN</option><option value="ar" @selected($avatar->language==='ar')>AR</option></select>
            </div>
            <textarea name="personality" rows="2" class="input">{{ $avatar->personality }}</textarea>
            <div class="grid grid-cols-2 gap-2">
                <input name="video_provider" placeholder="video provider (tavus/heygen)" value="{{ $avatar->video_provider }}" class="input">
                <input name="video_replica_id" placeholder="replica/avatar id" value="{{ $avatar->video_replica_id }}" class="input">
            </div>
            <button class="btn-primary">Save</button>
        </form>
    @endforeach
</div>
@endsection
