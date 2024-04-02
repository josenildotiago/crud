<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stubs Path
    |--------------------------------------------------------------------------
    |
    | O diretório do caminho de stubs para gerar o crud. Você pode configurar seu
    | caminhos de stubs aqui, permitindo que você personalize os próprios stubs do
    | modelo, Controoler ou View. Ou você pode simplesmente ficar com os padrões do CrudGenerator!
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

    'model' => [
        'namespace' => 'App\Models',

        /*
         * Não faça essas colunas $fillable em Model ou views
         * Você pode també colocar aqui seu campo específico, ex: cpf, rg, email
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
    ],

    'controller' => [
        'namespace' => 'App\Http\Controllers',
    ],

];
