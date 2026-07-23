<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class FirebaseService
{
    protected $credentials;
    protected $projectId;

    public function __construct()
    {
        $this->credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            base_path(env('FIREBASE_CREDENTIALS'))
        );

        $this->projectId = env('FIREBASE_PROJECT_ID');
    }

    private function getAccessToken()
    {
        $token = $this->credentials->fetchAuthToken();
        return $token['access_token'];
    }

    public function sendNotification($deviceToken, $title, $body)
    {
        $client = new Client();

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $message = [
            "message" => [
                "token" => $deviceToken,
                "notification" => [
                    "title" => $title,
                    "body"  => $body,
                ],
            ]
        ];

        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type'  => 'application/json',
            ],
            'json' => $message,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
