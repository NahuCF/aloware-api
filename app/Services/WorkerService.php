<?php

namespace App\Services;

use App\Models\User;
use Twilio\Rest\Client;

class WorkerService
{
    private Client $client;

    private string $workspaceSid;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        $this->workspaceSid = config('services.twilio.workspace_sid');
    }

    public function createWorker(User $user): ?string
    {
        if (empty($this->workspaceSid)) {
            return null;
        }

        $worker = $this->client->taskrouter->v1
            ->workspaces($this->workspaceSid)
            ->workers
            ->create($user->name, [
                'attributes' => $this->buildAttributes($user),
                'activitySid' => config('services.twilio.activity_available_sid'),
            ]);

        $user->update(['worker_sid' => $worker->sid]);

        return $worker->sid;
    }

    public function updateWorker(User $user): void
    {
        if (empty($this->workspaceSid) || empty($user->worker_sid)) {
            return;
        }

        $this->client->taskrouter->v1
            ->workspaces($this->workspaceSid)
            ->workers($user->worker_sid)
            ->update([
                'friendlyName' => $user->name,
                'attributes' => $this->buildAttributes($user),
            ]);
    }

    public function deleteWorker(User $user): void
    {
        if (empty($this->workspaceSid) || empty($user->worker_sid)) {
            return;
        }

        $this->client->taskrouter->v1
            ->workspaces($this->workspaceSid)
            ->workers($user->worker_sid)
            ->delete();
    }

    private function buildAttributes(User $user): string
    {
        $user->load(['skills', 'languages']);

        return json_encode([
            'user_id' => $user->id,
            'skills' => $user->skills->pluck('name')->toArray(),
            'languages' => $user->languages->pluck('id')->toArray(),
        ]);
    }
}
