<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\SmsDirection;
use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Twilio\TwiML\MessagingResponse;

class TwilioSmsController extends Controller
{
    public function incoming(Request $request): Response
    {
        $input = $request->all();

        $sid = data_get($input, 'MessageSid');
        $from = data_get($input, 'From');
        $to = data_get($input, 'To');
        $body = data_get($input, 'Body', '');

        SmsMessage::create([
            'sid' => $sid,
            'from' => $from,
            'to' => $to,
            'body' => $body,
            'direction' => SmsDirection::Inbound,
            'status' => 'received',
        ]);

        $response = new MessagingResponse;

        return response($response->__toString(), 200)
            ->header('Content-Type', 'text/xml');
    }

    public function status(Request $request): Response
    {
        $input = $request->all();

        $sid = data_get($input, 'MessageSid');
        $status = data_get($input, 'MessageStatus');

        if ($sid && $status) {
            SmsMessage::where('sid', $sid)->update(['status' => $status]);
        }

        return response('', 200);
    }
}
