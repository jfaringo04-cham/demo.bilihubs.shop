<?php

namespace App\Http\Controllers;

use App\Models\ReturnRefund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RefundDisputeProofController extends Controller
{
    public function store(Request $request, ReturnRefund $returnRefund)
    {
        $sellerId = $request->user()->seller?->id;
        abort_unless($sellerId && (int) $returnRefund->sellerOrder->seller_id === (int) $sellerId, 403);

        $request->validate(['refund_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        $path = $request->file('refund_proof')->store('refund-dispute-proof', 'local');
        if (!$path) {
            return back()->withErrors(['refund_proof' => 'The proof could not be saved.']);
        }

        try {
            DB::transaction(function () use ($returnRefund, $path) {
                $refund = ReturnRefund::lockForUpdate()->findOrFail($returnRefund->id);
                if (!$refund->reported_at || !$refund->dispute_proof_requested_at
                    || $refund->dispute_proof_path || $refund->dispute_resolved_at || $refund->received_at) {
                    throw ValidationException::withMessages(['refund_proof' => 'This dispute is no longer accepting proof.']);
                }

                $refund->update([
                    'dispute_proof_path' => $path,
                    'dispute_proof_submitted_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Refund transfer proof submitted.');
    }
}
