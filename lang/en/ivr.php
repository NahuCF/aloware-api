<?php

return [
    'welcome' => 'Welcome to :app_name',
    'press_for' => 'Press :digit for :label',
    'connecting_to_agent' => 'Connecting you to an agent.',
    'transferring_call' => 'Transferring your call.',
    'please_try_again' => 'Please try again later.',
    'no_destination' => 'No destination number provided.',
    'agent_no_answer' => 'The agent could not answer your call.',

    'error' => [
        'invalid_number' => 'The number you called is not valid.',
        'no_ivr' => 'There is no menu configured for this line.',
        'invalid_action' => 'Invalid menu option.',
        'agent_not_found' => 'The requested agent does not exist.',
        'transfer_failed' => 'The transfer could not be completed.',
        'destination_no_ivr' => 'The destination line has no menu configured.',
    ],

    'agent_status' => [
        'on_call' => 'The requested agent is currently on another call.',
        'away' => 'The requested agent is currently away.',
        'offline' => 'The requested agent is currently offline.',
        'unavailable' => 'The requested agent is not available.',
    ],

    'queue' => [
        'no_agents' => 'All of our agents are currently busy.',
        'error' => 'We are experiencing technical difficulties.',
    ],
];
