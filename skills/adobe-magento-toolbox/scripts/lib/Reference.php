<?php
/**
 * Fonte do módulo de referência para o magento-example: os arquivos de reference/ (repo, laptop) ou o
 * pacote reference/bundle-N.json, que é o que vai para a instância. Publicar os 90+ arquivos do módulo um
 * a um custa horas na plataforma (uma versão da skill por arquivo); o pacote são poucos arquivos, cada
 * um abaixo de ~70 KB para caber num argumento do twinforgecli (o Linux corta argumento em 128 KB, e o
 * payload escapa o conteúdo de novo).
 *
 * O pacote é gerado com `php magento-example.php --build-bundle` e o --self-test reprova pacote
 * desatualizado onde o módulo existe inteiro em disco (o repo).
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class Reference
{
    public const BUNDLE_GLOB = 'bundle-*.json';
    private const CHUNK_BYTES = 70000;

    /** @param array<string,string>|null $files caminho relativo a reference/ => conteúdo (null = ler do disco) */
    private function __construct(private string $root, private ?array $files)
    {
    }

    /** Pacote quando existe (os pedaços juntos são sempre o módulo inteiro); senão os arquivos em disco. */
    public static function open(string $root): self
    {
        $files = null;
        foreach (self::chunkFiles($root) as $chunk) {
            $data = json_decode((string)file_get_contents($chunk), true);
            if (is_array($data['files'] ?? null)) {
                $files = ($files ?? []) + $data['files'];
            }
        }
        return new self($root, $files);
    }

    /** @return string[] */
    private static function chunkFiles(string $root): array
    {
        $chunks = glob($root . '/' . self::BUNDLE_GLOB) ?: [];
        natsort($chunks);
        return array_values($chunks);
    }

    public function read(string $rel): ?string
    {
        $rel = ltrim($rel, '/');
        if ($this->files !== null) {
            return $this->files[$rel] ?? null;
        }
        $path = realpath($this->root . '/' . $rel);
        $inside = $path && str_starts_with($path, (string)realpath($this->root) . '/');
        return $inside && is_file($path) ? (string)file_get_contents($path) : null;
    }

    /** @return array<string,mixed> o patterns.json (vazio onde o módulo não foi publicado) */
    public function catalog(): array
    {
        return (array)json_decode((string)$this->read('patterns.json'), true);
    }

    /** O módulo inteiro está em disco (repo): só aí o pacote pode ser gerado e conferido. */
    public static function hasSources(string $root): bool
    {
        return is_file($root . '/patterns.json') && is_dir($root . '/Acme/Reference');
    }

    /** @return array<string,string> arquivos de reference/ em disco, menos o próprio pacote */
    public static function sources(string $root): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isFile() && !fnmatch(self::BUNDLE_GLOB, $rel)) {
                $files[$rel] = (string)file_get_contents($file->getPathname());
            }
        }
        ksort($files);
        return $files;
    }

    /** @return array<string,string> nome do pedaço => conteúdo, na ordem dos caminhos */
    public static function build(string $root): array
    {
        $groups = [[]];
        $size = 0;
        foreach (self::sources($root) as $rel => $content) {
            $entry = strlen((string)json_encode([$rel => $content], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if ($size > 0 && $size + $entry > self::CHUNK_BYTES) {
                [$groups[], $size] = [[], 0];
            }
            $groups[count($groups) - 1][$rel] = $content;
            $size += $entry;
        }
        $chunks = [];
        foreach ($groups as $i => $files) {
            $chunk = ['note' => 'Gerado por magento-example.php --build-bundle a partir de reference/: nao edite a mao.',
                'files' => $files];
            $chunks['bundle-' . ($i + 1) . '.json'] = json_encode($chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
        return $chunks;
    }

    /** Regrava o pacote: apaga os pedaços antigos e escreve os novos. */
    public static function write(string $root): int
    {
        foreach (self::chunkFiles($root) as $old) {
            unlink($old);
        }
        $chunks = self::build($root);
        foreach ($chunks as $name => $content) {
            file_put_contents($root . '/' . $name, $content);
        }
        return count($chunks);
    }

    /** Os pedaços em disco são exatamente o que build() geraria agora. */
    public static function bundleIsCurrent(string $root): bool
    {
        $onDisk = [];
        foreach (self::chunkFiles($root) as $chunk) {
            $onDisk[basename($chunk)] = (string)file_get_contents($chunk);
        }
        return $onDisk === self::build($root);
    }
}
