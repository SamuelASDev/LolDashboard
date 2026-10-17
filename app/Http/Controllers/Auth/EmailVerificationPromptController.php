<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RedirectAfterAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request, RedirectAfterAuthentication $redirect): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? $redirect->execute($request)
                    : view('auth.verify-email');
    }
}
