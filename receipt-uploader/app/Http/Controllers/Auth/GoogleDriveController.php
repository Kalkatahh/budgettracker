<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Google\Client;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class GoogleDriveController extends Controller
{
    public function redirectToGoogle()
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(route('google.auth.callback')); // This now works with the named route
        $client->addScope('https://www.googleapis.com/auth/drive');
        $client->setAccessType('offline'); // Ensures a refresh token is returned

        $authUrl = $client->createAuthUrl();
        return redirect($authUrl);
    }

    public function handleGoogleCallback(Request $request)
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(route('google.auth.callback'));

        $token = $client->fetchAccessTokenWithAuthCode($request->code);
        if (isset($token['refresh_token'])) {
            $refreshToken = $token['refresh_token'];
            Log::info('Google Drive refresh token obtained', ['refresh_token' => $refreshToken]);
            // Update .env with the refresh token
            file_put_contents(base_path('.env'), str_replace(
                'GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token',
                'GOOGLE_DRIVE_REFRESH_TOKEN=' . $refreshToken,
                file_get_contents(base_path('.env'))
            ));
        }

        return redirect('/')->with('success', 'Google Drive authentication successful!'); // Redirect to Vue SPA root
    }
}