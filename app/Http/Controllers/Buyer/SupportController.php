<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index()
    {
        $tickets = Auth::user()->supportTickets()->latest()->get();
        return view('buyer.support.index', compact('tickets'));
    }

    public function create()
    {
        return view('buyer.support.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        Auth::user()->supportTickets()->create($request->all());

        return redirect()->route('buyer.support.index')->with('success', 'Support ticket created successfully.');
    }

    public function show(SupportTicket $ticket)
    {
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }
        return view('buyer.support.show', compact('ticket'));
    }
}
