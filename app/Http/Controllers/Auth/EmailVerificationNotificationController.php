<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RedirectAfterAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, RedirectAfterAuthentication $redirect): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $redirect->execute($request);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
