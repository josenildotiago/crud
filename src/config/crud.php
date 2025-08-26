<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stubs Path
    |--------------------------------------------------------------------------
    |
    | O diretório do caminho de stubs para gerar o crud. Você pode configurar seu
    | caminhos de stubs aqui, permitindo que você personalize os próprios stubs do
    | modelo, Controller ou View. Ou você pode simplesmente ficar com os padrões do CrudGenerator!
    |
    | Example: 'stub_path' => resource_path('path/to/views/stubs/')
    | Default: "default"
    | Files:
    |       Controller.stub
    |       Model.stub
    |       views/
    |            create.stub
    |            edit.stub
    |            form.stub
    |            form-field.stub
    |            index.stub
    |            show.stub
    |            view-field.stub
    */
    'stub_path' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Application Layout
    |--------------------------------------------------------------------------
    |
    | Esse valor é o nome do layout do seu aplicativo. Este valor é usado ao criar
    | vistas para CRUD. O padrão será o "layouts.app".
    |
    */
    'layout' => 'layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Frontend Framework
    |--------------------------------------------------------------------------
    |
    | Define qual framework frontend será usado para gerar os templates.
    | Opções: 'blade', 'react', 'vue'
    |
    */
    'frontend' => 'react',

    /*
    |--------------------------------------------------------------------------
    | Inertia.js Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para usar com Inertia.js
    |
    */
    'inertia' => [
        'enabled' => true,
        'components_path' => 'js/pages',
        'layout_component' => 'Layouts/AuthenticatedLayout',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para geração de APIs RESTful
    |
    */
    'api' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api'],
        'generate_resources' => true,
        'generate_requests' => true,
    ],

    'model' => [
        'namespace' => 'App\Models',

        /*
         * Não faça essas colunas $fillable em Model ou views
         * Você pode também colocar aqui seu campo específico, ex: cpf, rg, email
         */
        'unwantedColumns' => [
            'id',
            'password',
            'email_verified_at',
            'remember_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ],

        /*
         * Configurações de relacionamentos
         */
        'relationships' => [
            'auto_detect' => true,
            'generate_pivot_models' => true,
            'include_polymorphic' => true,
        ],
    ],

    'controller' => [
        'namespace' => 'App\Http\Controllers',

        /*
         * Gerar métodos adicionais no controller
         */
        'additional_methods' => [
            'bulk_delete' => true,
            'export' => true,
            'import' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para geração de rotas RESTful
    |
    */
    'routes' => [
        'use_resource' => true,
        'prefix' => null,
        'middleware' => ['web', 'auth'],
        'name_prefix' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para geração automática de validações
    |
    */
    'validation' => [
        'generate_form_requests' => true,
        'auto_rules' => true,
        'include_custom_rules' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Integration
    |--------------------------------------------------------------------------
    |
    | Integração com o sistema de temas dinâmicos
    |
    */
    'themes' => [
        'enabled' => true,
        'default_theme' => 'default',
        'generate_theme_aware_components' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Support
    |--------------------------------------------------------------------------
    |
    | Configurações para suporte a múltiplos bancos de dados
    |
    */
    'database' => [
        'supported_drivers' => ['mysql', 'pgsql', 'sqlite', 'sqlsrv'],
        'auto_detect_driver' => true,
        'use_schema_cache' => true,
    ],
];