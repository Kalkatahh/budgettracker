<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Google\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class GoogleDriveController extends Controller
{
    public function redirectToGoogle()
{
    $user = Auth::user();
    if (!$user || !$user->is_premium) {
        return response()->json(['error' => 'This feature is only available for premium users.'], 403);
    }

    $client = new Client();
    $client->setClientId(config('services.google.client_id'));
    $client->setClientSecret(config('services.google.client_secret'));
    $client->setRedirectUri(route('google.auth.callback'));
    $client->addScope('https://www.googleapis.com/auth/drive');
    $client->setAccessType('offline');

    $authUrl = $client->createAuthUrl();
    return response()->json(['authUrl' => $authUrl]);
}

public function handleGoogleCallback(Request $request)
{
    $user = Auth::user();
    if (!$user || !$user->is_premium) {
        return response()->json(['error' => 'This feature is only available for premium users.'], 403);
    }

    $client = new Client();
    $client->setClientId(config('services.google.client_id'));
    $client->setClientSecret(config('services.google.client_secret'));
    $client->setRedirectUri(route('google.auth.callback'));

    $token = $client->fetchAccessTokenWithAuthCode($request->code);
    if (!isset($token['access_token']) || !isset($token['refresh_token'])) {
        Log::error('Google Drive token fetch failed', ['token' => $token]);
        return redirect('/dashboard')->with('error', 'Failed to authenticate with Google Drive.');
    }

    $user->update([
        'google_drive_access_token' => $token['access_token'],
        'google_drive_refresh_token' => $token['refresh_token'],
        'google_drive_token_expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
    ]);

    Log::info('Google Drive tokens updated for user', ['user_id' => $user->id, 'token' => $token]);
    return redirect('/dashboard')->with('success', 'Google Drive authentication successful!');
}
}