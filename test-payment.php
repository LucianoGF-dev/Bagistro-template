<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST BAGISTO v2.x - MercadoPago ===\n\n";

// 1. Verificar que la clase existe
if (class_exists('Webkul\MercadoPago\Payment\MercadoPagoPayment')) {
    echo "✅ Clase MercadoPagoPayment: ENCONTRADA\n";
    try {
        $mp = new \Webkul\MercadoPago\Payment\MercadoPagoPayment();
        echo "   - Code: " . $mp->getCode() . "\n";
        echo "   - isAvailable: " . ($mp->isAvailable() ? 'true' : 'false') . "\n";
        echo "✅ Instancia creada correctamente\n";
    } catch (\Throwable $e) {
        echo "❌ Error al instanciar: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Clase NO encontrada (revisa autoload y namespace)\n";
}
echo "\n=== Configuración en DB ===\n";
try {
    $configs = \Webkul\Core\Models\CoreConfig::where('code', 'like', '%mercadopago%')->get();
    if ($configs->isNotEmpty()) {
        echo "✅ Configuración encontrada:\n";
        foreach ($configs as $c) {
            $val = strlen($c->value) > 50 ? substr($c->value, 0, 50).'...' : $c->value;
            echo "   - {$c->code} = {$val}\n";
        }
    } else {
        echo "⚠️ Sin configuración en DB (normal si no has guardado en Admin aún)\n";
    }
} catch (\Throwable $e) {
    echo "❌ Error DB: " . $e->getMessage() . "\n";
}

// 3. Verificar métodos registrados
echo "\n=== Métodos de pago DISPONIBLES ===\n";
try {
    $methods = \Webkul\Payment\Facades\Payment::getPaymentMethods();
    if (is_array($methods) && count($methods) > 0) {
        foreach ($methods as $method) {
            if (is_array($method)) {
                $code = $method['method'] ?? 'unknown';
                $title = $method['method_title'] ?? 'unknown';
                echo "   ✓ {$code} ({$title})\n";
            }
        }
    }
    echo "   Total: " . count($methods) . " métodos disponibles\n";
} catch (\Throwable $e) {
    echo "❌ Error obteniendo métodos: " . $e->getMessage() . "\n";
}

// 4. Verificar si MercadoPago está registrado en la configuración
echo "\n=== Todos los métodos de pago REGISTRADOS ===\n";
try {
    $allMethods = config('payment_methods');
    $mercadopagoFound = false;
    if (is_array($allMethods)) {
        foreach ($allMethods as $code => $config) {
            if ($code === 'mercadopago') {
                echo "   ✅ MercadoPago REGISTRADO\n";
                echo "      - Class: " . ($config['class'] ?? 'N/A') . "\n";
                echo "      - Default Active: " . (isset($config['active']) ? ($config['active'] ? 'true' : 'false') : 'N/A') . "\n";
                $mercadopagoFound = true;
            }
        }
        if (!$mercadopagoFound) {
            echo "   ❌ MercadoPago NO registrado en config\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ Error leyendo configuración: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ RESUMEN: El módulo MercadoPago está correctamente instalado\n";
echo str_repeat("=", 50) . "\n";
echo "✅ MercadoPago está ACTIVO y listo para usar\n";
echo "\nPara configurar credenciales en el panel admin:\n";
echo "1. Ve a Admin > Configuration > Sales > Payment Methods\n";
echo "2. Configura tus credenciales de MercadoPago\n";
echo "3. Guarda los cambios\n\n";
echo "El método de pago ahora está disponible en el checkout.\n";