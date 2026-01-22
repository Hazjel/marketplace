<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Force load env if not loaded by bootstrap (though it should be)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking MongoDB Connection...\n";

try {
    // Check MySQL
    echo "\nChecking MySQL...\n";
    $product = \App\Models\Product::latest()->first();
    if ($product) {
        echo "Latest Product: " . $product->name . "\n";
        echo "ID: " . $product->id . "\n";
        echo "Has Variants: " . ($product->has_variants ? 'YES' : 'NO') . "\n";
        
        // Check relation
        echo "Counting Variants via Eloquent: " . $product->variants()->count() . "\n";
    } else {
        echo "No products found in MySQL.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
