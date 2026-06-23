<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Candidate Portal authentication (the `candidate` guard). */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (! Auth::guard('candidate')->attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();
        Auth::guard('candidate')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function showRegister(): View
    {
        return view('portal.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:190'],
            'email'     => ['required', 'email', 'max:190', 'unique:candidate_users,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $candidate = Candidate::firstOrCreate(
            ['email' => $data['email']],
            ['full_name' => $data['full_name'], 'consent_at' => now(), 'source' => 'portal'],
        );
        $user = CandidateUser::create([
            'candidate_id' => $candidate->id,
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'is_active'    => true,
        ]);

        Auth::guard('candidate')->login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('candidate')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
