<?php
/**
 * magento-api: a assinatura real de uma classe, lida do vendor/ instalado (não da memória do modelo).
 *
 * Uso, na raiz do worktree Magento:
 *   php <skill>/scripts/magento-api.php 'Magento\Customer\Api\CustomerRepositoryInterface'
 *   php <skill>/scripts/magento-api.php --search "email template source"
 *
 * Mostra: arquivo, tipo, pai e interfaces, constantes, construtor e métodos públicos com tipos,
 * a preference configurada nos di.xml e até 3 usos no app/code. Nome que não existe: sugestões.
 */
declare(strict_types=1);

namespace MagentoToolbox;

require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/ClassIndex.php';

$args = array_slice($argv, 1);
$root = Env::findRoot(getcwd());
if (!$root || !$args) {
    fwrite(STDERR, "Uso (na raiz do Magento): magento-api.php <FQCN> | --search \"palavras\"\n");
    exit(2);
}
$env = new Env($root);
$env->boot();
$index = new ClassIndex($env);

if ($args[0] === '--search') {
    $words = preg_split('/\s+/', strtolower(trim(implode(' ', array_slice($args, 1))))) ?: [];
    $hits = [];
    foreach ($index->classes() as $c) {
        $lc = strtolower($c);
        $score = 0;
        foreach ($words as $w) {
            $score += str_contains($lc, $w) ? 1 : 0;
        }
        if ($score === count($words)) {
            $hits[$c] = strlen($c);
        }
    }
    asort($hits);
    echo $hits ? implode("\n", array_slice(array_keys($hits), 0, 15)) . "\n" : "Nada com todas as palavras: " . implode(' ', $words) . "\n";
    exit($hits ? 0 : 1);
}

$name = ltrim($args[0], '\\');
if (!$index->exists($name)) {
    if ($index->isVirtualType($name)) {
        echo "$name e um virtualType (declarado em " . $index->virtualTypes()[$name] . "): use so no di.xml, nunca no PHP.\n";
        exit(0);
    }
    $sug = $index->suggest($name);
    echo "$name NAO EXISTE neste projeto." . ($sug ? ' Voce quis dizer: ' . implode(' ou ', $sug) : '') . "\n";
    exit(1);
}
try {
    $r = new \ReflectionClass($name);
} catch (\Throwable $t) {
    echo "$name existe como classe gerada pelo Magento (Factory, Proxy, Interceptor ou Extension): "
        . "pede no construtor e o Magento gera.\n";
    exit(0);
}

$type = $r->isInterface() ? 'interface' : ($r->isTrait() ? 'trait' : ($r->isAbstract() ? 'classe abstrata' : 'classe'));
$file = (string)$r->getFileName();
$rootReal = (string)realpath($root);
echo "$type $name\n  arquivo: " . (str_starts_with($file, $rootReal) ? substr($file, strlen($rootReal) + 1) : $file) . "\n";
if ($p = $r->getParentClass()) {
    echo "  estende: {$p->getName()}\n";
}
if ($ifaces = $r->getInterfaceNames()) {
    echo '  implementa: ' . implode(', ', array_slice($ifaces, 0, 8)) . (count($ifaces) > 8 ? ', ...' : '') . "\n";
}

$sig = static function (\ReflectionMethod $m): string {
    $params = [];
    foreach ($m->getParameters() as $p) {
        $s = ($p->hasType() ? $p->getType() . ' ' : '') . ($p->isVariadic() ? '...' : '') . '$' . $p->getName();
        if ($p->isDefaultValueAvailable()) {
            $d = $p->getDefaultValue();
            $s .= ' = ' . (is_array($d) ? '[]' : var_export($d, true));
        }
        $params[] = $s;
    }
    return $m->getName() . '(' . implode(', ', $params) . ')' . ($m->hasReturnType() ? ': ' . $m->getReturnType() : '');
};

$consts = $r->getReflectionConstants();
if ($consts) {
    echo "  constantes:\n";
    foreach (array_slice($consts, 0, 30) as $c) {
        echo '    ' . $c->getName() . ' = ' . var_export($c->getValue(), true) . "\n";
    }
}
if (!$r->isInterface() && ($ctor = $r->getConstructor())) {
    echo '  construtor (declarado em ' . $ctor->getDeclaringClass()->getName() . "):\n    " . $sig($ctor) . "\n";
}
echo "  metodos publicos:\n";
foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
    if ($m->isConstructor() || str_starts_with($m->getName(), '__')) {
        continue;
    }
    $own = $m->getDeclaringClass()->getName() === $name ? '' : '  [de ' . $m->getDeclaringClass()->getShortName() . ']';
    echo '    ' . ($m->isStatic() ? 'static ' : '') . $sig($m) . $own . "\n";
}

// preference nos di.xml (app/etc, app/code, vendor)
$quoted = preg_quote($name, '/');
$out = (string)shell_exec('grep -rlnF --include=di.xml ' . escapeshellarg('for="' . $name . '"') . ' '
    . escapeshellarg($root . '/app') . ' ' . escapeshellarg($root . '/vendor/magento') . ' 2>/dev/null | head -5');
foreach (array_filter(explode("\n", $out)) as $f) {
    if (preg_match('/<preference\s+for="\\\\?' . $quoted . '"\s+type="([^"]+)"/', (string)file_get_contents($f), $m)) {
        echo '  preference: ' . $m[1] . '  (' . substr($f, strlen($root) + 1) . ")\n";
    }
}
$uses = (string)shell_exec('grep -rn --include=*.php -F ' . escapeshellarg($name) . ' ' . escapeshellarg($env->codeRoot)
    . ' 2>/dev/null | grep -v "/Test/" 2>/dev/null | head -3');
if (trim($uses) !== '') {
    echo "  usos no projeto:\n";
    foreach (array_filter(explode("\n", $uses)) as $u) {
        echo '    app/code/' . substr(trim(preg_replace('/^(.+?:\d+):.*$/', '$1', $u)), strlen($env->codeRoot) + 1) . "\n";
    }
}
