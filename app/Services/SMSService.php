<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SMSService
{
    public function sendSMS($phone, $message)
{
    $encodedMessage = rawurlencode($message);
    $url = env('SMSPRIMA_BASE_URL') . "?" . http_build_query([
        'apikey'      => env('SMSPRIMA_API_KEY'),
        'sender'      => env('SMSPRIMA_SENDER'),
        'destination' => $phone,
        'type'        => env('SMSPRIMA_TYPE'),
        'message'     => $encodedMessage,
    ]);
 
        \Log::info("Sending SMS to: " . $phone);
        $response = Http::get($url);
        
        if ($response->successful()) {
            \Log::info("SMS sent successfully to: " . $phone);
        } else {
            \Log::error("Failed to send SMS to: " . $phone . ". Response: " . $response->body());
        }

        return $response->body();
}

}
