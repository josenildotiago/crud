<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | O tema padrão que será aplicado quando nenhum tema específico for
    | selecionado pelo usuário.
    |
    */
    'default_theme' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Default Mode
    |--------------------------------------------------------------------------
    |
    | O modo padrão de aparência (light, dark, system).
    |
    */
    'default_mode' => 'system',

    /*
    |--------------------------------------------------------------------------
    | Persistence Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para persistência das preferências do usuário.
    |
    */
    'persistence' => [
        'cookie_name' => 'app_theme',
        'cookie_days' => 365,
        'localStorage_key' => 'themeId',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSS Variables
    |--------------------------------------------------------------------------
    |
    | Definição das variáveis CSS obrigatórias e opcionais para cada tema.
    |
    */
    'css_variables' => [
        'required' => [
            'background',
            'foreground',
            'card',
            'card-foreground',
            'popover',
            'popover-foreground',
            'primary',
            'primary-foreground',
            'secondary',
            'secondary-foreground',
            'muted',
            'muted-foreground',
            'accent',
            'accent-foreground',
            'destructive',
            'destructive-foreground',
            'border',
            'input',
            'ring',
        ],
        'optional' => [
            'chart-1',
            'chart-2',
            'chart-3',
            'chart-4',
            'chart-5',
            'sidebar',
            'sidebar-foreground',
            'sidebar-primary',
            'sidebar-primary-foreground',
            'sidebar-accent',
            'sidebar-accent-foreground',
            'sidebar-border',
            'sidebar-ring',
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Assets
    |--------------------------------------------------------------------------
    |
    | Caminhos para os arquivos de temas e componentes React.
    |
    */
    'assets' => [
        'themes_file' => 'js/lib/themes.ts',
        'hook_file' => 'js/hooks/use-appearance.tsx',
        'components_path' => 'js/components',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Themes
    |--------------------------------------------------------------------------
    |
    | Lista dos temas disponíveis que podem ser instalados.
    |
    */
    'available_themes' => [
        'default' => 'Padrão (Preto/Branco)',
        'blue' => 'Azul',
        'green' => 'Verde',
        'purple' => 'Roxo',
        'orange' => 'Laranja',
        'red' => 'Vermelho',
        'yellow' => 'Amarelo',
        'pink' => 'Rosa',
        'gray' => 'Cinza',
    ],
];
