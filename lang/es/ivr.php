<?php

return [
    'welcome' => 'Bienvenido a :app_name',
    'press_for' => 'Presione :digit para :label',
    'connecting_to_agent' => 'Conectando con un agente.',
    'transferring_call' => 'Transfiriendo su llamada.',
    'please_try_again' => 'Por favor intente mas tarde.',
    'no_destination' => 'No se proporciono un numero de destino.',
    'agent_no_answer' => 'El agente no pudo responder su llamada.',

    'error' => [
        'invalid_number' => 'El numero al que llamo no es valido.',
        'no_ivr' => 'No hay un menu configurado para esta linea.',
        'session_not_found' => 'No pudimos encontrar su sesion de llamada.',
        'line_not_found' => 'No se pudo encontrar la linea.',
        'invalid_action' => 'Opcion de menu invalida.',
        'agent_not_found' => 'El agente solicitado no existe.',
        'transfer_failed' => 'No se pudo completar la transferencia.',
        'destination_no_ivr' => 'La linea de destino no tiene un menu configurado.',
    ],

    'agent_status' => [
        'on_call' => 'El agente solicitado esta en otra llamada.',
        'away' => 'El agente solicitado no esta disponible.',
        'offline' => 'El agente solicitado esta desconectado.',
        'unavailable' => 'El agente solicitado no esta disponible.',
    ],
];
