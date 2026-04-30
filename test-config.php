<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST: Config de Payment Methods ===\n";
$config = config('payment_methods');
if (is_array($config)) {
    echo "✅ Config 'payment_methods' encontrada\n";
    echo "   Total métodos: " . count($config) . "\n";
    foreach ($config as $key => $method) {
        echo "   - {$key}: " . ($method['class'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ Config 'payment_methods' NO encontrada o no es un array\n";
    echo "   Type: " . gettype($config) . "\n";
}

echo "\n=== TEST: Métodos registrados en Payment Facade ===\n";
try {
    $payment = \Webkul\Payment\Facades\Payment::getPaymentMethods();
    echo "✅ Métodos obtenidos: " . count($payment) . "\n";
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
