<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransactionalEmail;

class EmailController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:255',
            'body'    => 'required|string',
        ]);

        try {
            Mail::to($validated['to'])->send(
                new TransactionalEmail($validated['subject'], $validated['body'])
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
