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
 
    \Log::info("SMSPrima URL: " . $url);
    $response = Http::get($url);
    \Log::info("SMSPrima Response: " . $response->body());
    return $response->body();
}

}
