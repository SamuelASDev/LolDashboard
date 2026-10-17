<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RedirectAfterAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request, RedirectAfterAuthentication $redirect): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $redirect->execute($request, ['verified' => 1]);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $redirect->execute($request, ['verified' => 1]);
    }
}
