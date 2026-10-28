<?php
/**
 * magento-trace: o caminho real de uma página Magento, com arquivo e linha de cada elo, e o elo
 * quebrado marcado. Responde "como a URL chega até o JS" com o código deste projeto, não de memória.
 *
 *   URL -> área e roteador -> routes.xml (frontName) -> controller -> handle de layout
 *       -> arquivos de layout (módulos e tema) -> blocos, templates .phtml e uiComponent
 *       -> JS: x-magento-init / data-mage-init -> RequireJS (map) -> arquivo .js
 *       -> uiComponent: XML -> dataSource (componente JS) -> DataProvider
 *
 * Uso, na raiz do Magento (só lê arquivos; não precisa de banco):
 *   php <skill>/scripts/magento-trace.php acme-reference/index/index           (storefront)
 *   php <skill>/scripts/magento-trace.php admin/acme_reference/item/index      (admin)
 *   php <skill>/scripts/magento-trace.php --handle checkout_index_index [--area frontend]
 *   php <skill>/scripts/magento-trace.php --ui acme_reference_item_form
 * Exit 1 quando algum elo está quebrado.
 */
declare(strict_types=1);

namespace MagentoToolbox;

require __DIR__ . '/lib/Env.php';
require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/ClassIndex.php';

$opts = getopt('', ['handle:', 'ui:', 'area:'], $rest);
$root = Env::findRoot(getcwd());
if (!$root) {
    fwrite(STDERR, "Rode na raiz de um Magento.\n");
    exit(2);
}
$env = new Env($root);
$env->boot();
require __DIR__ . '/lib/XmlChecks.php';
$index = new ClassIndex($env);
$js = new JsResolver($index);
$tracer = new Tracer($env, $index, $js);

$target = $argv[$rest] ?? null;
if (isset($opts['ui'])) {
    $tracer->uiComponent((string)$opts['ui'], (string)($opts['area'] ?? 'adminhtml'), 0);
} elseif (isset($opts['handle'])) {
    $tracer->handle((string)$opts['handle'], (string)($opts['area'] ?? 'frontend'), 0);
} elseif ($target) {
    $tracer->url((string)$target);
} else {
    fwrite(STDERR, "Uso: magento-trace.php <url> | --handle <handle> [--area frontend|adminhtml] | --ui <nome>\n");
    exit(2);
}
echo implode("\n", $tracer->lines), "\n";
echo $tracer->broken ? "TRACE: {$tracer->broken} elo(s) quebrado(s) (marcados com X)\n" : "TRACE OK\n";
exit($tracer->broken ? 1 : 0);

final class Tracer
{
    /** @var string[] */
    public array $lines = [];
    public int $broken = 0;
    /** @var array<string,true> */
    private array $seenTemplates = [];
    /** @var array<string,true> */
    private array $seenUi = [];
    private ?array $requireMap = null;

    public function __construct(private Env $env, private ClassIndex $index, private JsResolver $js)
    {
    }

    private function out(int $depth, bool $ok, string $text): void
    {
        $this->lines[] = str_repeat('  ', $depth) . ($ok ? '- ' : 'X ') . $text;
        if (!$ok) {
            $this->broken++;
        }
    }

    private function rel(string $path): string
    {
        $root = $this->env->root . '/';
        $vendor = (string)realpath($this->env->root . '/vendor');
        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }
        return ($vendor !== '' && str_starts_with($path, $vendor . '/')) ? 'vendor/' . substr($path, strlen($vendor) + 1) : $path;
    }

    /** @return array<string,string> Vendor_Module => diretório (app/code do worktree e vendor) */
    private function modules(): array
    {
        static $modules = null;
        if ($modules !== null) {
            return $modules;
        }
        $modules = [];
        $registrar = \Magento\Framework\Component\ComponentRegistrar::class;
        if (class_exists($registrar)) {
            foreach ((new $registrar())->getPaths($registrar::MODULE) as $name => $dir) {
                $modules[$name] = $dir;
            }
        }
        foreach (glob($this->env->codeRoot . '/*/*/registration.php') ?: [] as $reg) {
            if (preg_match("/ComponentRegistrar::MODULE,\s*'([^']+)'/", (string)file_get_contents($reg), $m)) {
                $modules[$m[1]] = dirname($reg);
            }
        }
        return $modules;
    }

    /** Diretórios de tema que podem sobrescrever layout e template (app/design). */
    private function themeDirs(string $area): array
    {
        return glob($this->env->root . "/app/design/$area/*/*", GLOB_ONLYDIR) ?: [];
    }

    public function url(string $url): void
    {
        $path = trim((string)parse_url($url, PHP_URL_PATH) ?: $url, '/');
        $parts = $path === '' ? [] : explode('/', $path);
        $area = 'frontend';
        $routerId = 'standard';
        if (($parts[0] ?? '') === 'admin') {
            $area = 'adminhtml';
            $routerId = 'admin';
            array_shift($parts);
        }
        $front = $parts[0] ?? 'cms';
        $controller = $parts[1] ?? 'index';
        $action = $parts[2] ?? 'index';
        $this->lines[] = "URL /$path -> area $area, roteador $routerId: frontName \"$front\", controller \"$controller\", action \"$action\"";

        $routes = $this->routes($area, $routerId, $front);
        if (!$routes) {
            $this->out(1, false, "nenhum routes.xml ($area, router $routerId) declara frontName \"$front\": a URL cai no 404 (ou e URL rewrite/CMS)");
            return;
        }
        $routeId = $routes[0]['id'];
        foreach ($routes as $r) {
            $this->out(1, true, "rota \"{$r['id']}\" do modulo {$r['module']} ({$r['file']}:{$r['line']})");
        }
        $found = false;
        foreach ($routes as $r) {
            $class = str_replace('_', '\\', $r['module']) . '\\Controller\\' . ($area === 'adminhtml' ? 'Adminhtml\\' : '')
                . self::studly($controller) . '\\' . self::studly($action === 'new' ? 'new_action' : $action);
            if ($this->index->exists($class)) {
                $file = $this->index->file($class);
                $ok = $file && preg_match('/function\s+execute\s*\(/', (string)file_get_contents($file));
                $this->out(2, (bool)$ok, "controller $class (" . ($file ? $this->rel($file) : '?') . ')' . ($ok ? '' : ': sem execute()'));
                if ($area === 'adminhtml' && $file && !preg_match('/ADMIN_RESOURCE\s*=/', (string)file_get_contents($file))) {
                    $this->out(3, false, 'controller admin sem ADMIN_RESOURCE: qualquer usuario do admin acessa');
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->out(2, false, 'nenhum controller ' . self::studly($controller) . '\\' . self::studly($action) . ' nos modulos da rota: 404');
        }
        $this->handle("{$routeId}_{$controller}_{$action}", $area, 1);
    }

    /** @return array<int,array{id:string,module:string,file:string,line:int}> */
    private function routes(string $area, string $routerId, string $front): array
    {
        $found = [];
        foreach ($this->modules() as $module => $dir) {
            $file = "$dir/etc/$area/routes.xml";
            if (!is_file($file)) {
                continue;
            }
            $dom = new \DOMDocument();
            if (!@$dom->load($file)) {
                continue;
            }
            $xp = new \DOMXPath($dom);
            foreach ($xp->query("//router[@id='$routerId']/route[@frontName='$front']") as $route) {
                foreach ($xp->query('./module', $route) as $m) {
                    $found[] = ['id' => $route->getAttribute('id'), 'module' => $m->getAttribute('name'),
                        'file' => $this->rel($file), 'line' => $route->getLineNo()];
                }
            }
        }
        return $found;
    }

    public function handle(string $handle, string $area, int $depth): void
    {
        $files = [];
        foreach ($this->modules() as $module => $dir) {
            foreach (["$dir/view/$area/layout/$handle.xml", "$dir/view/base/layout/$handle.xml"] as $f) {
                if (is_file($f)) {
                    $files[] = $f;
                }
            }
        }
        foreach ($this->themeDirs($area) as $theme) {
            foreach (glob("$theme/*/layout/$handle.xml") ?: [] as $f) {
                $files[] = $f;
            }
        }
        if (!$files) {
            $this->out($depth, false, "handle $handle: nenhum arquivo de layout (a pagina abre vazia)");
            return;
        }
        $this->out($depth, true, "handle $handle: " . count($files) . ' arquivo(s) de layout');
        foreach ($files as $file) {
            $this->layoutFile($file, $area, $depth + 1);
        }
    }

    private function layoutFile(string $file, string $area, int $depth): void
    {
        $dom = new \DOMDocument();
        if (!@$dom->load($file)) {
            $this->out($depth, false, $this->rel($file) . ': XML mal formado');
            return;
        }
        $this->out($depth, true, $this->rel($file));
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $moduleOfFile = $this->moduleOf($file);
        foreach ($xp->query('//update[@handle]') as $u) {
            $this->out($depth + 1, true, 'inclui o handle ' . $u->getAttribute('handle') . " (linha {$u->getLineNo()})");
        }
        foreach ($xp->query('//block | //referenceBlock[@template]') as $b) {
            $class = ltrim($b->getAttribute('class'), '\\');
            $name = $b->getAttribute('name');
            if ($class !== '' && !$this->index->exists($class)) {
                $this->out($depth + 1, false, "bloco $name: classe $class nao existe (linha {$b->getLineNo()})");
            }
            foreach ($xp->query('./arguments/argument[@xsi:type="object"]', $b) ?: [] as $arg) {
                $vm = ltrim(trim($arg->textContent), '\\');
                if (!$this->index->exists($vm) && !$this->index->isVirtualType($vm)) {
                    $this->out($depth + 1, false, "bloco $name: argumento {$arg->getAttribute('name')} aponta para $vm, que nao existe");
                    continue;
                }
                // JS montado em PHP (ViewModel): os ids de componente ficam como strings na classe
                $vmFile = $this->index->file($vm);
                $vmCode = $vmFile ? (string)file_get_contents($vmFile) : '';
                preg_match_all("#'([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+/js/[A-Za-z0-9_/.-]+|[a-z][A-Za-z0-9]+)'\\s*=>#", $vmCode, $jm);
                preg_match_all("#'component'\\s*=>\\s*'([^']+)'#", $vmCode, $cm);
                if ($vmCode !== '') {
                    foreach (array_unique(array_merge($jm[1], $cm[1])) as $id) {
                        if (str_contains($id, '/') || isset($this->requireMap()[$id])) {
                            $this->jsModule($id, $depth + 2);
                        }
                    }
                }
            }
            $template = $b->getAttribute('template');
            if ($template !== '') {
                $this->template($template, $moduleOfFile, $area, $depth + 1, "bloco $name (linha {$b->getLineNo()})");
            }
        }
        foreach ($xp->query('//uiComponent[@name]') as $ui) {
            $this->uiComponent($ui->getAttribute('name'), $area, $depth + 1);
        }
    }

    private function moduleOf(string $file): string
    {
        foreach ($this->modules() as $module => $dir) {
            if (str_starts_with($file, rtrim($dir, '/') . '/')) {
                return $module;
            }
        }
        return preg_match('#/([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+)/layout/#', $file, $m) ? $m[1] : '';
    }

    private function template(string $template, string $defaultModule, string $area, int $depth, string $owner): void
    {
        [$module, $path] = str_contains($template, '::') ? explode('::', $template, 2) : [$defaultModule, $template];
        $dir = $this->modules()[$module] ?? null;
        $candidates = $dir ? ["$dir/view/$area/templates/$path", "$dir/view/base/templates/$path"] : [];
        foreach ($this->themeDirs($area) as $theme) {
            $candidates[] = "$theme/$module/templates/$path";
        }
        $file = null;
        foreach ($candidates as $c) {
            if (is_file($c)) {
                $file = $c;
                break;
            }
        }
        if (!$file) {
            $this->out($depth, false, "$owner: template $module::$path nao existe");
            return;
        }
        $this->out($depth, true, "$owner: template " . $this->rel($file));
        if (isset($this->seenTemplates[$file])) {
            return;
        }
        $this->seenTemplates[$file] = true;
        $this->scripts((string)file_get_contents($file), $depth + 1);
    }

    /** Componentes JS iniciados pelo template: x-magento-init e data-mage-init. */
    private function scripts(string $content, int $depth): void
    {
        $components = [];
        if (preg_match_all('#<script type="text/x-magento-init">(.*?)</script>#s', $content, $m)) {
            foreach ($m[1] as $json) {
                if (preg_match_all('#"([A-Za-z0-9_]+/[A-Za-z0-9_/.-]+|[a-z][A-Za-z0-9]+)"\s*:\s*\{#', $json, $c)) {
                    $components = array_merge($components, $c[1]);
                }
            }
        }
        if (preg_match_all('#data-mage-init=\'\{\s*"([^"]+)"#', $content, $m)) {
            $components = array_merge($components, $m[1]);
        }
        if (preg_match_all('#"component"\s*:\s*"([^"]+)"#', $content, $m)) {
            $components = array_merge($components, $m[1]);
        }
        foreach (array_unique($components) as $component) {
            if ($component === '*' || str_starts_with($component, '#') || str_starts_with($component, '.')) {
                continue;
            }
            $this->jsModule($component, $depth);
        }
        if (str_contains($content, 'getViewModel') || str_contains($content, "getData('view_model')")) {
            $this->out($depth, true, 'usa ViewModel (argumento view_model do layout)');
        }
    }

    private function jsModule(string $id, int $depth): void
    {
        $target = $this->requireMap()[$id] ?? $id;
        $file = $this->js->jsFile($target);
        if ($file === null) {
            $this->out($depth, true, "JS $id (alias sem caminho de modulo; nao conferido)");
        } elseif ($file === '') {
            $this->out($depth, false, "JS $id" . ($target !== $id ? " -> $target" : '') . ': arquivo nao existe');
        } else {
            $this->out($depth, true, "JS $id" . ($target !== $id ? " (map -> $target)" : '') . ' -> ' . $this->rel($file));
        }
    }

    /** @return array<string,string> aliases dos requirejs-config.js (map '*') de todos os módulos */
    private function requireMap(): array
    {
        if ($this->requireMap !== null) {
            return $this->requireMap;
        }
        $this->requireMap = [];
        foreach ($this->modules() as $dir) {
            foreach (glob("$dir/view/{frontend,adminhtml,base}/requirejs-config.js", GLOB_BRACE) ?: [] as $f) {
                if (preg_match_all('#[\'"]?([A-Za-z0-9_/-]+)[\'"]?\s*:\s*[\'"]([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+/[^\'"]+)[\'"]#', (string)file_get_contents($f), $m, PREG_SET_ORDER)) {
                    foreach ($m as $pair) {
                        $this->requireMap[$pair[1]] ??= $pair[2];
                    }
                }
            }
        }
        return $this->requireMap;
    }

    public function uiComponent(string $name, string $area, int $depth): void
    {
        if (isset($this->seenUi[$name])) {
            return;
        }
        $this->seenUi[$name] = true;
        $file = null;
        foreach ($this->modules() as $dir) {
            foreach (["$dir/view/$area/ui_component/$name.xml", "$dir/view/base/ui_component/$name.xml"] as $f) {
                if (is_file($f) && (!$file || str_contains(file_get_contents($f), '<dataSource'))) {
                    $file = $f;
                }
            }
        }
        if (!$file) {
            $this->out($depth, false, "uiComponent $name: nenhum ui_component/$name.xml");
            return;
        }
        $dom = new \DOMDocument();
        if (!@$dom->load($file)) {
            $this->out($depth, false, "uiComponent $name: " . $this->rel($file) . ' mal formado');
            return;
        }
        $this->out($depth, true, "uiComponent $name -> " . $this->rel($file));
        $xp = new \DOMXPath($dom);
        foreach ($xp->query('//dataSource') as $ds) {
            $component = $ds->getAttribute('component');
            if ($component === '') {
                $node = $xp->query('.//item[@name="component"]', $ds)->item(0);
                $component = $node ? trim($node->textContent) : '';
            }
            $this->out($depth + 1, $component !== '', 'dataSource ' . $ds->getAttribute('name')
                . ($component !== '' ? " (componente $component)" : ': sem componente JS (spinner infinito)'));
            foreach ($xp->query('.//dataProvider[@class] | .//argument[@name="class"]', $ds) as $dp) {
                $class = ltrim($dp instanceof \DOMElement && $dp->hasAttribute('class') ? $dp->getAttribute('class') : trim($dp->textContent), '\\');
                $ok = $this->index->exists($class) || $this->index->isVirtualType($class);
                $this->out($depth + 2, $ok, "DataProvider $class" . ($ok ? '' : ': nao existe'));
            }
        }
        if ($dom->documentElement->nodeName === 'form' && $dom->documentElement->getAttribute('extends') === ''
            && $xp->query('/form/argument[@name="data"]/item[@name="template"] | /form/settings/layout')->length === 0) {
            $this->out($depth + 1, false, 'form sem template nem layout: o spinner da area de abas nunca some');
        }
        foreach ($xp->query('//@class') as $attr) {
            $class = ltrim($attr->value, '\\');
            if (str_contains($class, '\\') && $attr->ownerElement->nodeName !== 'dataProvider'
                && !$this->index->exists($class) && !$this->index->isVirtualType($class)) {
                $this->out($depth + 1, false, $attr->ownerElement->nodeName . " $class nao existe (linha {$attr->ownerElement->getLineNo()})");
            }
        }
    }

    private static function studly(string $s): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $s)));
    }
}
