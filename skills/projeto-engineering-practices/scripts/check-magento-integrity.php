<?php
/**
 * Checagem de integridade dos arquivos alterados na branch (só leitura, não roda o Magento).
 *
 * Uso, na raiz do worktree:  php <esta-skill>/scripts/check-magento-integrity.php [base]
 *   base: ref de comparação (padrão: origin/$BUNGE_BASE_BRANCH, ou origin/twinforge)
 *
 * Confere, só nos arquivos .php e .xml adicionados/alterados desde a base:
 *   1. sintaxe PHP (php -l);
 *   2. XML válido contra o XSD que o próprio arquivo declara (urn:magento:...);
 *   3. toda classe/interface citada (use, ::class, \FQCN, atributos e argumentos de XML) existe.
 * Sai com código 1 e uma linha "ERRO" por problema; "OK" quando não há nenhum.
 */
declare(strict_types=1);

$root = getcwd();
if (!is_file($root . '/app/bootstrap.php')) {
    fwrite(STDERR, "Rode na raiz do repositório Magento (onde está app/bootstrap.php).\n");
    exit(2);
}
require $root . '/app/bootstrap.php';

$base = $argv[1] ?? ('origin/' . (getenv('BUNGE_BASE_BRANCH') ?: 'twinforge'));
$files = array_filter(explode("\n", (string)shell_exec(
    'git diff --name-only --diff-filter=AM ' . escapeshellarg($base . '...HEAD') . ' 2>/dev/null'
)));
$files = array_values(array_filter($files, fn($f) => is_file($f) && preg_match('/\.(php|xml)$/', $f)));

$urn = new \Magento\Framework\Config\Dom\UrnResolver();
// os XSD do Magento incluem outros por urn:, como o próprio Magento resolve
libxml_set_external_entity_loader([$urn, 'registerEntityLoader']);
$errors = [];
$checked = ['php' => 0, 'xml' => 0, 'classes' => 0];
$names = [];
$fqcn = '[A-Z][A-Za-z0-9_]*(?:\\\\[A-Za-z0-9_]+)+';

foreach ($files as $file) {
    $content = (string)file_get_contents($file);
    if (str_ends_with($file, '.php')) {
        $checked['php']++;
        $out = (string)shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
        if (!str_contains($out, 'No syntax errors')) {
            $errors[] = "ERRO php-sintaxe $file: " . trim($out);
        }
        preg_match_all('/^use\s+(' . $fqcn . ')(?:\s+as\s+\w+)?;/m', $content, $m);
        preg_match_all('/\\\\(' . $fqcn . ')(?=::class|\s*\(|\s+\$|\s*[,)])/', $content, $m2);
        foreach (array_merge($m[1], $m2[1]) as $n) {
            $names[$n][] = $file;
        }
        continue;
    }
    $checked['xml']++;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML($content)) {
        $errors[] = "ERRO xml-malformado $file: " . trim((string)(libxml_get_errors()[0]->message ?? ''));
        libxml_clear_errors();
        continue;
    }
    if (preg_match('/noNamespaceSchemaLocation="(urn:magento:[^"]+)"/', $content, $u)) {
        try {
            $xsd = $urn->getRealPath($u[1]);
            $fileXsd = preg_replace('/\.xsd$/', '_file.xsd', $xsd);
            $xsd = is_file($fileXsd) ? $fileXsd : $xsd;
            if (!$dom->schemaValidate($xsd)) {
                $e = libxml_get_errors()[0] ?? null;
                $errors[] = "ERRO xml-xsd $file:" . ($e->line ?? '?') . ': ' . trim((string)($e->message ?? ''));
            }
        } catch (\Throwable $t) {
            $errors[] = "ERRO xml-xsd $file: não resolveu {$u[1]} ({$t->getMessage()})";
        }
        libxml_clear_errors();
    }
    preg_match_all('/(?:class|type|instance|for|source_model|backend_model|frontend_model|component)="\\\\?(' . $fqcn . ')"/', $content, $m);
    preg_match_all('/xsi:type="object"[^>]*>\s*\\\\?(' . $fqcn . ')\s*</', $content, $m2);
    preg_match_all('/<(?:source_model|backend_model|frontend_model)>\s*\\\\?(' . $fqcn . ')\s*</', $content, $m3);
    foreach (array_merge($m[1], $m2[1], $m3[1]) as $n) {
        $names[$n][] = $file;
    }
}

foreach ($names as $name => $where) {
    if (str_contains($name, '/')) {
        continue; // componente JS (Vendor_Module/js/...), não é classe PHP
    }
    $checked['classes']++;
    $exists = fn($n) => class_exists($n) || interface_exists($n) || trait_exists($n) || enum_exists($n);
    // Factory, Proxy e Interceptor que não existem em disco são gerados: confere a classe de origem
    $origin = preg_replace('/(Factory|\\\\Proxy|\\\\Interceptor)$/', '', $name);
    if (!$exists($name) && ($origin === $name || !$exists($origin))) {
        $errors[] = "ERRO classe-inexistente $name (em " . implode(', ', array_unique($where)) . ')';
    }
}

foreach ($errors as $e) {
    echo $e, PHP_EOL;
}
printf("%s: %d php, %d xml, %d classes conferidos contra %s\n",
    $errors ? count($errors) . ' ERRO(S)' : 'OK', $checked['php'], $checked['xml'], $checked['classes'], $base);
exit($errors ? 1 : 0);
