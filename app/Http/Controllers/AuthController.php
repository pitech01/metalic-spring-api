<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed',
            'country' => 'required|string',
            'referral_code' => 'nullable|string',
        ]);

        $user = \App\Models\User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => bcrypt($fields['password']),
            'country' => $fields['country'],
            'referral_code' => $fields['referral_code'] ?? null,
            'role' => 'user',
        ]);

        // Generate signed verification URL (valid for 60 minutes)
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Send verification email
        // Send verification email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\UserRegistered($user, $verificationUrl));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Registration mail failed: ' . $e->getMessage());
            // Proceed to return success so the user isn't stuck. They can use "Resend Verification" later.
        }

        // Note: We are NOT logging the user in yet. They must verify email first.
        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user_email' => $user->email
        ], 201);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = \App\Models\User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->email))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
             return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?verified=1');
        }

        $user->markEmailAsVerified();

        // Redirect to frontend login page with success flag
        return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?verified=1');
    }

    public function login(Request $request) {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = \App\Models\User::where('email', $fields['email'])->first();

        if(!$user || !\Illuminate\Support\Facades\Hash::check($fields['password'], $user->password)) {
            return response()->json([
                'message' => 'Bad credentials'
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email not verified. Please check your inbox.',
                'email_unverified' => true,
                'email' => $user->email
            ], 403);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'role' => $user->role
        ], 200);
    }
    public function resendVerificationEmail(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|email'
        ]);

        $user = \App\Models\User::where('email', $fields['email'])->first();

        if (!$user) {
            // Return success even if user not found to prevent user Enumeration
            return response()->json(['message' => 'If your email is registered, a verification link has been sent.']);
        }

        if ($user->hasVerifiedEmail()) {
             return response()->json(['message' => 'Email already verified. You can log in.']);
        }

        // Generate signed verification URL (valid for 60 minutes)
        $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Send verification email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\UserRegistered($user, $verificationUrl));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Mail sending failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to send email. Please check server logs or contact support. Error: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Verification link resent. Please check your email.']);
    }
}
