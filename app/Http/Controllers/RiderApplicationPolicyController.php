<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RiderApplicationPolicyController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'rider_application_terms' => 'required|string|max:12000',
            'rider_application_privacy' => 'required|string|max:12000',
        ]);

        $logistic = $request->user()->ownedLogistic()->firstOrFail();
        $logistic->forceFill($validated)->save();

        return back()->with('success', 'Rider application policies saved.');
    }
}
