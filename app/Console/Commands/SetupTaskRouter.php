<?php

namespace App\Console\Commands;

use App\Models\Skill;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class SetupTaskRouter extends Command
{
    protected $signature = 'taskrouter:setup';
    protected $description = 'Create TaskRouter queues and workflow';

    private Client $client;
    private string $workspaceSid;

    public function handle(): int
    {
        $this->workspaceSid = config('services.twilio.workspace_sid');

        if (empty($this->workspaceSid)) {
            return Command::FAILURE;
        }

        $this->client = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );

        $skills = Skill::all();

        if ($skills->isEmpty()) {
            return Command::FAILURE;
        }

        $this->createQueuesForSkills();
        $defaultQueueSid = $this->createDefaultQueue();
        $this->createWorkflow($defaultQueueSid);

        return Command::SUCCESS;
    }

    private function createQueuesForSkills(): void
    {
        $skills = Skill::all();

        foreach ($skills as $skill) {
            if ($skill->queue_sid) {
                continue;
            }

            $queue = $this->client->taskrouter
                ->workspaces($this->workspaceSid)
                ->taskQueues
                ->create($skill->name, [
                    'targetWorkers' => "skills HAS '{$skill->name}'",
                ]);

            $skill->update(['queue_sid' => $queue->sid]);
        }
    }

    private function createDefaultQueue(): string
    {
        $existingQueues = $this->client->taskrouter
            ->workspaces($this->workspaceSid)
            ->taskQueues
            ->read(['friendlyName' => 'Default'], 1);

        if (!empty($existingQueues)) {
            return $existingQueues[0]->sid;
        }

        $queue = $this->client->taskrouter
            ->workspaces($this->workspaceSid)
            ->taskQueues
            ->create('Default', [
                'targetWorkers' => '1 == 1',
            ]);

        return $queue->sid;
    }

    private function createWorkflow(string $defaultQueueSid): string
    {
        $skills = Skill::whereNotNull('queue_sid')->get();

        $filters = $skills->map(fn($skill) => [
            'filter_friendly_name' => $skill->name,
            'expression' => "'{$skill->name}' IN task.skills",
            'targets' => [
                [
                    'queue' => $skill->queue_sid,
                    'expression' => 'task.language IN worker.languages OR task.language == null',
                    'timeout' => 60,
                ],
                [
                    'queue' => $skill->queue_sid,
                    'timeout' => 30,
                ],
                [
                    'queue' => $defaultQueueSid,
                ],
            ],
        ])->toArray();

        $configuration = [
            'task_routing' => [
                'filters' => $filters,
                'default_filter' => [
                    'queue' => $defaultQueueSid,
                ],
            ],
        ];

        $workflow = $this->client->taskrouter
            ->workspaces($this->workspaceSid)
            ->workflows
            ->create('Main Routing', json_encode($configuration), [
                'assignmentCallbackUrl' => config('app.url') . '/webhook/twilio/taskrouter/assignment',
                'taskReservationTimeout' => 120,
            ]);

        return $workflow->sid;
    }
}
