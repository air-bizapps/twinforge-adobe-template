<?php
/**
 * Ambiente da checagem: raiz Magento, autoload do vendor, arquivos alterados e linhas adicionadas.
 *
 * O código da branch pode estar num worktree cujo vendor/ é link para o de outro checkout. O
 * autoload do Composer resolve app/code pelo checkout dono do vendor, então um carregador para o
 * app/code deste worktree entra na frente (senão a checagem veria as classes do outro checkout).
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class Env
{
    public string $root;
    public string $codeRoot;
    /** @var \Composer\Autoload\ClassLoader */
    public $loader;
    public string $base = '';
    public string $mergeBase = '';
    public string $head = '';
    public string $branch = '';
    /** @var array<string,string> caminho relativo => status git (A, M, D) */
    public array $files = [];
    /** @var array<string,array<int,true>|null> linhas adicionadas por arquivo; null = arquivo todo */
    public array $added = [];
    public string $outDir;

    public function __construct(string $root, ?string $codeRoot = null)
    {
        $this->root = rtrim(realpath($root) ?: $root, '/');
        $code = $codeRoot ?? $this->root . '/app/code';
        $this->codeRoot = rtrim(realpath($code) ?: $code, '/');
        $this->outDir = $this->root . '/var/verify';
    }

    public static function findRoot(string $start): ?string
    {
        $dir = realpath($start) ?: $start;
        while ($dir !== '/' && $dir !== '') {
            if (is_file($dir . '/app/bootstrap.php') && is_dir($dir . '/vendor')) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        return null;
    }

    /** Carrega o vendor/autoload.php e põe o app/code deste worktree na frente. */
    public function boot(): void
    {
        $autoload = $this->root . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException("vendor/autoload.php nao encontrado em {$this->root}: rode composer install");
        }
        $this->loader = require $autoload;
        $codeRoot = $this->codeRoot;
        spl_autoload_register(static function (string $class) use ($codeRoot): void {
            $file = $codeRoot . '/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }, true, true);
    }

    /**
     * O autoload do vendor registra os módulos do checkout dono do vendor. Para subir o Magento
     * neste worktree, os módulos do app/code daqui passam a apontar para cá.
     */
    public function registerLocalModules(): void
    {
        $prop = new \ReflectionProperty(\Magento\Framework\Component\ComponentRegistrar::class, 'paths');
        $paths = $prop->getValue();
        foreach (glob($this->codeRoot . '/*/*/registration.php') ?: [] as $reg) {
            if (preg_match("/ComponentRegistrar::MODULE,\s*'([^']+)'/", (string)file_get_contents($reg), $m)) {
                $paths[\Magento\Framework\Component\ComponentRegistrar::MODULE][$m[1]] = dirname($reg);
            }
        }
        $prop->setValue(null, $paths);
    }

    /** Caminho absoluto de um arquivo relativo à raiz (app/code/... vem do codeRoot). */
    public function abs(string $rel): string
    {
        if (str_starts_with($rel, 'app/code/')) {
            return $this->codeRoot . '/' . substr($rel, 9);
        }
        return $this->root . '/' . $rel;
    }

    public function read(string $rel): string
    {
        return (string)@file_get_contents($this->abs($rel));
    }

    public function git(string $args): string
    {
        return trim((string)shell_exec('git -C ' . escapeshellarg($this->root) . ' ' . $args . ' 2>/dev/null'));
    }

    /**
     * Arquivos alterados desde o ponto em que a branch saiu da base: diff do working tree contra o
     * merge-base (pega commitado e não commitado) mais os arquivos novos ainda não rastreados.
     */
    public function collectChanges(string $base): void
    {
        $this->base = $base;
        $this->mergeBase = $this->git('merge-base ' . escapeshellarg($base) . ' HEAD');
        if ($this->mergeBase === '') {
            throw new \RuntimeException("nao achei o merge-base com $base (rode git fetch ou passe --base)");
        }
        $this->head = $this->git('rev-parse --short HEAD');
        $this->branch = $this->git('rev-parse --abbrev-ref HEAD');
        $mb = escapeshellarg($this->mergeBase);
        foreach (explode("\n", $this->git("diff --name-status --no-renames $mb")) as $line) {
            if (preg_match('/^([AMD])\t(.+)$/', $line, $m)) {
                $this->files[$m[2]] = $m[1];
            }
        }
        foreach (explode("\n", $this->git('ls-files -o --exclude-standard')) as $f) {
            if ($f !== '' && !str_starts_with($f, 'var/')) {
                $this->files[$f] = 'A';
            }
        }
        foreach ($this->files as $f => $status) {
            if ($status === 'A') {
                $this->added[$f] = null;
            } elseif ($status === 'M') {
                $this->added[$f] = $this->addedLines($mb, $f);
            }
        }
    }

    /** @return array<int,true> */
    private function addedLines(string $mb, string $file): array
    {
        $lines = [];
        $diff = $this->git("diff -U0 $mb -- " . escapeshellarg($file));
        foreach (explode("\n", $diff) as $l) {
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $l, $m)) {
                $start = (int)$m[1];
                $count = isset($m[2]) ? (int)$m[2] : 1;
                for ($i = $start; $i < $start + $count; $i++) {
                    $lines[$i] = true;
                }
            }
        }
        return $lines;
    }

    /** Para a autochecagem: todo arquivo do diretório conta como novo. */
    public function useAllFiles(string $dir, string $prefix): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = $prefix . substr($f->getPathname(), strlen($dir) + 1);
            $this->files[$rel] = 'A';
            $this->added[$rel] = null;
        }
        ksort($this->files);
    }

    public function isAdded(string $file, int $line): bool
    {
        if (!array_key_exists($file, $this->added)) {
            return false;
        }
        $set = $this->added[$file];
        return $set === null || isset($set[$line]);
    }

    /** @return string[] arquivos adicionados ou alterados (existem no disco) */
    public function present(string $pattern = ''): array
    {
        $out = [];
        foreach ($this->files as $f => $s) {
            if ($s !== 'D' && ($pattern === '' || preg_match($pattern, $f))) {
                $out[] = $f;
            }
        }
        return $out;
    }

    public function cacheDir(): string
    {
        $base = getenv('XDG_CACHE_HOME') ?: ((getenv('HOME') ?: sys_get_temp_dir()) . '/.cache');
        $dir = $base . '/adobe-magento-toolbox';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Chave do cache: muda quando o vendor muda. */
    public function vendorKey(): string
    {
        $lock = $this->root . '/composer.lock';
        $vendor = realpath($this->root . '/vendor') ?: $this->root . '/vendor';
        return substr(sha1($vendor . '|' . (is_file($lock) ? sha1_file($lock) : '') . '|'
            . (string)@filemtime($vendor . '/composer/installed.json')), 0, 16);
    }
}
