<?php
/**
 * Achados e relatório. A saída curta é o que o agente lê (e o que vai em comentário na TwinForge):
 * ASCII, sem "<" ">" (o WAF da instância bloqueia trechos de código), no máximo 5 itens em ordem
 * causal e um ID estável por regra, para a máquina de estados reconhecer falha repetida.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class Finding
{
    public bool $preexisting = false;

    public function __construct(
        public string $id,
        public string $cls,
        public string $severity,
        public string $file,
        public int $line,
        public string $message,
        public string $hint = '',
        public string $model = ''
    ) {
    }

    public function where(): string
    {
        return $this->line > 0 ? "{$this->file}:{$this->line}" : $this->file;
    }

    public function signature(): string
    {
        return $this->id . '|' . $this->file . '|' . $this->message;
    }
}

final class Report
{
    /** ordem causal: corrigir de cima para baixo costuma resolver o que vem depois */
    private const ORDER = ['CHEAT' => 0, 'PHP-SYNTAX' => 1, 'XML-MALFORMED' => 1, 'CLASS' => 2,
        'PHP-LOAD' => 3, 'PHP-PARENT' => 3, 'DI' => 4, 'XML-XSD' => 5, 'UI' => 6, 'JS' => 7,
        'WEBAPI' => 8, 'PHP' => 9];

    /** @var Finding[] */
    public array $findings = [];
    /** @var array<string,int> */
    public array $checked = [];

    public function add(Finding $f): void
    {
        foreach ($this->findings as $e) {
            if ($e->signature() === $f->signature() && $e->line === $f->line) {
                return;
            }
        }
        $this->findings[] = $f;
    }

    public function count(string $what, int $n = 1): void
    {
        $this->checked[$what] = ($this->checked[$what] ?? 0) + $n;
    }

    /** @return Finding[] */
    public function errors(): array
    {
        return array_values(array_filter($this->sorted(), fn(Finding $f) => $f->severity === 'error' && !$f->preexisting));
    }

    /** @return Finding[] */
    public function sorted(): array
    {
        $list = $this->findings;
        usort($list, function (Finding $a, Finding $b): int {
            return [$a->preexisting, $a->severity !== 'error', self::rank($a->id), $a->file, $a->line]
                <=> [$b->preexisting, $b->severity !== 'error', self::rank($b->id), $b->file, $b->line];
        });
        return $list;
    }

    private static function rank(string $id): int
    {
        foreach (self::ORDER as $prefix => $rank) {
            if (str_starts_with($id, $prefix)) {
                return $rank;
            }
        }
        return 10;
    }

    public static function oneLine(string $s): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $s));
    }

    public static function ascii(string $s): string
    {
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'Á' => 'A', 'É' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ú' => 'U', 'Ç' => 'C', 'Ã' => 'A', 'Õ' => 'O', '<' => '', '>' => '', '…' => '', '“' => '"',
            '”' => '"', '’' => "'", '—' => '-', '–' => '-']);
        return (string)preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $s);
    }

    /** Resumo para o agente: no máximo 5 erros, até 3.000 caracteres. */
    public function summary(Env $env, string $mode, string $extraHeader = ''): string
    {
        $errors = $this->errors();
        $all = $this->sorted();
        $pre = count(array_filter($all, fn(Finding $f) => $f->preexisting));
        $warn = count(array_filter($all, fn(Finding $f) => !$f->preexisting && $f->severity !== 'error'));
        $head = ($errors ? 'VERIFY FAIL' : 'VERIFY OK') . " ($mode)" . ($extraHeader !== '' ? " | $extraHeader" : '')
            . ' | ' . ($env->branch ?: '-') . ' | sha ' . ($env->head ?: '-');
        $lines = [$head];
        if (!$errors) {
            $lines[] = 'Conferidos: ' . $this->checkedLine() . '.';
            if ($warn) {
                $lines[] = "Avisos (nao reprovam, mas leia): $warn em var/verify/last.md";
            }
            return self::ascii(implode("\n", $lines));
        }
        $lines[] = 'Detalhe completo: var/verify/last.md (rode magento-verify --last)';
        $lines[] = 'Corrija SO estes, na ordem:';
        $budget = 3000 - strlen($head) - 400;
        $shown = 0;
        foreach ($errors as $i => $f) {
            $item = ($i + 1) . " [{$f->id}] {$f->where()}\n  " . self::oneLine($f->message)
                . ($f->hint !== '' ? "\n  {$f->hint}" : '') . ($f->model !== '' ? "\n  Modelo: {$f->model}" : '');
            $item = self::ascii($item);
            if (strlen($item) > 600) {
                $item = substr($item, 0, 597) . '...';
            }
            if ($shown === 5 || ($shown > 0 && strlen($item) > $budget)) {
                break;
            }
            $lines[] = $item;
            $budget -= strlen($item);
            $shown++;
        }
        if (count($errors) > $shown) {
            $lines[] = 'Mais ' . (count($errors) - $shown) . ' erro(s) depois destes, em var/verify/last.md.';
        }
        if ($pre) {
            $lines[] = "Ignorados (preexistentes, fora das linhas alteradas): $pre";
        }
        if ($warn) {
            $lines[] = "Avisos: $warn (var/verify/last.md)";
        }
        $lines[] = 'Fim: corrija, rode magento-verify de novo ate VERIFY OK e so entao marque done. '
            . 'Item impossivel de corrigir: blocked citando o ID e o arquivo.';
        return self::ascii(implode("\n", $lines));
    }

    private function checkedLine(): string
    {
        $parts = [];
        foreach ($this->checked as $k => $n) {
            $parts[] = "$n $k";
        }
        return $parts ? implode(', ', $parts) : 'nenhum arquivo alterado';
    }

    public function markdown(Env $env, string $mode): string
    {
        $out = ["# magento-verify ($mode)", '', "- branch: {$env->branch}", "- commit: {$env->head}",
            "- base: {$env->base} (merge-base " . substr($env->mergeBase, 0, 10) . ')',
            '- conferido: ' . $this->checkedLine(), ''];
        $groups = ['Erros' => fn(Finding $f) => $f->severity === 'error' && !$f->preexisting,
            'Avisos' => fn(Finding $f) => $f->severity !== 'error' && !$f->preexisting,
            'Preexistentes (fora das linhas alteradas)' => fn(Finding $f) => $f->preexisting];
        foreach ($groups as $title => $filter) {
            $list = array_values(array_filter($this->sorted(), $filter));
            if (!$list) {
                continue;
            }
            $out[] = "## $title (" . count($list) . ')';
            $out[] = '';
            foreach ($list as $i => $f) {
                $out[] = ($i + 1) . ". **[{$f->id}]** `{$f->where()}` ({$f->cls})";
                $out[] = '   ' . self::oneLine($f->message);
                if ($f->hint !== '') {
                    $out[] = "   {$f->hint}";
                }
                if ($f->model !== '') {
                    $out[] = "   Modelo: `{$f->model}`";
                }
            }
            $out[] = '';
        }
        return implode("\n", $out);
    }

    /** @return array<string,mixed> */
    public function toArray(Env $env, string $mode): array
    {
        return ['mode' => $mode, 'branch' => $env->branch, 'head' => $env->head, 'base' => $env->base,
            'ok' => !$this->errors(), 'checked' => $this->checked,
            'findings' => array_map(fn(Finding $f) => get_object_vars($f), $this->sorted())];
    }
}
