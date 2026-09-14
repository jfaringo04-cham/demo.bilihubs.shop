<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    protected array $policyKeys = [
        'terms_of_service' => 'Terms of Service',
        'privacy_policy' => 'Privacy Policy',
        'refund_policy' => 'Refund & Return Policy',
        'shipping_policy' => 'Shipping Policy',
    ];

    public function index()
    {
        $announcements = Announcement::with('admin')->latest()->paginate(15);

        $policies = collect($this->policyKeys)->map(function ($label, $key) {
            $setting = PlatformSetting::firstOrCreate(
                ['key' => $key],
                ['label' => $label, 'value' => '']
            );
            return $setting;
        });

        return view('admin.settings', compact('announcements', 'policies'));
    }

    public function createAnnouncement()
    {
        return view('admin.announcements-create');
    }

    public function storeAnnouncement(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Announcement::create([
            'admin_id' => Auth::id(),
            'title' => $request->title,
            'body' => $request->body,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Announcement posted.');
    }

    public function toggleAnnouncement(Announcement $announcement)
    {
        $announcement->update(['is_active' => !$announcement->is_active]);

        return back()->with('success', 'Announcement visibility updated.');
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    public function updatePolicies(Request $request)
    {
        foreach ($this->policyKeys as $key => $label) {
            if ($request->has($key)) {
                PlatformSetting::updateOrCreate(
                    ['key' => $key],
                    ['label' => $label, 'value' => $request->input($key)]
                );
            }
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Platform policies updated.');
    }
}
