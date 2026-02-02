<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\CallSessionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Language;
use App\Models\Line;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class TwilioController extends Controller
{
    private const DEFAULT_LANGUAGE = 'en';

    private const TWILIO_VOICES = [
        'en' => 'Polly.Joanna',
        'es' => 'Polly.Lupe',
    ];

    public function incoming(Request $request): \Illuminate\Http\Response
    {
        $input = $request->all();
        $callSid = data_get($input, 'CallSid');
        $from = data_get($input, 'From');
        $to = data_get($input, 'To');

        $line = Line::where('phone_number', $to)->first();

        if (! $line) {
            return $this->errorResponse(__('ivr.error.invalid_number'));
        }

        if (empty($line->ivr_steps)) {
            return $this->errorResponse(__('ivr.error.no_ivr'));
        }

        $session = CallSession::create([
            'call_sid' => $callSid,
            'line_id' => $line->id,
            'from_number' => $from,
            'path' => [],
            'context' => [],
        ]);

        return $this->playMenu($session, $line->ivr_steps);
    }

    public function handleInput(Request $request): \Illuminate\Http\Response
    {
        $input = $request->all();
        $sessionId = data_get($input, 'session_id');
        $digit = data_get($input, 'Digits');

        $session = CallSession::find($sessionId);

        if (! $session) {
            return $this->errorResponse(__('ivr.error.session_not_found'));
        }

        $line = $session->line;

        if (! $line) {
            return $this->errorResponse(__('ivr.error.line_not_found'));
        }

        $currentSteps = $this->getStepsAtPath($line->ivr_steps, $session->path);

        $step = collect($currentSteps)->firstWhere('digit', $digit);

        if (! $step) {
            return $this->playMenu($session, $currentSteps);
        }

        $newContext = $this->mergeContext($session->context, $step['context'] ?? []);
        $newPath = array_merge($session->path, [$digit]);

        $session->update([
            'path' => $newPath,
            'context' => $newContext,
        ]);

        $session->refresh();

        return match ($step['action_type']) {
            'menu' => $this->playMenu($session, $step['sub_steps'] ?? []),
            'route_to_skill' => $this->routeToSkill($session),
            'route_to_user' => $this->routeToUser($session, $step['target_id']),
            'route_to_line' => $this->routeToLine($session, $step['target_id']),
            default => $this->errorResponse($this->trans($session, 'ivr.error.invalid_action')),
        };
    }

    private function playMenu(CallSession $session, array $steps): \Illuminate\Http\Response
    {
        $response = new VoiceResponse;
        $voice = $this->getVoice($session);
        $lang = $this->getLanguageCode($session);

        if (empty($session->path)) {
            $response->say(
                $this->trans($session, 'ivr.welcome', ['app_name' => config('app.name')]),
                ['voice' => $voice]
            );
        }

        $handleInputUrl = '/webhook/twilio/voice/handle-input?session_id='.$session->id;

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => $handleInputUrl,
            'method' => 'POST',
            'timeout' => config('services.twilio.ivr.gather_timeout'),
        ]);

        foreach ($steps as $step) {
            $this->playStepPrompt($gather, $step, $lang, $voice);
        }

        $response->redirect($handleInputUrl, ['method' => 'POST']);

        return $this->twimlResponse($response);
    }

    private function getStepsAtPath(array $steps, array $path): array
    {
        $current = $steps;

        foreach ($path as $digit) {
            $step = collect($current)->firstWhere('digit', $digit);
            if (! $step || empty($step['sub_steps'])) {
                return [];
            }
            $current = $step['sub_steps'];
        }

        return $current;
    }

    private function mergeContext(array $existing, array $new): array
    {
        $merged = $existing;

        if (! empty($new['language_id'])) {
            $merged['language_id'] = $new['language_id'];
        }

        $existingSkills = $existing['skill_ids'] ?? [];
        $newSkills = $new['skill_ids'] ?? [];
        $merged['skill_ids'] = array_unique(array_merge($existingSkills, $newSkills));

        return $merged;
    }

    private function routeToSkill(CallSession $session): \Illuminate\Http\Response
    {
        $context = $session->context;
        $skillIds = $context['skill_ids'] ?? [];
        $skillNames = Skill::whereIn('id', $skillIds)->pluck('name')->toArray();

        $response = new VoiceResponse;

        $enqueue = $response->enqueue(null, [
            'workflowSid' => config('services.twilio.workflow_sid'),
            'waitUrl' => config('services.twilio.ivr.hold_music_url'),
        ]);

        $enqueue->task(json_encode([
            'session_id' => $session->id,
            'call_sid' => $session->call_sid,
            'from' => $session->from_number,
            'language' => $context['language_id'] ?? null,
            'skills' => $skillNames,
        ]));

        return $this->twimlResponse($response);
    }

    private function routeToUser(CallSession $session, int $userId): \Illuminate\Http\Response
    {
        $user = User::find($userId);

        if (! $user) {
            return $this->errorResponse($this->trans($session, 'ivr.error.agent_not_found'));
        }

        if ($user->status !== UserStatus::Available) {
            return $this->agentUnavailableResponse($session, $user);
        }

        $user->update(['status' => UserStatus::OnCall]);

        $session->update(['status' => CallSessionStatus::Connected]);

        $response = new VoiceResponse;
        $response->say($this->trans($session, 'ivr.connecting_to_agent'), ['voice' => $this->getVoice($session)]);

        $dial = $response->dial(null, [
            'callerId' => $session->line->phone_number,
            'timeout' => config('services.twilio.ivr.dial_timeout'),
            'action' => '/webhook/twilio/voice/dial-complete?session_id='.$session->id.'&user_id='.$user->id,
        ]);

        $dial->client('agent_'.$user->id);

        return $this->twimlResponse($response);
    }

    private function agentUnavailableResponse(CallSession $session, User $user): \Illuminate\Http\Response
    {
        $response = new VoiceResponse;
        $voice = $this->getVoice($session);

        $messageKey = match ($user->status) {
            UserStatus::OnCall => 'ivr.agent_status.on_call',
            UserStatus::Away => 'ivr.agent_status.away',
            UserStatus::Offline => 'ivr.agent_status.offline',
            default => 'ivr.agent_status.unavailable',
        };

        $response->say($this->trans($session, $messageKey), ['voice' => $voice]);
        $response->say($this->trans($session, 'ivr.please_try_again'), ['voice' => $voice]);
        $response->hangup();

        return $this->twimlResponse($response);
    }

    private function routeToLine(CallSession $session, int $lineId): \Illuminate\Http\Response
    {
        $line = Line::find($lineId);

        if (! $line) {
            return $this->errorResponse($this->trans($session, 'ivr.error.transfer_failed'));
        }

        if (empty($line->ivr_steps)) {
            return $this->errorResponse($this->trans($session, 'ivr.error.destination_no_ivr'));
        }

        $session->update(['status' => CallSessionStatus::Transferred]);

        $newSession = CallSession::create([
            'call_sid' => $session->call_sid,
            'line_id' => $line->id,
            'from_number' => $session->from_number,
            'path' => [],
            'context' => $session->context,
        ]);

        $response = new VoiceResponse;
        $response->say($this->trans($session, 'ivr.transferring_call'), ['voice' => $this->getVoice($session)]);

        return $this->playMenu($newSession, $line->ivr_steps);
    }

    public function outbound(Request $request): \Illuminate\Http\Response
    {
        $input = $request->all();
        $to = data_get($input, 'To');

        $response = new VoiceResponse;

        if (! $to) {
            $response->say(__('ivr.no_destination'));
            $response->hangup();

            return $this->twimlResponse($response);
        }

        $dial = $response->dial(null, [
            'callerId' => config('services.twilio.phone_number'),
            'timeout' => config('services.twilio.ivr.dial_timeout'),
        ]);

        $dial->number($to);

        return $this->twimlResponse($response);
    }

    public function dialComplete(Request $request): \Illuminate\Http\Response
    {
        $input = $request->all();
        $sessionId = data_get($input, 'session_id');
        $status = data_get($input, 'DialCallStatus');
        $userId = data_get($input, 'user_id');

        if ($userId) {
            User::where('id', $userId)->update([
                'status' => UserStatus::Available,
                'last_activity_at' => now(),
            ]);
        }

        $session = CallSession::find($sessionId);

        CallSession::where('id', $sessionId)->update(['status' => CallSessionStatus::Completed]);

        $response = new VoiceResponse;

        if ($status !== 'completed') {
            $voice = $session ? $this->getVoice($session) : self::TWILIO_VOICES[self::DEFAULT_LANGUAGE];
            $message = $session
                ? $this->trans($session, 'ivr.agent_no_answer')
                : __('ivr.agent_no_answer');
            $response->say($message, ['voice' => $voice]);
        }

        $response->hangup();

        return $this->twimlResponse($response);
    }

    private function playStepPrompt($gather, array $step, string $lang, string $voice): void
    {
        $message = __('ivr.press_for', ['digit' => $step['digit'], 'label' => $step['label']], $lang);
        $gather->say($message, ['voice' => $voice]);
    }

    private function getLanguageCode(CallSession $session): string
    {
        $languageId = data_get($session->context, 'language_id');

        if (! $languageId) {
            return self::DEFAULT_LANGUAGE;
        }

        $language = Language::find($languageId);

        return $language?->code ?? self::DEFAULT_LANGUAGE;
    }

    private function getVoice(CallSession $session): string
    {
        $lang = $this->getLanguageCode($session);

        return self::TWILIO_VOICES[$lang] ?? self::TWILIO_VOICES[self::DEFAULT_LANGUAGE];
    }

    private function trans(CallSession $session, string $key, array $replace = []): string
    {
        $lang = $this->getLanguageCode($session);

        return __($key, $replace, $lang);
    }

    private function twimlResponse(VoiceResponse $response): \Illuminate\Http\Response
    {
        return response($response->__toString(), 200)
            ->header('Content-Type', 'text/xml');
    }

    private function errorResponse(string $message, ?string $voice = null): \Illuminate\Http\Response
    {
        $response = new VoiceResponse;
        $response->say($message, ['voice' => $voice ?? self::TWILIO_VOICES[self::DEFAULT_LANGUAGE]]);
        $response->hangup();

        return $this->twimlResponse($response);
    }
}
