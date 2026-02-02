<?php

namespace App\Http\Middleware;

use App\Helpers\EnvironmentHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (EnvironmentHelper::isLocal()) {
            return $next($request);
        }

        $authToken = config('services.twilio.auth_token');

        if (empty($authToken)) {
            return response()->json(['message' => 'Twilio auth token not configured'], 500);
        }

        $validator = new RequestValidator($authToken);
        $signature = $request->header('X-Twilio-Signature', '');
        $url = $request->fullUrl();
        $params = $request->all();

        if (!$validator->validate($signature, $url, $params)) {
            return response()->json(['message' => 'Invalid Twilio signature'], 403);
        }

        return $next($request);
    }
}
