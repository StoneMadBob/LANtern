<?php

function verify_turnstile(string $secret, string $token, string $ipAddress): bool
{
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ipAddress
    ]);
    $endpoint = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    if (function_exists('curl_init')) {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10
            ]
        ]);
        $response = @file_get_contents($endpoint, false, $context);
    }

    if ($response === false) {
        return false;
    }

    $result = json_decode($response, true);
    return is_array($result) && !empty($result['success']);
}