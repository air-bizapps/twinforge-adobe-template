<?php
/**
 * ui-render-check: monta de verdade cada UI Component alterado na branch (área adminhtml), como o
 * Magento faz ao abrir a tela, e valida a configuração JS gerada. Pega o que só aparece em execução:
 * exceção ao criar o componente, DataProvider que quebra, e o grafo de deps que nunca resolve
 * (spinner infinito sem erro no console). Não precisa de navegador; precisa de banco (só leitura).
 *
 * Uso, na raiz de um Magento instalado (app/etc/env.php):
 *   php <skill>/scripts/ui-render-check.php [--base <ref>] [nome_do_componente ...]
 * Exit: 0 OK, 1 erro, 2 ambiente.
 */
declare(strict_types=1);

namespace MagentoToolbox;

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/ClassIndex.php';

$opts = getopt('', ['base:'], $rest);
$names = array_slice($argv, $rest);
$root = Env::findRoot(getcwd());
if (!$root || !is_file($root . '/app/etc/env.php')) {
    fwrite(STDERR, "Rode na raiz de um Magento instalado (com app/etc/env.php).\n");
    exit(2);
}
$env = new Env($root);
$env->boot();
require __DIR__ . '/lib/XmlChecks.php';
$index = new ClassIndex($env);
$js = new JsResolver($index);

$files = [];
if (!$names) {
    $env->collectChanges($opts['base'] ?? ('origin/' . (getenv('BUNGE_BASE_BRANCH') ?: 'twinforge')));
    foreach ($env->present('#/view/adminhtml/ui_component/[^/]+\.xml$#') as $rel) {
        $names[] = basename($rel, '.xml');
        $files[basename($rel, '.xml')] = $rel;
    }
}
if (!$names) {
    echo "RENDER OK | nenhum ui_component adminhtml alterado\n";
    exit(0);
}

require_once $root . '/app/bootstrap.php';
$env->registerLocalModules();
$report = new Report();
try {
    $bootstrap = Bootstrap::create(\BP, $_SERVER);
    $om = $bootstrap->getObjectManager();
    $om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
    $om->configure($om->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class)->load('adminhtml'));
} catch (\Throwable $t) {
    $msg = strtok($t->getMessage(), "\n");
    $report->add(new Finding('UI-RENDER-BOOT', 'C2', 'error', 'app/etc', 0,
        'O Magento nao sobe com esta branch (setup:upgrade e cache:flush tambem quebram): ' . $msg,
        'Rode magento-verify: ele aponta o arquivo e a linha (normalmente XML fora do XSD).'));
    $env->branch = $env->branch ?: '-';
    echo str_replace('VERIFY', 'RENDER', $report->summary($env, 'render')), "\n";
    exit(1);
}
foreach ($names as $name) {
    $rel = $files[$name] ?? $name;
    $report->count('ui_component');
    try {
        $html = render($om, $name);
    } catch (\Throwable $t) {
        $report->add(new Finding('UI-RENDER-EXCEPTION', 'C3', 'error', $rel, 0,
            "$name quebra ao montar: " . get_class($t) . ': '
                . substr(str_replace((string)realpath($root) . '/', '', implode(' ', array_slice(array_filter(array_map('trim',
                    explode("\n", $t->getMessage()))), 0, 3))), 0, 400),
            'Origem: ' . shortPath($t->getFile(), $root) . ':' . $t->getLine()));
        continue;
    }
    foreach (validate($name, $html, $js) as [$id, $msg]) {
        $report->add(new Finding($id, 'C3', 'error', $rel, 0, $msg));
    }
}
$env->branch = $env->branch ?: '-';
@mkdir($env->outDir, 0775, true);
file_put_contents($env->outDir . '/last-render.md', $report->markdown($env, 'render'));
echo str_replace(['VERIFY', 'var/verify/last.md (rode magento-verify --last)'], ['RENDER', 'var/verify/last-render.md'],
    $report->summary($env, 'render')), "\n";
exit($report->errors() ? 1 : 0);

/** Cria, prepara (filhos antes do pai, como o controller mui/index/render) e renderiza. */
function render(\Magento\Framework\ObjectManagerInterface $om, string $name): string
{
    $component = $om->get(\Magento\Framework\View\Element\UiComponentFactory::class)->create($name);
    $prepare = function ($c) use (&$prepare): void {
        foreach ($c->getChildComponents() as $child) {
            $prepare($child);
        }
        $c->prepare();
    };
    $prepare($component);
    return (string)$component->render();
}

/**
 * Valida a árvore de componentes que vai para o x-magento-init.
 *
 * @return array<int,array{0:string,1:string}>
 */
function validate(string $name, string $html, JsResolver $js): array
{
    $out = [];
    $tree = null;
    if (preg_match_all('#<script type="text/x-magento-init">(.*?)</script>#s', $html, $m)) {
        foreach ($m[1] as $json) {
            $data = json_decode(trim($json), true);
            $app = $data['*']['Magento_Ui/js/core/app'] ?? null;
            if ($app) {
                $tree = $app;
                break;
            }
        }
    }
    if (!$tree || empty($tree['components'])) {
        return [['UI-RENDER-NO-CONFIG', "$name renderizou sem configuracao JS (Magento_Ui/js/core/app): a tela fica vazia."]];
    }
    $nodes = [];
    $walk = function (array $children, string $prefix) use (&$walk, &$nodes): void {
        foreach ($children as $key => $node) {
            $full = $prefix === '' ? $key : "$prefix.$key";
            $nodes[$full] = $node;
            if (!empty($node['children']) && is_array($node['children'])) {
                $walk($node['children'], $full);
            }
        }
    };
    $walk($tree['components'], '');
    $types = $tree['types'] ?? [];
    foreach ($nodes as $full => $node) {
        $cfg = $node['config'] ?? [];
        $component = $node['component'] ?? ($types[$node['type'] ?? '']['component'] ?? null);
        if (str_ends_with($full, '_data_source') || str_contains($full, 'data_source')) {
            if (!$component && empty($cfg['component'])) {
                $out[] = ['UI-RENDER-DS-NO-COMPONENT', "dataSource $full sem componente JS na configuracao final: spinner infinito."];
            }
        }
        if (is_string($component) && $js->jsFile($component) === '') {
            $out[] = ['UI-RENDER-JS-MISSING', "$full usa o componente $component, que nao existe como arquivo."];
        }
        foreach (['template', 'elementTmpl'] as $t) {
            if (isset($cfg[$t]) && is_string($cfg[$t]) && !preg_match('/[${}]/', $cfg[$t]) && $js->templateFile($cfg[$t]) === '') {
                $out[] = ['UI-RENDER-TEMPLATE-MISSING', "$full: $t {$cfg[$t]} nao existe."];
            }
        }
        $deps = array_merge((array)($cfg['deps'] ?? []), isset($cfg['provider']) ? [$cfg['provider']] : []);
        foreach ($deps as $dep) {
            if (is_string($dep) && $dep !== '' && !preg_match('/[${}]/', $dep) && !isset($nodes[$dep])) {
                $out[] = ['UI-RENDER-DEPS', "$full depende de $dep, que nao existe na arvore montada: nunca resolve (spinner)."];
            }
        }
    }
    // todo spinner do HTML espera um componente da arvore; se ele nao existe, o spinner nunca some
    if (preg_match_all('/data-role="spinner"[^>]*data-component="([^"]+)"|data-component="([^"]+)"[^>]*data-role="spinner"/', $html, $sp)) {
        foreach (array_unique(array_filter(array_merge($sp[1], $sp[2]))) as $ref) {
            if (!isset($nodes[$ref])) {
                $out[] = ['UI-RENDER-SPINNER-ORPHAN', "O HTML mostra um spinner ate o componente $ref carregar, mas ele nao existe na arvore: o spinner nunca some."];
            }
        }
    }
    return array_values(array_unique($out, SORT_REGULAR));
}

function shortPath(string $file, string $root): string
{
    $root = (string)realpath($root);
    return str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : preg_replace('#^.*/vendor/#', 'vendor/', $file);
}
