<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RiderDocumentController extends Controller
{
    public function upload(Request $request, string $type)
    {
        $request->merge(['document_type' => $type]);
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $owner = $request->user();
        $directory = "rider-documents/replacements/{$owner->id}";
        try {
            $path = $validated['document']->store($directory, 'supabase_private');
        } catch (Throwable $e) {
            report($e);
            $path = false;
        }

        if (!$path) {
            throw ValidationException::withMessages(['document' => 'Unable to upload. Please try again.']);
        }

        try {
            $previous = DB::transaction(function () use ($owner, $type, $path) {
                $rider = User::query()->lockForUpdate()->findOrFail($owner->id);
                $previous = $rider->{$type};

                $reviews = $rider->rider_document_reviews ?? [];
                $reviews[$type] = ['status' => 'pending_review'];

                $rider->{$type} = $path;
                $rider->rider_document_reviews = $reviews;
                $rider->save();

                return $previous;
            });
        } catch (Throwable $e) {
            $this->deleteReplacement($path);
            throw $e;
        }

        if (is_string($previous) && str_starts_with($previous, $directory . '/')) {
            $this->deleteReplacement($previous);
        }

        return redirect()->to(route('rider.account') . '#rider-documents')
            ->with('success', 'Document uploaded and ready for logistics review.');
    }

    public function review(Request $request, User $rider, string $type)
    {
        $logisticId = $request->user()->ownedLogistic?->id;

        DB::transaction(function () use ($request, $rider, $type, $logisticId) {
            $rider = User::query()->lockForUpdate()->findOrFail($rider->id);

            abort_unless($logisticId && $rider->isRider() && (int) $rider->logistic_id === (int) $logisticId, 403);

            $validated = $request->validate([
                'document_version' => ['required', 'string', 'size:64'],
                'decision' => ['required', 'in:verified,needs_replacement'],
                'reason' => ['required_if:decision,needs_replacement', 'nullable', 'string', 'max:500'],
            ]);

            $path = $rider->{$type};
            if (!$path || !hash_equals(hash('sha256', $path), $validated['document_version'])) {
                throw ValidationException::withMessages([
                    'document_version' => 'The document has changed. Open the current file and review it again.',
                ]);
            }

            if ($validated['decision'] === 'verified' && !Storage::disk('supabase_private')->exists($path)) {
                throw ValidationException::withMessages([
                    'document_version' => 'The uploaded file is unavailable. Request a replacement.',
                ]);
            }

            $reviews = $rider->rider_document_reviews ?? [];
            $reviews[$type] = [
                'status' => $validated['decision'],
                'reason' => $validated['decision'] === 'needs_replacement' ? $validated['reason'] : null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toDateTimeString(),
            ];
            $rider->rider_document_reviews = $reviews;
            $rider->save();
        });

        return redirect()->route('logistic.riders.show', $rider)
            ->with('success', 'Document review saved.');
    }

    private function deleteReplacement(string $path): void
    {
        try {
            Storage::disk('supabase_private')->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
