<?php

// Simple test to check if InstallCommand can be instantiated

require_once 'vendor/autoload.php';

use Crud\Console\InstallCommand;
use Illuminate\Filesystem\Filesystem;

try {
    $command = new InstallCommand(new Filesystem());
    echo "✅ InstallCommand class loads successfully!\n";

    // Check if all required methods exist
    $requiredMethods = [
        'buildController',
        'buildRouter',
        'buildModel',
        'buildViews',
        'buildApiController',
        'buildApiResource',
        'buildFormRequest',
        'buildApiRoutes'
    ];

    foreach ($requiredMethods as $method) {
        if (method_exists($command, $method)) {
            echo "✅ Method {$method} exists\n";
        } else {
            echo "❌ Method {$method} missing\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
