<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

class VoiceController extends Controller
{
    public function token(Request $request)
    {
        $user = $request->user();

        $accountSid = config('services.twilio.account_sid');
        $apiKey = config('services.twilio.api_key');
        $apiSecret = config('services.twilio.api_secret');
        $twimlAppSid = config('services.twilio.twiml_app_sid');

        $identity = 'agent_'.$user->id;

        $token = new AccessToken(
            $accountSid,
            $apiKey,
            $apiSecret,
            3600,
            $identity
        );

        $voiceGrant = new VoiceGrant;
        $voiceGrant->setOutgoingApplicationSid($twimlAppSid);
        $voiceGrant->setIncomingAllow(true);

        $token->addGrant($voiceGrant);

        return response()->json([
            'token' => $token->toJWT(),
            'identity' => $identity,
        ]);
    }
}
