<?php
/**
 * Decisão do estágio Verificador (sem LLM) a partir do resultado do magento-verify.
 *
 * Máquina de estados por subtarefa. As rodadas anteriores vêm dos comentários do próprio
 * Verificador (Rounds no verify-stage): cada devolução tem o cabeçalho
 * "VERIFY FAIL rN/M | <issue> | sha <commit> | sig <assinatura>" e uma aprovação zera a contagem.
 *   passou                                   -> approve
 *   falhou, rodada < máximo, houve progresso -> changes (volta ao dev com os itens)
 *   falhou na última rodada ou sem progresso -> escalate (aprova com ESCALATED; o Tech Lead chama o Kaio)
 *   não deu para verificar (worktree ausente) -> escalate (repetir não resolve)
 *   erro de ambiente (exit 2)                -> infra (sem decisão: a plataforma tenta de novo e,
 *                                               falhando de novo, bloqueia a issue para um humano)
 * No modo "observe" nunca devolve: comenta e aprova, para medir falso positivo antes de bloquear.
 */
declare(strict_types=1);

namespace MagentoToolbox;

final class StageDecision
{
    public const APPROVE = 'approve';
    public const CHANGES = 'changes';
    public const ESCALATE = 'escalate';
    public const INFRA = 'infra';

    public function __construct(
        public string $action,
        public int $round,
        public int $maxRounds,
        public string $comment,
        public string $signature
    ) {
    }

    /**
     * @param array<string,mixed>|null $verify saída --json do magento-verify (null = não rodou)
     * @param array{rounds?: int, signatures?: string[]} $history devoluções anteriores
     */
    public static function decide(?array $verify, int $exitCode, string $summary, array $history,
                                  string $issueRef, string $mode, int $maxRounds, string $sha = ''): self
    {
        $round = (int)($history['rounds'] ?? 0) + 1;
        $where = "$issueRef | sha " . ($sha ?: '?');
        $observe = $mode === 'observe' ? ' | modo observacao' : '';
        // a primeira linha do magento-verify repete veredito e sha: o cabeçalho do comentário já diz
        $body = (string)preg_replace('/^VERIFY [^\n]*\n/', '', $summary);

        if ($verify === null || $exitCode === 2) {
            $text = "VERIFY ERRO DE AMBIENTE r$round/$maxRounds | $where\n" . self::clip($summary, 1500)
                . "\nNao e defeito do codigo da subtarefa e a rodada do dev nao conta. A plataforma tenta de novo;"
                . " persistindo, a issue fica bloqueada para o DevOps.";
            return new self(self::INFRA, $round - 1, $maxRounds, $text, '');
        }

        $errors = array_values(array_filter((array)($verify['findings'] ?? []),
            fn($f) => ($f['severity'] ?? '') === 'error' && empty($f['preexisting'])));
        $signature = self::signature($errors);
        $fail = "VERIFY FAIL r$round/$maxRounds | $where | sig $signature";

        if (!$errors) {
            return new self(self::APPROVE, $round, $maxRounds,
                "## Review: APPROVED\n\nVERIFY OK r$round/$maxRounds | $where$observe\n" . self::clip($body, 1200), $signature);
        }
        if ($mode === 'observe') {
            return new self(self::APPROVE, $round, $maxRounds,
                "## Review: APPROVED\n\nObservacao (nao bloqueia): o verificador acusou os itens abaixo.\n$fail$observe\n"
                . self::clip($body, 2600), $signature);
        }
        $previous = (array)($history['signatures'] ?? []);
        $repeated = $previous && end($previous) === $signature;
        if ($round >= $maxRounds || $repeated) {
            $why = $repeated ? 'a mesma falha voltou sem mudanca' : "limite de $maxRounds rodadas";
            return new self(self::ESCALATE, $round, $maxRounds,
                "## Review: APPROVED\n\nESCALATED: $why. O codigo NAO passou na verificacao; o Tech Lead leva ao Kaio.\n"
                . "$fail\n" . self::clip($body, 2300), $signature);
        }
        return new self(self::CHANGES, $round, $maxRounds,
            "$fail\n" . self::clip($body, 2600)
            . "\nCommit antes do done: o Verificador e o PR so levam o que esta commitado.",
            $signature);
    }

    /**
     * Não foi possível verificar por um motivo que repetir não resolve (ex.: worktree ausente).
     *
     * @param array{rounds?: int, signatures?: string[]} $history
     */
    public static function unverifiable(string $reason, array $history, string $issueRef, string $mode, int $maxRounds): self
    {
        $round = (int)($history['rounds'] ?? 0) + 1;
        if ($mode === 'observe') {
            // em observação nada bloqueia: ESCALATED faria o Tech Lead parar a card para o Kaio
            return new self(self::APPROVE, $round, $maxRounds,
                "## Review: APPROVED\n\nObservacao (nao bloqueia): nao foi possivel verificar: " . self::clip($reason, 600)
                . "\nVERIFY NAO RODOU r$round/$maxRounds | $issueRef | modo observacao", '');
        }
        return new self(self::ESCALATE, $round, $maxRounds,
            "## Review: APPROVED\n\nESCALATED: nao foi possivel verificar: " . self::clip($reason, 600)
            . "\nVERIFY NAO RODOU r$round/$maxRounds | $issueRef" . ($mode === 'observe' ? ' | modo observacao' : '')
            . "\nO Tech Lead confere a subtarefa (o gate roda o magento-verify) antes de seguir.", '');
    }

    /** @param array<int,array<string,mixed>> $errors */
    private static function signature(array $errors): string
    {
        $parts = array_map(fn($f) => ($f['id'] ?? '') . '@' . ($f['file'] ?? ''), $errors);
        sort($parts);
        return substr(sha1(implode("\n", array_unique($parts))), 0, 12);
    }

    /** ASCII, sem < >, cortado no limite (o wake corta comentário em 4.000 caracteres). */
    private static function clip(string $text, int $max): string
    {
        $text = Report::ascii($text);
        return strlen($text) <= $max ? $text : substr($text, 0, $max - 30) . "\n(cortado: veja var/verify/last.md)";
    }
}

/** Rodadas anteriores reconstruídas dos comentários do Verificador desde a última aprovação dele. */
final class Rounds
{
    /**
     * @param array<int,array<string,mixed>> $comments em ordem cronológica
     * @return array{rounds: int, signatures: string[]}
     */
    public static function fromComments(array $comments, string $me): array
    {
        $rounds = 0;
        $signatures = [];
        foreach ($comments as $c) {
            if ($me !== '' && ($c['authorAgentId'] ?? '') !== $me) {
                continue;
            }
            $body = (string)($c['body'] ?? '');
            if (preg_match('/^## Review: APPROVED/m', $body)) {
                [$rounds, $signatures] = [0, []];
            } elseif (preg_match('/^VERIFY FAIL r\d+\/\d+ \|.*\| sig ([0-9a-f]{12})$/m', $body, $m)) {
                $rounds++;
                $signatures[] = $m[1];
            }
        }
        return ['rounds' => $rounds, 'signatures' => $signatures];
    }
}
