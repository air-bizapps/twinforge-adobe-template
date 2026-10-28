<?php
/**
 * Índice de classes sem executar código: existência pelo mapa do Composer (ou pelo app/code do
 * worktree), virtualTypes da config DI e sugestão "você quis dizer" para nome inventado.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class ClassIndex
{
    /** @var array<string,string> virtualType => arquivo onde foi declarado */
    private ?array $virtual = null;
    /** @var string[]|null */
    private ?array $classes = null;
    /** @var array<string,bool> */
    private array $memo = [];

    public function __construct(private Env $env)
    {
    }

    public function exists(string $name): bool
    {
        $name = ltrim(trim($name), '\\');
        if ($name === '') {
            return false;
        }
        if (isset($this->memo[$name])) {
            return $this->memo[$name];
        }
        $this->memo[$name] = false; // evita recursão em sufixos gerados
        return $this->memo[$name] = $this->resolve($name);
    }

    private function resolve(string $name): bool
    {
        if (class_exists($name, false) || interface_exists($name, false)
            || trait_exists($name, false) || enum_exists($name, false)) {
            return true;
        }
        $parts = explode('\\', $name);
        if (count($parts) >= 2 && is_dir($this->env->codeRoot . '/' . $parts[0] . '/' . $parts[1])) {
            // módulo do app/code: o worktree é a verdade (classe apagada aqui não conta)
            if (is_file($this->env->codeRoot . '/' . implode('/', $parts) . '.php')) {
                return true;
            }
        } elseif ($this->env->loader->findFile($name) !== false) {
            return true;
        }
        // classes que o Magento gera (setup:di:compile ou em modo developer)
        foreach (['/^(.+)Factory$/' => '$1', '/^(.+)\\\\(?:Proxy|Interceptor)$/' => '$1',
                     '/^(.+)ExtensionInterface$/' => '$1Interface', '/^(.+)Extension$/' => '$1Interface'] as $re => $to) {
            if (preg_match($re, $name)) {
                $origin = preg_replace($re, $to, $name);
                if ($origin !== $name && $this->exists($origin)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Caminho do arquivo da classe (app/code do worktree primeiro), ou null. */
    public function file(string $name): ?string
    {
        $name = ltrim($name, '\\');
        $local = $this->env->codeRoot . '/' . str_replace('\\', '/', $name) . '.php';
        if (is_file($local)) {
            return $local;
        }
        $f = $this->env->loader->findFile($name);
        return $f === false ? null : (realpath($f) ?: $f);
    }

    public function isVirtualType(string $name): bool
    {
        return isset($this->virtualTypes()[ltrim($name, '\\')]);
    }

    /** @return array<string,string> */
    public function virtualTypes(): array
    {
        if ($this->virtual !== null) {
            return $this->virtual;
        }
        $cache = $this->env->cacheDir() . '/virtualtypes-' . $this->env->vendorKey() . '.json';
        $vendor = is_file($cache) ? (array)json_decode((string)file_get_contents($cache), true) : null;
        if ($vendor === null) {
            $vendor = [];
            $v = $this->env->root . '/vendor';
            foreach (array_merge(glob("$v/*/*/etc/di.xml") ?: [], glob("$v/*/*/etc/*/di.xml") ?: [],
                glob("$v/*/*/src/etc/di.xml") ?: [], glob("$v/*/*/src/etc/*/di.xml") ?: []) as $f) {
                $vendor += $this->scanVirtual($f, 'vendor/' . substr($f, strlen($v) + 1));
            }
            @file_put_contents($cache, json_encode($vendor));
        }
        $local = $this->scanVirtual($this->env->root . '/app/etc/di.xml', 'app/etc/di.xml');
        $c = $this->env->codeRoot;
        foreach (array_merge(glob("$c/*/*/etc/di.xml") ?: [], glob("$c/*/*/etc/*/di.xml") ?: []) as $f) {
            $local += $this->scanVirtual($f, 'app/code/' . substr($f, strlen($c) + 1));
        }
        return $this->virtual = $local + $vendor;
    }

    /** @return array<string,string> */
    private function scanVirtual(string $file, string $rel): array
    {
        $out = [];
        if (preg_match_all('/<virtualType\s[^>]*name="\\\\?([^"]+)"/', (string)@file_get_contents($file), $m)) {
            foreach ($m[1] as $n) {
                $out[$n] = $rel;
            }
        }
        return $out;
    }

    /**
     * Sugestões para um nome que não existe: compara as palavras do nome (namespace e CamelCase)
     * com as classes do vendor/magento e do app/code. A palavra final pesa em dobro.
     *
     * @return string[] até 2 nomes
     */
    public function suggest(string $name): array
    {
        $want = self::tokens($name, true);
        if (!$want) {
            return [];
        }
        $wantTotal = array_sum($want);
        $scored = [];
        foreach ($this->classes() as $cand) {
            $tok = self::tokens($cand, false);
            $common = 0.0;
            foreach ($want as $t => $w) {
                if (isset($tok[$t])) {
                    $common += $w;
                }
            }
            if ($common < 2) {
                continue;
            }
            $extra = count(array_diff_key($tok, $want));
            $score = $common / ($wantTotal + $extra);
            if ($score >= 0.5) {
                $scored[$cand] = $score;
            }
        }
        arsort($scored);
        $top = array_slice($scored, 0, 2, true);
        $keys = array_keys($top);
        if (count($keys) === 2 && $top[$keys[0]] - $top[$keys[1]] > 0.1) {
            array_pop($keys);
        }
        return $keys;
    }

    /** @return array<string,float> */
    private static function tokens(string $fqcn, bool $weighted): array
    {
        $segments = array_values(array_filter(explode('\\', ltrim($fqcn, '\\'))));
        $out = [];
        foreach ($segments as $i => $seg) {
            $last = $i === count($segments) - 1;
            foreach (preg_split('/(?<=[a-z0-9])(?=[A-Z])|_/', $seg) ?: [] as $t) {
                $t = strtolower($t);
                if ($t === '' || $t === 'interface') {
                    continue;
                }
                $w = ($weighted && $last) ? 2.0 : 1.0;
                $out[$t] = max($out[$t] ?? 0, $w);
            }
        }
        return $out;
    }

    /** @return string[] classes do vendor/magento (cache por versão do vendor) e do app/code */
    public function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }
        $cache = $this->env->cacheDir() . '/magento-classes-' . $this->env->vendorKey() . '.txt';
        if (is_file($cache)) {
            $vendor = explode("\n", (string)file_get_contents($cache));
        } else {
            $vendor = [];
            foreach (glob($this->env->root . '/vendor/magento/*/composer.json') ?: [] as $cj) {
                $psr4 = (array)(json_decode((string)file_get_contents($cj), true)['autoload']['psr-4'] ?? []);
                foreach ($psr4 as $prefix => $dirs) {
                    foreach ((array)$dirs as $d) {
                        $vendor = array_merge($vendor, self::walk(dirname($cj) . '/' . trim($d, '/'), (string)$prefix));
                    }
                }
            }
            @file_put_contents($cache, implode("\n", $vendor));
        }
        $local = [];
        foreach (glob($this->env->codeRoot . '/*/*', GLOB_ONLYDIR) ?: [] as $mod) {
            $prefix = basename(dirname($mod)) . '\\' . basename($mod) . '\\';
            $local = array_merge($local, self::walk($mod, $prefix));
        }
        return $this->classes = array_values(array_unique(array_merge($local, array_filter($vendor))));
    }

    /** @return string[] */
    private static function walk(string $dir, string $prefix): array
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            static fn(\SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), ['Test', 'Tests', 'tests', 'dev', 'view', 'i18n'], true))
        ));
        foreach ($it as $f) {
            if ($f->getExtension() === 'php' && preg_match('/^[A-Z]/', $f->getFilename())) {
                $rel = substr($f->getPathname(), strlen($dir) + 1, -4);
                $out[] = rtrim($prefix, '\\') . '\\' . str_replace('/', '\\', $rel);
            }
        }
        return $out;
    }

    /** Diretório de um módulo (Vendor_Module): app/code do worktree ou vendor. */
    public function moduleDir(string $module): ?string
    {
        if (!preg_match('/^([A-Za-z0-9]+)_([A-Za-z0-9]+)$/', $module, $m)) {
            return null;
        }
        $local = $this->env->codeRoot . '/' . $m[1] . '/' . $m[2];
        if (is_dir($local)) {
            return $local;
        }
        if (class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
            $p = (new \Magento\Framework\Component\ComponentRegistrar())
                ->getPath(\Magento\Framework\Component\ComponentRegistrar::MODULE, $module);
            if ($p) {
                return $p;
            }
        }
        return null;
    }
}
