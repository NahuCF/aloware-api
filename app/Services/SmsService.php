<?php

namespace App\Services;

use App\Enums\SmsDirection;
use App\Models\SmsMessage;
use Twilio\Rest\Client;

class SmsService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
    }

    public function send(string $to, string $body, ?int $userId = null): SmsMessage
    {
        $params = [
            'from' => config('services.twilio.phone_number'),
            'body' => $body,
            'statusCallback' => route('twilio.sms.status'),
        ];

        $message = $this->client->messages->create($to, $params);

        return SmsMessage::create([
            'sid' => $message->sid,
            'from' => $message->from,
            'to' => $message->to,
            'body' => $body,
            'direction' => SmsDirection::Outbound,
            'status' => $message->status,
            'user_id' => $userId,
        ]);
    }
}
