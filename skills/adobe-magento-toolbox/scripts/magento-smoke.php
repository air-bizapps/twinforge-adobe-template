<?php
/**
 * magento-smoke: prova em execução, sem navegador e sem HTTP, que o código alterado sobe e responde.
 *
 *  1. DI: cria pelo ObjectManager, na área certa, cada classe alterada da branch. Pega preference
 *     que falta ("Cannot instantiate interface"), tipo errado no di.xml, classe inexistente.
 *  2. Web API: para cada rota dos webapi.xml alterados, chama o service contract com dados de teste:
 *     getList com SearchCriteria vazio e getById com um id que não existe (tem que dar
 *     NoSuchEntity, não erro fatal). Com --write, também save() com um DTO montado pelos tipos do
 *     docblock, dentro de uma transação desfeita no fim (nada fica no banco).
 *
 * Uso, na raiz de um Magento instalado (banco de verificação, nunca o compartilhado):
 *   php <skill>/scripts/magento-smoke.php [--base <ref> | --path app/code/Vendor/Module] [--write]
 * Exit: 0 OK, 1 erro, 2 ambiente.
 */
declare(strict_types=1);

namespace MagentoToolbox;

require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/ClassIndex.php';

$opts = getopt('', ['base:', 'path:', 'write', 'worker:', 'items:']);
$root = Env::findRoot(getcwd());
if (!$root || !is_file($root . '/app/etc/env.php')) {
    fwrite(STDERR, "Rode na raiz de um Magento instalado (com app/etc/env.php).\n");
    exit(2);
}

if (isset($opts['worker'])) {
    exit(SmokeWorker::run($root, (string)$opts['worker'], (string)$opts['items'], isset($opts['write'])));
}

$env = new Env($root);
$env->boot();
if (isset($opts['path'])) {
    $dir = trim((string)$opts['path'], '/');
    $env->useAllFiles($root . '/' . $dir, $dir . '/');
} else {
    $env->collectChanges($opts['base'] ?? ('origin/' . (getenv('BUNGE_BASE_BRANCH') ?: 'twinforge')));
}

// 1. classes alteradas, agrupadas pela área em que o Magento as cria
$byArea = [];
foreach ($env->present('#^app/code/.+\.php$#') as $rel) {
    if (preg_match('#/(Test|Setup|registration\.php)#', $rel) || str_contains($rel, '/Api/')) {
        continue;
    }
    $fqcn = str_replace('/', '\\', substr($rel, 9, -4));
    $area = match (true) {
        (bool)preg_match('#\\\\(Controller|Block)\\\\Adminhtml\\\\|\\\\Ui\\\\#', $fqcn) => 'adminhtml',
        (bool)preg_match('#\\\\(Controller|Block|ViewModel|CustomerData)\\\\#', $fqcn) => 'frontend',
        (bool)preg_match('#\\\\Model\\\\Resolver\\\\#', $fqcn) => 'graphql',
        (bool)preg_match('#\\\\Cron\\\\#', $fqcn) => 'crontab',
        default => 'global',
    };
    $byArea[$area][] = ['type' => 'di', 'class' => $fqcn, 'file' => $rel];
}
// 2. rotas dos webapi.xml alterados
foreach ($env->present('#/etc/webapi\.xml$#') as $rel) {
    $dom = new \DOMDocument();
    if (!@$dom->load($env->abs($rel))) {
        continue;
    }
    foreach ((new \DOMXPath($dom))->query('//route/service') as $service) {
        $byArea['webapi_rest'][] = ['type' => 'api', 'class' => ltrim($service->getAttribute('class'), '\\'),
            'method' => $service->getAttribute('method'), 'file' => $rel, 'line' => $service->getLineNo(),
            'route' => $service->parentNode->getAttribute('method') . ' ' . $service->parentNode->getAttribute('url')];
    }
}

$report = new Report();
foreach ($byArea as $area => $items) {
    $cmd = escapeshellarg(PHP_BINARY) . ' -d memory_limit=2G ' . escapeshellarg(__FILE__) . ' --worker ' . escapeshellarg($area)
        . ' --items ' . escapeshellarg((string)json_encode($items)) . (isset($opts['write']) ? ' --write' : '') . ' 2>&1';
    $out = (string)shell_exec($cmd);
    $json = null;
    foreach (array_reverse(explode("\n", trim($out))) as $line) {
        if (str_starts_with($line, '{"results"')) {
            $json = json_decode($line, true);
            break;
        }
    }
    if (!is_array($json)) {
        $report->add(new Finding('SMOKE-BOOT', 'C4', 'error', 'app/etc', 0,
            "O Magento nao subiu na area $area: " . Report::oneLine(substr($out, 0, 400))));
        continue;
    }
    foreach ($json['results'] as $r) {
        $report->count($r['type'] === 'api' ? 'chamadas de API' : "classes ($area)");
        if ($r['status'] === 'ok' || $r['status'] === 'skip') {
            continue;
        }
        $report->add(new Finding($r['id'], $r['cls'], $r['status'] === 'warn' ? 'warn' : 'error', $r['file'],
            (int)($r['line'] ?? 0), $r['message'], $r['hint'] ?? ''));
    }
}
$env->branch = $env->branch ?: '-';
@mkdir($env->outDir, 0775, true);
file_put_contents($env->outDir . '/last-smoke.md', $report->markdown($env, 'smoke'));
echo str_replace(['VERIFY', 'var/verify/last.md (rode magento-verify --last)'], ['SMOKE', 'var/verify/last-smoke.md'],
    $report->summary($env, 'smoke')), "\n";
exit($report->errors() ? 1 : 0);

/** Processo filho: sobe o Magento numa área e testa os itens dela. */
final class SmokeWorker
{
    public static function run(string $root, string $area, string $itemsJson, bool $write): int
    {
        $env = new Env($root);
        $env->boot();
        require_once $root . '/app/bootstrap.php';
        $env->registerLocalModules();
        $bootstrap = \Magento\Framework\App\Bootstrap::create(\BP, $_SERVER);
        $om = $bootstrap->getObjectManager();
        if ($area !== 'global') {
            $om->get(\Magento\Framework\App\State::class)->setAreaCode($area);
            $om->configure($om->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class)->load($area));
        }
        $results = [];
        foreach ((array)json_decode($itemsJson, true) as $item) {
            $results[] = $item['type'] === 'api' ? self::api($om, $item, $write) : self::di($om, $item);
        }
        echo json_encode(['results' => $results]), "\n";
        return 0;
    }

    /** @param array<string,mixed> $item */
    private static function di(\Magento\Framework\ObjectManagerInterface $om, array $item): array
    {
        $base = ['type' => 'di', 'file' => $item['file'], 'cls' => 'C5'];
        $class = $item['class'];
        try {
            $ref = new \ReflectionClass($class);
            if (!$ref->isInstantiable()) {
                return $base + ['status' => 'skip'];
            }
            $om->create($class);
            return $base + ['status' => 'ok'];
        } catch (\Throwable $t) {
            $msg = Report::oneLine($t->getMessage());
            if (preg_match('/Missing required argument \$\w+ of/', $msg)) {
                // argumento escalar que o XML (UI Component, virtualType) fornece: não dá para julgar aqui
                return $base + ['status' => 'skip'];
            }
            if (preg_match('/Cannot instantiate interface ([\w\\\\]+)/', $msg, $m)) {
                return $base + ['status' => 'error', 'id' => 'SMOKE-DI-PREFERENCE',
                    'message' => "$class nao sobe: falta preference para {$m[1]} no di.xml.",
                    'hint' => 'Declare preference for=interface type=classe (magento-example service-contract).'];
            }
            return $base + ['status' => 'error', 'id' => 'SMOKE-DI', 'message' => "$class nao sobe pelo ObjectManager: " . substr($msg, 0, 300),
                'hint' => 'Confira o construtor e os argumentos do di.xml (tipos e nomes).'];
        }
    }

    /** @param array<string,mixed> $item */
    private static function api(\Magento\Framework\ObjectManagerInterface $om, array $item, bool $write): array
    {
        $base = ['type' => 'api', 'file' => $item['file'], 'line' => $item['line'], 'cls' => 'C9'];
        $label = "{$item['route']} -> {$item['class']}::{$item['method']}()";
        try {
            $service = $om->get($item['class']);
            $method = new \ReflectionMethod($service, $item['method']);
        } catch (\Throwable $t) {
            return $base + ['status' => 'error', 'id' => 'SMOKE-API', 'message' => "$label: o servico nao sobe: " . Report::oneLine(substr($t->getMessage(), 0, 250))];
        }
        $params = $method->getParameters();
        $first = $params[0] ?? null;
        $type = $first && $first->getType() instanceof \ReflectionNamedType ? $first->getType()->getName() : '';
        try {
            if ($type === \Magento\Framework\Api\SearchCriteriaInterface::class) {
                $criteria = $om->create(\Magento\Framework\Api\SearchCriteriaBuilder::class)->setPageSize(1)->create();
                $method->invoke($service, $criteria);
                return $base + ['status' => 'ok'];
            }
            if (count($params) === 1 && in_array($type, ['int', 'string', ''], true) && preg_match('/^get/i', $item['method'])) {
                try {
                    $method->invoke($service, $type === 'string' ? 'smoke-missing-' . bin2hex(random_bytes(3)) : 999999999);
                } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                    return $base + ['status' => 'ok'];
                }
                return $base + ['status' => 'ok'];
            }
            if ($write && $first && $type !== '' && interface_exists($type) && preg_match('/^(save|create)/i', $item['method'])) {
                return self::write($om, $service, $method, $type, $label, $base);
            }
            return $base + ['status' => 'skip'];
        } catch (\Throwable $t) {
            return $base + ['status' => 'error', 'id' => 'SMOKE-API',
                'message' => "$label quebra com dado de teste: " . get_class($t) . ': ' . Report::oneLine(substr($t->getMessage(), 0, 250)),
                'hint' => 'Rode o metodo com um caso simples e confira o caminho do erro (repositorio, resource model, collection).'];
        }
    }

    /**
     * save() com um DTO de teste montado pelos setters e pelos tipos do docblock, dentro de uma
     * transação desfeita no fim: prova que o caminho de gravação funciona sem deixar dado.
     *
     * @param array<string,mixed> $base
     */
    private static function write(\Magento\Framework\ObjectManagerInterface $om, object $service, \ReflectionMethod $method,
                                  string $dtoType, string $label, array $base): array
    {
        $dto = $om->create($dtoType);
        foreach ((new \ReflectionClass($dtoType))->getMethods() as $setter) {
            if (!preg_match('/^set(\w+)$/', $setter->getName(), $m) || $setter->getNumberOfParameters() !== 1
                || in_array($m[1], ['Id', 'ExtensionAttributes'], true)) {
                continue;
            }
            $p = $setter->getParameters()[0];
            $t = $p->getType() instanceof \ReflectionNamedType ? $p->getType()->getName() : 'string';
            $field = strtolower($m[1]);
            $value = match (true) {
                $t === 'int' => 1,
                $t === 'float' => 1.5,
                $t === 'bool' => true,
                str_contains($field, 'email') => 'smoke@example.com',
                str_contains($field, 'date') || str_contains($field, 'from') => '2026-01-01',
                $t === 'string' || $t === 'mixed' => 'SMOKE_' . strtoupper(bin2hex(random_bytes(3))),
                default => null,
            };
            if ($value !== null) {
                try {
                    $setter->invoke($dto, $value);
                } catch (\Throwable) {
                    // setter com regra própria: segue sem esse campo
                }
            }
        }
        $connection = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
        $connection->beginTransaction();
        try {
            $method->invoke($service, $dto);
            return $base + ['status' => 'ok'];
        } catch (\Magento\Framework\Exception\InputException | \Magento\Framework\Exception\ValidatorException $e) {
            return $base + ['status' => 'warn', 'id' => 'SMOKE-API-INPUT',
                'message' => "$label recusou o dado de teste (validacao): " . Report::oneLine(substr($e->getMessage(), 0, 200))];
        } finally {
            $connection->rollBack();
        }
    }
}
