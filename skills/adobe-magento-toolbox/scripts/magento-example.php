<?php
/**
 * magento-example: mostra como um padrão Magento é feito, primeiro no módulo de referência da skill
 * (reference/Acme/Reference, provado numa instalação real) e, se o padrão não estiver lá, nos módulos
 * do projeto (app/code) e depois no core (vendor/magento).
 *
 * Uso (na raiz do Magento, para a busca no projeto funcionar):
 *   php <skill>/scripts/magento-example.php <tema ou palavras>    ex.: plugin, ui-form, "checkout field"
 *   php <skill>/scripts/magento-example.php --list                 todos os temas
 *   php <skill>/scripts/magento-example.php --file <caminho>       um arquivo do módulo de referência
 *   php <skill>/scripts/magento-example.php --build-bundle         regera reference/bundle-N.json (repo)
 *
 * O módulo vem de reference/bundle-N.json quando existe (é o que vai para a instância) e dos
 * arquivos de reference/ quando não existe.
 */
declare(strict_types=1);

require __DIR__ . '/lib/Reference.php';

$refRoot = dirname(__DIR__) . '/reference';
$args = array_slice($argv, 1);

if (($args[0] ?? '') === '--build-bundle') {
    if (!\MagentoToolbox\Reference::hasSources($refRoot)) {
        fwrite(STDERR, "Sem o modulo de referencia em disco (reference/Acme/Reference e patterns.json): nada a empacotar.\n");
        exit(2);
    }
    $chunks = \MagentoToolbox\Reference::write($refRoot);
    echo "reference/bundle-*.json: $chunks pedaco(s), " . count(\MagentoToolbox\Reference::sources($refRoot)) . " arquivos\n";
    exit(0);
}

// sem o patterns.json (módulo de referência não publicado nesta instalação) só sobra a busca no projeto
$ref = \MagentoToolbox\Reference::open($refRoot);
$data = $ref->catalog();
$catalog = $data['patterns'] ?? [];
$maps = $data['maps'] ?? [];

if (!$args || $args[0] === '--list') {
    if (!$catalog) {
        echo "Modulo de referencia nao publicado aqui: php magento-example.php <palavras> busca exemplos no app/code e no vendor.\n";
        exit(0);
    }
    echo "Temas do modulo de referencia (php magento-example.php <tema>):\n";
    foreach ($catalog as $p) {
        printf("  %-22s %s\n", $p['id'], $p['title']);
    }
    echo "Mapas de fluxo (como as pecas se ligam; use junto com magento-trace):\n";
    foreach ($maps as $m) {
        printf("  %-22s %s\n", $m['id'], $m['title']);
    }
    exit(0);
}

if ($args[0] === '--file') {
    $rel = ltrim((string)($args[1] ?? ''), '/');
    $content = $rel === '' ? null : $ref->read('Acme/Reference/' . $rel);
    if ($content === null) {
        fwrite(STDERR, "Arquivo nao encontrado no modulo de referencia: $rel\n");
        exit(1);
    }
    printContent($rel, $content);
    exit(0);
}

$query = strtolower(trim(implode(' ', $args)));
foreach ($maps as $m) {
    if (score($query, $m) >= 90 || (str_starts_with($query, 'map') && score(trim(substr($query, strpos($query, ' ') ?: 0)), $m) > 0)) {
        echo "MAPA: {$m['title']} (tema {$m['id']})\n\n" . (string)$ref->read($m['file']);
        exit(0);
    }
}
$best = null;
$bestScore = 0;
foreach ($catalog as $p) {
    $score = score($query, $p);
    if ($score > $bestScore) {
        [$best, $bestScore] = [$p, $score];
    }
}

if ($best !== null) {
    echo "PADRAO: {$best['title']} (tema {$best['id']})\n";
    echo "Modulo de referencia provado em Magento 2.4.8. Copie a FORMA e troque Acme\\Reference, Acme_Reference e\n";
    echo "acme_reference pelo nome do seu modulo; nunca copie o modulo inteiro nem mantenha o nome Acme.\n";
    echo "Arquivos do padrao:\n";
    foreach ($best['files'] as $f) {
        echo "  $f\n";
    }
    echo "Veja outro arquivo: php " . $argv[0] . " --file <caminho acima>\n\n";
    printContent($best['files'][0], $ref->read('Acme/Reference/' . $best['files'][0]));
    exit(0);
}

// Fora do módulo de referência: exemplos no projeto e no core.
echo "Nao ha padrao \"$query\" no modulo de referencia (temas: --list). Exemplos reais:\n";
$root = findRoot(getcwd());
$words = array_values(array_filter(preg_split('/[^a-z0-9_]+/', $query) ?: [], fn($w) => strlen($w) > 2));
if (!$root || !$words) {
    echo "  (rode na raiz de um Magento e use palavras com mais de 2 letras)\n";
    exit(1);
}
$found = false;
foreach (['no projeto (app/code)' => $root . '/app/code', 'no core (vendor/magento)' => $root . '/vendor/magento'] as $label => $dir) {
    $hits = searchFiles($dir, $words);
    if (!$hits) {
        continue;
    }
    $found = true;
    echo "  $label:\n";
    foreach ($hits as $h) {
        echo '    ' . substr($h, strlen($root) + 1) . "\n";
    }
}
if (!$found) {
    echo "  nenhum arquivo com todas as palavras: " . implode(' ', $words) . "\n";
}
exit($found ? 0 : 1);

/**
 * @param string $query
 * @param array<string,mixed> $pattern
 * @return int
 */
function score(string $query, array $pattern): int
{
    if ($query === $pattern['id']) {
        return 100;
    }
    $best = 0;
    foreach ($pattern['aliases'] as $alias) {
        if ($query === $alias) {
            return 90;
        }
        if (str_contains($query, $alias) || str_contains($alias, $query)) {
            $best = max($best, 40 + strlen($alias));
        }
    }
    return $best;
}

function printContent(string $rel, ?string $content): void
{
    $lines = preg_split('/(?<=\n)/', (string)$content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    echo "===== $rel (" . count($lines) . " linhas) =====\n";
    foreach (array_slice($lines, 0, 220) as $i => $line) {
        printf("%4d  %s", $i + 1, $line);
    }
    if (count($lines) > 220) {
        echo "... (" . (count($lines) - 220) . " linhas a mais)\n";
    }
}

function findRoot(string $dir): ?string
{
    while ($dir !== '/' && $dir !== '') {
        if (is_file($dir . '/app/bootstrap.php')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

/**
 * Arquivos que têm TODAS as palavras (no caminho ou no conteúdo), sem testes, até 5.
 *
 * @param string $dir
 * @param string[] $words
 * @return string[]
 */
function searchFiles(string $dir, array $words): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $cmd = 'grep -rlIi --include=*.php --include=*.xml --include=*.phtml --include=*.js --include=*.graphqls '
        . '--exclude-dir=Test --exclude-dir=Tests -e ' . escapeshellarg($words[0]) . ' ' . escapeshellarg($dir)
        . ' 2>/dev/null | head -400';
    $candidates = array_filter(explode("\n", (string)shell_exec($cmd)));
    $hits = [];
    foreach ($candidates as $file) {
        $haystack = strtolower($file . ' ' . (string)@file_get_contents($file, false, null, 0, 200000));
        $ok = true;
        foreach (array_slice($words, 1) as $w) {
            if (!str_contains($haystack, $w)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $hits[] = $file;
        }
        if (count($hits) === 5) {
            break;
        }
    }
    return $hits;
}
