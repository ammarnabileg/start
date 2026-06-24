<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Services\HeyGenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Avatar::where('tenant_id', auth()->user()->tenant_id)->withCount('jobs')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string', 'gender' => 'nullable|in:male,female', 'personality' => 'nullable|string', 'language' => 'nullable|string', 'heygen_avatar_id' => 'nullable|string', 'heygen_voice_id' => 'nullable|string']);
        $avatar = Avatar::create(array_merge($request->validated(), ['tenant_id' => auth()->user()->tenant_id]));
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store("avatars", 'public');
            $avatar->update(['photo' => $path]);
        }
        return response()->json($avatar, 201);
    }

    public function update(Request $request, Avatar $avatar): JsonResponse
    {
        abort_unless($avatar->tenant_id === auth()->user()->tenant_id, 403);
        $avatar->update($request->except('photo'));
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store("avatars", 'public');
            $avatar->update(['photo' => $path]);
        }
        return response()->json($avatar);
    }

    public function destroy(Avatar $avatar): JsonResponse
    {
        abort_unless($avatar->tenant_id === auth()->user()->tenant_id, 403);
        $avatar->delete();
        return response()->json(['message' => 'Avatar deleted']);
    }

    public function heygenAvatars(): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $heygen = new HeyGenService($tenant->getEffectiveHeygenKey());
        try {
            $avatars = $heygen->listAvatars();
            return response()->json($avatars);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
