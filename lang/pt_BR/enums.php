<?php

declare(strict_types=1);

// Rótulos de App\Enums\TimeEntryAggregationType::description() (usados nos relatórios em PDF e planilha).
// A biblioteca datomatic/laravel-enum-helper procura 'enums.<classe>.<valor>' como alternativa.
return [
    'App\Enums\TimeEntryAggregationType' => [
        'day' => 'Dia',
        'week' => 'Semana',
        'month' => 'Mês',
        'year' => 'Ano',
        'user' => 'Membro',
        'project' => 'Projeto',
        'task' => 'Tarefa',
        'client' => 'Cliente',
        'billable' => 'Faturável',
        'description' => 'Descrição',
        'tag' => 'Tag',
        'type' => 'Tipo',
    ],
];
