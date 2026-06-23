<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvatarController extends Controller
{
    public function index(): View
    {
        return view('hr.avatars', ['avatars' => Avatar::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Avatar());

        return back()->with('status', 'Avatar saved.');
    }

    public function update(Request $request, Avatar $avatar): RedirectResponse
    {
        $this->save($request, $avatar);

        return back()->with('status', 'Avatar updated.');
    }

    private function save(Request $request, Avatar $avatar): void
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'role_label'        => ['required', 'string', 'max:120'],
            'gender'            => ['required', 'in:female,male,neutral'],
            'questioning_style' => ['required', 'in:friendly,formal,probing,rapid,socratic'],
            'language'          => ['required', 'in:en,ar'],
            'personality'       => ['required', 'string', 'max:2000'],
            'voice_provider'    => ['nullable', 'string', 'max:40'],
            'voice_id'          => ['nullable', 'string', 'max:120'],
            'video_provider'    => ['nullable', 'string', 'max:40'],
            'video_replica_id'  => ['nullable', 'string', 'max:120'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $avatar->fill([...$data, 'is_active' => (bool) $request->boolean('is_active', true)])->save();
    }
}
