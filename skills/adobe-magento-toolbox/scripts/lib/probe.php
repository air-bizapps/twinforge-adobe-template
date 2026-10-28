<?php
/**
 * Processo filho do magento-verify: carrega uma classe e confere o parent::__construct.
 * Uso interno: MAGENTO_VERIFY_PROBE='{"root":...,"codeRoot":...,"class":...,"call":...}' php probe.php
 * (entrada por ambiente, não por argumento: o WAF da instância barra o arquivo que lê os argumentos e faz require)
 * Imprime uma linha JSON. Fatal de compilação não é capturável: o processo pai lê o stderr.
 */
declare(strict_types=1);

$input = (array)json_decode((string)getenv('MAGENTO_VERIFY_PROBE'), true);
$root = (string)($input['root'] ?? '');
$codeRoot = (string)($input['codeRoot'] ?? '');
$class = (string)($input['class'] ?? '');
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $c) use ($codeRoot): void {
    $f = $codeRoot . '/' . str_replace('\\', '/', $c) . '.php';
    if (is_file($f)) {
        require $f;
    }
}, true, true);

$result = ['ok' => true, 'parent' => []];
try {
    if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
        $result = ['ok' => false, 'error' => "o arquivo nao declara $class (namespace ou nome da classe diferente do caminho)"];
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]), "\n";
    exit(0);
}

$call = $input['call'] ?? null;
$parent = $result['ok'] && is_array($call) ? get_parent_class($class) : false;
if ($parent && !$call['named'] && !$call['unpack'] && method_exists($parent, '__construct')) {
    $ctor = new ReflectionMethod($parent, '__construct');
    $params = $ctor->getParameters();
    if (!$ctor->isVariadic() && $call['args'] > count($params)) {
        $result['parent'][] = "parent::__construct recebe {$call['args']} argumentos, mas $parent aceita no maximo "
            . count($params) . '.';
    }
    foreach ($call['vars'] as $pos => $childType) {
        if (!$childType || !isset($params[$pos])) {
            continue;
        }
        $type = $params[$pos]->getType();
        if (!$type instanceof ReflectionNamedType) {
            continue;
        }
        $expected = $type->getName();
        $ok = $type->isBuiltin()
            ? in_array($expected, ['mixed', 'object'], true)
            : (is_a($childType, $expected, true) || !(class_exists($childType) || interface_exists($childType)));
        if (!$ok) {
            $result['parent'][] = 'parent::__construct: o argumento ' . ($pos + 1) . " ($childType) cai no parametro \$"
                . $params[$pos]->getName() . " ($expected) do construtor de $parent.";
        }
    }
}
echo json_encode($result), "\n";
