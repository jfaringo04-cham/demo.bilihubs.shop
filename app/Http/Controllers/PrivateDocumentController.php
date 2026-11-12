<?php

namespace App\Http\Controllers;

use App\Models\Logistic;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PrivateDocumentController extends Controller
{
    /**
     * View a shipment's proof of delivery.
     */
    public function showShipmentProof(Shipment $shipment)
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($shipment->proof_of_delivery, 404);

        $allowed = false;

        if ($user->isAdmin()) {
            $allowed = true;
        } elseif (
            $user->hasRole('rider')
            && (int) $shipment->rider_id === (int) $user->id
        ) {
            $allowed = true;
        } elseif (
            $user->ownedLogistic
            && (int) $shipment->logistic_id
            === (int) $user->ownedLogistic->id
        ) {
            $allowed = true;
        } elseif (
            $shipment->sellerOrder
            && (int) $shipment->sellerOrder->order?->user_id
            === (int) $user->id
        ) {
            $allowed = true;
        } elseif ($user->isSeller()) {
            $sellerUserId = $shipment->sellerOrder?->seller?->user_id;

            $allowed = $sellerUserId !== null
                && (int) $sellerUserId === (int) $user->id;
        }

        abort_unless($allowed, 403);

        $path = $shipment->proof_of_delivery;

        abort_unless(
            is_string($path)
                && str_starts_with($path, 'proof-of-delivery/')
                && !str_contains($path, '..')
                && !str_contains($path, '\\'),
            404
        );

        // Prefer Supabase private storage.
        $disk = Storage::disk('supabase_private');

        // Support historical local files during migration.
        if (!$disk->exists($path)) {
            $disk = Storage::disk('public');
        }

        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        abort_unless(
            in_array($mime, [
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true),
            415
        );

        return $this->streamPrivateFile($disk, $path, $mime);
    }

    /**
     * View registration documents.
     * Only accessible through the authenticated admin route.
     */
    public function showRegistrationDocument(User $user, string $type)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        return $this->streamRegistrationDocument($user, $type);
    }

    public function showRiderDocument(User $rider, string $type)
    {
        $logistic = Auth::user()?->ownedLogistic;

        abort_unless($logistic && $rider->isRider()
            && (int) $rider->logistic_id === (int) $logistic->id, 403);
        abort_unless(in_array($type, ['or_document', 'cr_document'], true), 404);

        return $this->streamRegistrationDocument($rider, $type);
    }

    private function streamRegistrationDocument(User $user, string $type)
    {
        $allowedTypes = [
            'id_verification',
            'business_permit',
            'logistic_permit',
            'sec_certificate',
            'bir_form_2303',
            'or_document',
            'cr_document',
        ];

        abort_unless(in_array($type, $allowedTypes, true), 404);

        if (in_array($type, ['logistic_permit', 'sec_certificate', 'bir_form_2303'], true)) {
            $logistic = Logistic::where('owner_user_id', $user->id)
                ->firstOrFail();

                        $path = match ($type) {
                'logistic_permit' => $logistic->business_permit,
                'sec_certificate' => $logistic->sec_certificate,
                'bir_form_2303' => $logistic->bir_form_2303,
            };

            // Verify actual path before enabling logistics permit viewing.
            $allowedFolders = [
                'seller-documents/',
                'logistic-documents/',
            ];
        } else {
            $path = $user->{$type};

            $allowedFolders = match ($type) {
                'id_verification' => ['customer-documents/'],
                'business_permit' => ['seller-documents/'],
                'or_document', 'cr_document' => ['rider-documents/'],
            };
        }

        abort_unless(
            is_string($path)
                && !str_contains($path, '..')
                && !str_contains($path, '\\')
                && collect($allowedFolders)->contains(
                    fn($folder) => str_starts_with($path, $folder)
                ),
            404
        );

        $disk = Storage::disk('supabase_private');

        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        abort_unless(
            in_array($mime, [
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf',
            ], true),
            415
        );

        return $this->streamPrivateFile($disk, $path, $mime);
    }

    /**
     * Stream a file without exposing a public storage URL.
     */
    private function streamPrivateFile($disk, string $path, string $mime)
    {
        return response()->stream(
            function () use ($disk, $path) {
                $stream = $disk->readStream($path);

                if ($stream === false) {
                    throw new \RuntimeException(
                        'Private document could not be read.'
                    );
                }

                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]
        );
    }
}
