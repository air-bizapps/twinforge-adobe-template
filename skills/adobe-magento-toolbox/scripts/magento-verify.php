<?php
/**
 * magento-verify: confere os arquivos alterados na branch antes do done. Não precisa de banco,
 * navegador nem bin/magento: lê o código, o vendor e os XSD do próprio Magento.
 *
 * Uso, na raiz do worktree Magento:
 *   php <skill>/scripts/magento-verify.php [--base <ref>] [--json]   checagem (modo fast)
 *   php <skill>/scripts/magento-verify.php --last                    reimprime o último resultado completo
 *   php <skill>/scripts/magento-verify.php --self-test               confere as regras contra os casos de teste
 *
 * Base padrão: origin/$BUNGE_BASE_BRANCH, ou origin/twinforge. Saída curta (o que o agente lê) no
 * stdout e o detalhe em var/verify/last.md. Exit: 0 OK, 1 erro, 2 uso ou ambiente, 3 limite de execuções.
 */
declare(strict_types=1);

namespace MagentoToolbox;

require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/ClassIndex.php';

$opts = getopt('', ['base:', 'json', 'last', 'self-test', 'root:', 'allow-protected', 'max-runs:', 'path:']);
$root = Env::findRoot($opts['root'] ?? getcwd());

if (isset($opts['self-test'])) {
    require __DIR__ . '/lib/SelfTest.php';
    exit(SelfTest::run($root, dirname(__DIR__) . '/tests/fixtures'));
}
if (!$root) {
    fwrite(STDERR, "Rode na raiz de um projeto Magento (onde estao app/bootstrap.php e vendor/).\n");
    exit(2);
}
$env = new Env($root);
if (isset($opts['last'])) {
    $last = $env->outDir . '/last.md';
    echo is_file($last) ? file_get_contents($last) : "Nenhuma execucao anterior em var/verify/.\n";
    exit(0);
}

// teto por run do agente: o loop é da plataforma, não da ferramenta
$runId = getenv('TWINFORGE_RUN_ID') ?: '';
$max = (int)($opts['max-runs'] ?? getenv('MAGENTO_VERIFY_MAX_RUNS') ?: 3);
if ($runId !== '') {
    @mkdir($env->outDir . '/runs', 0775, true);
    $counter = $env->outDir . '/runs/' . preg_replace('/[^A-Za-z0-9_-]/', '', $runId);
    $n = (int)@file_get_contents($counter) + 1;
    file_put_contents($counter, (string)$n);
    if ($n > $max) {
        echo "LIMITE: magento-verify ja rodou $max vezes neste run. Pare de tentar: marque blocked citando os IDs "
            . "que sobraram em var/verify/last.md (rode magento-verify --last).\n";
        exit(3);
    }
}

try {
    $env->boot();
    require __DIR__ . '/lib/PhpChecks.php';
    require __DIR__ . '/lib/XmlChecks.php';
    require __DIR__ . '/lib/UiRules.php';
    require __DIR__ . '/lib/WebapiRules.php';
    require __DIR__ . '/lib/DiffChecks.php';
    if (isset($opts['path'])) {
        // um diretório inteiro conta como novo (ex.: conferir um módulo de uma vez)
        $dir = rtrim((string)$opts['path'], '/');
        $abs = str_starts_with($dir, '/') ? $dir : $root . '/' . $dir;
        if (!is_dir($abs) || !str_starts_with($dir, 'app/')) {
            throw new \RuntimeException("--path precisa ser um diretorio dentro de app/ (relativo a raiz): $dir");
        }
        $env->branch = $env->git('rev-parse --abbrev-ref HEAD');
        $env->head = $env->git('rev-parse --short HEAD');
        $env->useAllFiles($abs, $dir . '/');
    } else {
        $base = $opts['base'] ?? ('origin/' . (getenv('BUNGE_BASE_BRANCH') ?: 'twinforge'));
        $env->collectChanges($base);
    }
} catch (\Throwable $t) {
    fwrite(STDERR, 'VERIFY ERRO DE AMBIENTE: ' . $t->getMessage() . "\n");
    exit(2);
}

$allowReference = str_starts_with(trim((string)($opts['path'] ?? ''), '/'), 'app/code/Acme/Reference');
$report = Checks::run($env, isset($opts['allow-protected']), $allowReference);
@mkdir($env->outDir, 0775, true);
file_put_contents($env->outDir . '/last.md', $report->markdown($env, 'fast'));
file_put_contents($env->outDir . '/last.json', json_encode($report->toArray($env, 'fast'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo isset($opts['json']) ? json_encode($report->toArray($env, 'fast'), JSON_UNESCAPED_SLASHES) : $report->summary($env, 'fast'), "\n";
exit($report->errors() ? 1 : 0);

final class Checks
{
    public static function run(Env $env, bool $allowProtected = false, bool $allowReference = false): Report
    {
        $report = new Report();
        $index = new ClassIndex($env);
        $js = new JsResolver($index);
        (new DiffChecks($env, $index, $report, $js))->run($allowProtected, $allowReference);
        (new PhpChecks($env, $index, $report))->run();
        (new XmlChecks($env, $index, $report, $js))->run();
        return $report;
    }
}
