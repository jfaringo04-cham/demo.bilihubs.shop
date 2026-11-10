<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\VoucherClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class VoucherClaimController extends Controller
{
    public function store(
        Request $request,
        Voucher $voucher,
        VoucherClaimService $claimService
    ) {
        try {
            $claimService->claim((int) Auth::id(), (int) $voucher->id);

            return back()
                ->with('success', 'Voucher claimed successfully. Enter its code during checkout.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->with('error', 'Unable to claim this voucher right now. Please try again.');
        }
    }
}
