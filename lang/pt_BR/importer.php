<?php

declare(strict_types=1);

return [
    'clockify_time_entries' => [
        'name' => 'Apontamentos do Clockify',
        'description' => '<strong>Importante:</strong> se você também quer importar seus projetos, use o importador "Projetos do Clockify" antes deste, pois aquela exportação traz mais detalhes, como status de faturável, valores por hora e tempo estimado.<br><br>'.
            '1. Primeiro, nas configurações do usuário, defina o formato de data como "MM/DD/YYYY" e o formato de hora como "12-hour".<br>'.
            '2. Na mesma página de preferências, mude o idioma do Clockify para inglês.<br>'.
            '3. No menu à esquerda, vá em REPORTS -> TIME -> Detailed. <br>'.
            '4. Agora selecione o período que você quer exportar, no canto superior direito. '.
            'No plano gratuito do Clockify, atualmente não é possível selecionar mais de um ano. '.
            'Você pode exportar cada ano separadamente e importá-los um após o outro.'.
            '<br> 4. Agora clique em Export -> Save as CSV. O menu Export fica no cabeçalho da tabela de exportação, à esquerda do ícone da impressora. '.
            '<br><br>Antes de importar, confira se o fuso horário (Timezone) no Clockify é o mesmo do solidtime.',
    ],
    'generic_projects' => [
        'name' => 'Projetos (genérico)',
        'description' => 'Se você quer importar muitos projetos por conta própria, este importador é a escolha certa. Veja na nossa documentação <a href="https://docs.solidtime.io/user-guide/import">mais informações sobre a estrutura do CSV</a>',
    ],
    'generic_time_entries' => [
        'name' => 'Apontamentos (genérico)',
        'description' => 'Se você quer importar muitos apontamentos por conta própria, este importador é a escolha certa. Veja na nossa documentação <a href="https://docs.solidtime.io/user-guide/import">mais informações sobre a estrutura do CSV</a>',
    ],
    'clockify_projects' => [
        'name' => 'Projetos do Clockify',
        'description' => '1. Defina o idioma do Clockify como inglês em "Preferences -> General".<br>'.
            '2. No menu à esquerda, vá em PROJECTS.<br> '.
            '3. Clique nos três pontinhos à direita do projeto que você quer exportar e selecione Export.<br> '.
            '4. Agora clique em Export -> Save as CSV. O menu Export fica no cabeçalho da tabela de exportação, no canto superior direito.',
    ],
    'toggl_data_importer' => [
        'name' => 'Importador de dados do Toggl',
        'description' => '1. Vá em Admin -> Settings -> Data export. <br>'.
            '2. Em "Data Export", selecione todos os itens para exportar e clique em "Export to email". <br> '.
            '3. Você vai receber um e-mail com um link de download. Baixe o ZIP e envie-o aqui. '.
            '<br><br>O "Data Export" exporta tudo, exceto os apontamentos. '.
            'Se você também quer importar apontamentos, use depois o importador "Apontamentos do Toggl".',
    ],
    'toggl_time_entries' => [
        'name' => 'Apontamentos do Toggl',
        'description' => '<strong>Importante:</strong> se você quer importar uma organização do Toggl, use o "Importador de dados do Toggl" antes deste importador, pois aquela exportação traz mais detalhes. '.
            '<br><br>1. Vá em Admin -> Settings -> Data export. <br>2. Em "Time entries", selecione o ano que você quer exportar e clique em "Export time entries". <br><br>Você pode exportar todos os anos, um após o outro, e importá-los um após o outro. '.
            ' <br>Antes de importar, confira se o fuso horário (Timezone) no Toggl é o mesmo do solidtime.',
    ],
    'solidtime_importer' => [
        'name' => 'Solidtime',
        'description' => '1. Escolha a organização que você quer exportar na lista no canto superior esquerdo<br>2. Clique em "Exportar" no menu à esquerda, em "Administração" (você precisa ser Administrador ou Proprietário da organização para ver essa opção)<br>3. Clique em "Exportar". <br>4. Salve o arquivo e envie-o aqui.',
    ],
    'harvest_clients' => [
        'name' => 'Clientes do Harvest',
        'description' => '1. Vá em "Manage" (menu superior)<br>2. Clique em "Clients"'.
            '<br>3. Clique em "Import/Export" e, na lista, em "Export clients to CSV" '.
            '<br>',
    ],
    'harvest_projects' => [
        'name' => 'Projetos do Harvest',
        'description' => '1. Vá em "Projects" (menu superior)<br>2. Clique no botão "Export"'.
            '<br>3. Selecione quais projetos você quer exportar e escolha o formato CSV '.
            '<br><br>Antes de importar, confira se o fuso horário (Timezone) no Harvest é o mesmo do solidtime.',
    ],
    'harvest_time_entries' => [
        'name' => 'Apontamentos do Harvest',
        'description' => '<strong>Importante:</strong> se você também quer importar seus projetos, use o importador "Projetos do Harvest" antes deste, pois aquela exportação traz mais detalhes, como status de faturável e tempo estimado.<br><br>'.
            '1. Vá em Settings (canto superior direito)<br>2. Clique em "Import/Export" no menu à esquerda'.
            '<br>3. Agora clique em "Export all time" '.
            '<br><br>Antes de importar, confira se o fuso horário (Timezone) no Harvest é o mesmo do solidtime.',
    ],
];
