<?php
/**
 * Autochecagem das regras: cada pasta em tests/fixtures é um app/code mínimo com um case.json
 * dizendo quais IDs têm que aparecer (expect) e quais não podem aparecer (forbid; "*" = nenhum erro).
 * Regra nova só entra com um caso ruim e um bom aqui.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class SelfTest
{
    public static function run(?string $root, string $dir): int
    {
        if (!$root) {
            fwrite(STDERR, "A autochecagem usa o vendor de um projeto Magento: rode na raiz de um (ou passe --root).\n");
            return 2;
        }
        $boot = new Env($root);
        $boot->boot();
        require_once __DIR__ . '/PhpChecks.php';
        require_once __DIR__ . '/XmlChecks.php';
        require_once __DIR__ . '/UiRules.php';
        require_once __DIR__ . '/WebapiRules.php';
        require_once __DIR__ . '/DiffChecks.php';

        $failed = 0;
        $cases = glob(rtrim($dir, '/') . '/*/case.json') ?: [];
        foreach ($cases as $caseFile) {
            $caseDir = dirname($caseFile);
            $case = json_decode((string)file_get_contents($caseFile), true);
            $env = new Env($root, $caseDir . '/app/code');
            $env->loader = $boot->loader;
            $env->branch = 'self-test';
            $env->useAllFiles($caseDir . '/app/code', 'app/code/');
            foreach ($case['deleted'] ?? [] as $d) {
                $env->files[$d] = 'D';
            }
            foreach ($case['changed'] ?? [] as $c) {
                $env->files[$c] = 'M';
                $env->added[$c] = null;
            }
            $report = Checks::run($env);
            $ids = array_map(fn(Finding $f) => $f->id, array_filter($report->findings, fn(Finding $f) => !$f->preexisting));
            $errorIds = array_map(fn(Finding $f) => $f->id, $report->errors());
            $problems = [];
            foreach ($case['expect'] ?? [] as $id) {
                if (!in_array($id, $ids, true)) {
                    $problems[] = "esperava $id";
                }
            }
            foreach ($case['forbid'] ?? [] as $id) {
                if ($id === '*' && $errorIds) {
                    $problems[] = 'nao esperava erro, veio ' . implode(', ', array_unique($errorIds));
                } elseif ($id !== '*' && in_array($id, $ids, true)) {
                    $problems[] = "nao esperava $id";
                }
            }
            $name = basename($caseDir);
            if ($problems) {
                $failed++;
                echo "FAIL $name: " . implode('; ', $problems) . "\n";
                foreach ($report->sorted() as $f) {
                    echo "     [{$f->id}] {$f->where()} {$f->message}\n";
                }
            } else {
                echo "ok   $name (" . implode(', ', array_unique($ids) ?: ['sem achados']) . ")\n";
            }
        }
        echo ($failed ? "$failed de " : 'todos os ') . count($cases) . ' casos ' . ($failed ? 'falharam' : 'passaram') . "\n";
        $failed += self::stageDecision();
        $failed += self::publishable(dirname($dir, 2));
        $failed += self::referenceBundle(dirname($dir, 2));
        return $failed ? 1 : 0;
    }

    /** O pacote do módulo de referência (o que vai para a instância) tem que bater com os arquivos do repo. */
    private static function referenceBundle(string $skillDir): int
    {
        require_once __DIR__ . '/Reference.php';
        $root = $skillDir . '/reference';
        if (!Reference::hasSources($root)) {
            echo "ok   pacote: instalacao so com o pacote do modulo de referencia\n";
            return 0;
        }
        $current = Reference::bundleIsCurrent($root);
        echo ($current ? 'ok   ' : 'FAIL ') . 'pacote: reference/bundle-*.json '
            . ($current ? 'bate com o modulo' : 'desatualizado: rode php scripts/magento-example.php --build-bundle') . "\n";
        return $current ? 0 : 1;
    }

    /** Máquina de estados do Verificador (StageDecision + Rounds): rodadas, repetição, teto, modos. */
    private static function stageDecision(): int
    {
        require_once __DIR__ . '/StageDecision.php';
        $err = fn(string $id, string $file, bool $pre = false) => ['id' => $id, 'severity' => 'error', 'file' => $file, 'preexisting' => $pre];
        $a = ['findings' => [$err('CLASS-MISSING', 'a.php')]];
        $b = ['findings' => [$err('WEBAPI-DOCBLOCK', 'b.php')]];
        $ok = ['findings' => [$err('CLASS-MISSING', 'old.php', true)]];
        $d = fn(?array $v, array $h, string $mode = 'enforce', int $exit = 1) => StageDecision::decide($v, $exit, "VERIFY FAIL (fast)\n1 [X] y", $h, 'T-1', $mode, 3, 'abc12345');
        $by = fn(string $agent, string $body) => ['authorAgentId' => $agent, 'body' => $body];

        $r1 = $d($a, []);
        $h1 = Rounds::fromComments([$by('dev', 'VERIFY FAIL r9/9 | T-1 | sha x | sig 000000000000'), $by('me', $r1->comment)], 'me');
        $h2 = Rounds::fromComments([$by('me', $r1->comment), $by('me', $d($b, $h1)->comment)], 'me');
        $reset = Rounds::fromComments([$by('me', $r1->comment), $by('me', $d(['findings' => []], $h1)->comment)], 'me');
        $cases = [
            'falha na r1 devolve ao dev' => $r1->action === StageDecision::CHANGES && str_starts_with($r1->comment, 'VERIFY FAIL r1/3 | T-1 | sha abc12345 | sig '),
            'rodada vem so dos comentarios do Verificador' => $h1['rounds'] === 1 && $h1['signatures'] === [$r1->signature],
            'mesma falha de novo escala' => $d($a, $h1)->action === StageDecision::ESCALATE,
            'falha diferente na r2 devolve' => $d($b, $h1)->action === StageDecision::CHANGES && $d($b, $h1)->round === 2,
            'falha na r3 escala (teto)' => $d($a, $h2)->action === StageDecision::ESCALATE && str_contains($d($a, $h2)->comment, 'ESCALATED: limite'),
            'erro preexistente nao reprova' => $d($ok, $h1)->action === StageDecision::APPROVE,
            'aprovacao comeca com o cabecalho de review' => str_starts_with($d(['findings' => []], [])->comment, "## Review: APPROVED\n\nVERIFY OK r1/3"),
            'aprovacao zera a contagem' => $reset['rounds'] === 0,
            'modo observe aprova mesmo com falha' => $d($a, $h2, 'observe')->action === StageDecision::APPROVE,
            'erro de ambiente nao conta rodada' => $d(null, $h1, 'enforce', 2)->action === StageDecision::INFRA && $d(null, $h1, 'enforce', 2)->round === 1,
            'worktree ausente escala' => StageDecision::unverifiable('sem worktree', [], 'T-1', 'enforce', 3)->action === StageDecision::ESCALATE,
            'worktree ausente em observacao so comenta' => StageDecision::unverifiable('sem worktree', [], 'T-1', 'observe', 3)->action === StageDecision::APPROVE
                && !str_contains(StageDecision::unverifiable('sem worktree', [], 'T-1', 'observe', 3)->comment, 'ESCALATED'),
        ];
        $failed = 0;
        foreach ($cases as $name => $pass) {
            echo ($pass ? 'ok   ' : 'FAIL ') . "estagio: $name\n";
            $failed += $pass ? 0 : 1;
        }
        return $failed;
    }

    /**
     * O WAF da instância barra o upload de arquivo de skill com cara de path traversal (ponto-ponto-barra).
     * Outros bloqueios dependem da pontuação somada do arquivo: a prova é twinforge/waf-probe.py.
     * tests/ não é publicada.
     */
    private static function publishable(string $skillDir): int
    {
        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skillDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($skillDir) + 1);
            if (str_starts_with($rel, 'tests/') || !$file->isFile()) {
                continue;
            }
            $text = (string)file_get_contents($file->getPathname());
            if (str_contains($text, '..' . '/')) {
                $bad[] = $rel;
            }
        }
        echo ($bad ? 'FAIL ' : 'ok   ') . 'publicavel: nenhum arquivo com padrao que o WAF barra'
            . ($bad ? ' (' . implode(', ', $bad) . ')' : '') . "\n";
        return $bad ? 1 : 0;
    }
}
