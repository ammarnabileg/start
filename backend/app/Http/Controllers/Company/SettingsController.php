<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = Tenant::find(auth()->user()->tenant_id);

        return response()->json([
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'industry' => $tenant->industry,
            'country' => $tenant->country,
            'timezone' => $tenant->timezone,
            'default_language' => $tenant->default_language,
            'career_page_title' => $tenant->career_page_title,
            'career_page_description' => $tenant->career_page_description,
            'primary_color' => $tenant->primary_color,
            'has_openai_key' => !empty($tenant->openai_api_key),
            'has_heygen_key' => !empty($tenant->heygen_api_key),
            'settings' => $tenant->settings ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'default_language' => 'nullable|in:ar,en',
            'career_page_title' => 'nullable|string|max:255',
            'career_page_description' => 'nullable|string',
            'primary_color' => 'nullable|string|max:20',
            'openai_api_key' => 'nullable|string',
            'heygen_api_key' => 'nullable|string',
            'smtp_host' => 'nullable|string',
            'smtp_port' => 'nullable|integer',
            'smtp_user' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_from_email' => 'nullable|email',
            'smtp_from_name' => 'nullable|string',
            'settings' => 'nullable|array',
        ]);

        $tenant = Tenant::find(auth()->user()->tenant_id);

        $fillable = $request->only([
            'name', 'domain', 'industry', 'country', 'timezone',
            'default_language', 'career_page_title', 'career_page_description',
            'primary_color',
        ]);

        if ($request->filled('openai_api_key')) {
            $fillable['openai_api_key'] = $request->openai_api_key;
        }

        if ($request->filled('heygen_api_key')) {
            $fillable['heygen_api_key'] = $request->heygen_api_key;
        }

        if ($request->has('settings')) {
            $fillable['settings'] = array_merge($tenant->settings ?? [], $request->settings);
        }

        // Store SMTP settings inside tenant settings JSON
        $smtpFields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];
        $smtpData = $request->only($smtpFields);
        if (!empty(array_filter($smtpData))) {
            $currentSettings = $tenant->settings ?? [];
            $currentSettings['smtp'] = array_merge($currentSettings['smtp'] ?? [], $smtpData);
            $fillable['settings'] = $currentSettings;
        }

        $tenant->update($fillable);

        return response()->json(['message' => 'Settings updated successfully', 'settings' => $this->index()->getData(true)]);
    }
}
