<?php

declare(strict_types=1);

return [
    'navigation' => 'Exécutions Durable',
    'backend' => [
        'checked' => ':name · vérifié à :time',
    ],
    'outcomes_heading' => 'Issues des :count exécutions de cette page',
    'kpi' => [
        'waiting_for_worker' => 'En attente d’un worker',
    ],
    'status' => [
        'running' => 'En cours',
        'completed' => 'Terminée',
        'failed' => 'En échec',
        'cancelled' => 'Annulée',
        'continued_as_new' => 'Poursuivie à neuf',
    ],
    'filter' => [
        'workflow_name' => 'Nom du workflow',
        'execution_id_prefix' => 'L’identifiant d’exécution commence par',
        'submit' => 'Filtrer',
    ],
    'runs' => [
        'title' => 'Exécutions de workflow',
        'empty' => 'Aucune exécution ne correspond à ce filtre.',
        'execution' => 'Exécution',
        'workflow' => 'Workflow',
        'status' => 'Issue',
        'started_at' => 'Démarrée',
        'notes' => 'Notes',
    ],
    'pagination' => [
        'first' => 'Première page',
        'next' => 'Page suivante',
    ],
    'run' => [
        'title' => 'Exécution de workflow',
        'back' => 'Retour aux exécutions',
        'not_found' => 'Aucune exécution de cet identifiant sur ce backend.',
        'workflow' => 'Workflow',
        'execution' => 'Exécution',
        'backend_run' => 'exécution :id côté serveur',
        'outcome' => 'Issue',
        'history' => 'Historique',
        'no_history' => 'Aucun historique enregistré pour cette exécution.',
    ],
    'phase' => [
        'requested' => 'demandé',
        'started' => 'pris en charge',
        'failed' => 'en échec',
        'settled' => 'réglé',
    ],
    'nexus' => [
        'title' => 'Opérations Nexus',
        'endpoint' => 'Endpoint',
        'service' => 'Service',
        'operation' => 'Opération',
        'state' => 'État',
        'states' => [
            'in_flight' => 'en cours',
            'completed' => 'terminée',
            'failed' => 'en échec',
            'timed_out' => 'expirée',
            'cancelled' => 'annulée',
        ],
    ],
];
