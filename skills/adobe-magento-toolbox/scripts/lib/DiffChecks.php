<?php
/**
 * Checagens sobre o diff em si:
 *  - requirejs-config.js: todo destino de map/paths/mixins existe como arquivo;
 *  - classe apagada ou movida ainda citada em outro arquivo do app/code;
 *  - anti-trapaça: silenciar erro, pular teste, apagar teste ou mexer nos arquivos que definem a
 *    própria checagem (baseline, quarentena, CI, ferramentas) reprova. Passar "no grito" não vale.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class DiffChecks
{
    /** arquivos que o agente não muda: são a régua, não o código */
    private const PROTECTED = '#^(\.github/|phpstan[^/]*\.neon|dev/tests/unit/(phpunit\.xml|quarantine)|grumphp\.yml|tools/|app/etc/di\.xml|\.verify/)#';
    /** só o que silencia o que esta verificação roda (PHPStan, testes); phpcs não é régua aqui */
    private const SUPPRESS = '/@phpstan-ignore|markTestSkipped\s*\(|markTestIncomplete\s*\(/';

    /** nomes do módulo de referência da skill: em app/code, só renomeados */
    private const REFERENCE = '/Acme\\\\Reference|Acme_Reference|acme_reference/';

    private bool $allowReference = false;

    public function __construct(private Env $env, private ClassIndex $index, private Report $report, private JsResolver $js)
    {
    }

    private function add(string $id, string $cls, string $file, int $line, string $msg, string $hint, bool $always = true, string $sev = 'error'): void
    {
        $f = new Finding($id, $cls, $sev, $file, $line, $msg, $hint);
        $f->preexisting = !$always && !$this->env->isAdded($file, $line);
        $this->report->add($f);
    }

    public function run(bool $allowProtected = false, bool $allowReference = false): void
    {
        $this->allowReference = $allowReference;
        foreach ($this->env->present('#requirejs-config\.js$#') as $rel) {
            $this->requirejs($rel);
        }
        $this->removedClasses();
        $this->cheats($allowProtected);
    }

    private function requirejs(string $rel): void
    {
        $this->report->count('requirejs-config');
        $lines = explode("\n", $this->env->read($rel));
        foreach ($lines as $i => $l) {
            if (!preg_match_all('#[\'"]([A-Z][A-Za-z0-9]*_[A-Za-z0-9]+/[^\'"]+)[\'"]#', $l, $m)) {
                continue;
            }
            foreach ($m[1] as $id) {
                if ($this->js->jsFile($id) === '') {
                    $this->add('JS-REQUIREJS-TARGET', 'C7', $rel, $i + 1,
                        "requirejs-config aponta para $id, que nao existe como arquivo JS.",
                        'Remova a entrada (o caminho padrao Vendor_Module/js/x ja resolve sozinho) ou corrija o destino.', false);
                }
            }
        }
    }

    /** Classe apagada (ou movida de módulo) que outro arquivo do app/code ainda cita. */
    private function removedClasses(): void
    {
        foreach ($this->env->files as $rel => $status) {
            if ($status !== 'D' || !preg_match('#^app/code/(.+)\.php$#', $rel, $m) || str_contains($rel, '/Test/')) {
                continue;
            }
            $fqcn = str_replace('/', '\\', $m[1]);
            if ($this->index->exists($fqcn)) {
                continue;
            }
            $out = (string)shell_exec('grep -rnF --include=*.php --include=*.xml --include=*.phtml '
                . escapeshellarg($fqcn) . ' ' . escapeshellarg($this->env->codeRoot) . ' 2>/dev/null | head -5');
            foreach (array_filter(explode("\n", $out)) as $hit) {
                if (!preg_match('/^(.+?):(\d+):/', $hit, $h)) {
                    continue;
                }
                $file = 'app/code/' . substr($h[1], strlen($this->env->codeRoot) + 1);
                $this->add('CLASS-REMOVED-REFERENCED', 'C1', $file, (int)$h[2],
                    "$fqcn foi apagada (ou movida) nesta branch, mas este arquivo ainda cita a classe.",
                    'Atualize a referencia para o novo nome/modulo ou apague o arquivo orfao.');
            }
        }
    }

    private function cheats(bool $allowProtected): void
    {
        foreach ($this->env->files as $rel => $status) {
            if (!$allowProtected && preg_match(self::PROTECTED, $rel)) {
                $this->add('CHEAT-PROTECTED', 'C11', $rel, 0,
                    'Arquivo da regua da verificacao alterado (baseline, quarentena, CI ou ferramenta).',
                    'Desfaca a mudanca: o codigo tem que passar na regua, nao o contrario. Se a regua estiver errada, blocked explicando.');
            }
            if ($status === 'D' && preg_match('#/Test/#', $rel)) {
                $this->add('CHEAT-TEST-DELETED', 'C11', $rel, 0, 'Teste apagado.',
                    'Restaure o teste e corrija o codigo; se o teste ficou obsoleto pela regra nova, blocked explicando qual.');
            }
            if ($status === 'D' || !preg_match('#\.(php|phtml|xml|js)$#', $rel)) {
                continue;
            }
            $copied = false;
            foreach (explode("\n", $this->env->read($rel)) as $i => $l) {
                if (!$this->allowReference && !$copied && str_starts_with($rel, 'app/code/')
                    && preg_match(self::REFERENCE, $l) && $this->env->isAdded($rel, $i + 1)) {
                    $copied = true;
                    $this->add('REFERENCE-NOT-RENAMED', 'C11', $rel, $i + 1,
                        'Codigo copiado do modulo de referencia sem trocar o nome (Acme\\Reference, Acme_Reference ou acme_reference).',
                        'Copie so a forma do padrao e use o namespace, o nome do modulo e os ids do seu modulo.');
                }
                if (preg_match(self::SUPPRESS, $l, $m) && $this->env->isAdded($rel, $i + 1)) {
                    $this->add('CHEAT-SUPPRESS', 'C11', $rel, $i + 1, "Silenciador de checagem adicionado ({$m[0]}).",
                        'Remova e corrija a causa; silenciar erro ou pular teste reprova a verificacao.');
                }
            }
        }
    }
}
