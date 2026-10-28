<?php
/**
 * verify-stage: o agente Verificador (adapter process, sem LLM). Quando a TwinForge o acorda como
 * participante do estágio de revisão de uma subtarefa de dev, roda o magento-verify no worktree da
 * subtarefa, decide (StageDecision) e responde: aprova, devolve ao dev com os itens ou escala.
 *
 * Como a plataforma chama (adapter process): command = php, args = [caminho deste arquivo] (array: uma
 * string é ignorada). O adapter passa TWINFORGE_API_URL, TWINFORGE_API_KEY (JWT do run, vale 1 h),
 * TWINFORGE_RUN_ID, TWINFORGE_AGENT_ID e TWINFORGE_COMPANY_ID, mas não diz qual issue acordou o run:
 * ela vem de GET /api/heartbeat-runs/<run> (contextSnapshot.issueId).
 *
 * Env do agente:
 *   VERIFY_WORKSPACE        raiz que substitui "$WS" na linha "Worktree:" da descrição
 *   VERIFY_MODE             observe (padrao: comenta e aprova sempre, mede falso positivo) | enforce
 *   VERIFY_MAX_ROUNDS=3     rodadas antes de escalar
 *   BUNGE_BASE_BRANCH       base do diff (origin/<branch>)
 *   VERIFY_SETTLE_SEC=60    espera o worktree ficar sem mudança não commitada (o run do dev pode
 *                           estar terminando quando o Verificador acorda)
 *   VERIFY_DRY_RUN=1        decide e imprime, sem escrever na TwinForge
 *
 * Regras da plataforma que este script segue (twinf-forge, issue-execution-policy e heartbeat):
 * - só o participante atual decide; aprovar = PATCH status done + comment, devolver = PATCH
 *   status in_progress + comment. Run de revisão sem decisão ganha 1 retry, 1 recuperação e
 *   depois a issue fica bloqueada: é o caminho dos erros de ambiente (exit 3).
 * - o wake do dev (execution_changes_requested) não traz o comentário e a sessão dele recomeça:
 *   o detalhe fica em var/verify/last.md do worktree (magento-verify --last).
 * - não há teto de rodadas na plataforma: a rodada sai dos comentários do próprio Verificador,
 *   que o dev não apaga (um arquivo de estado no worktree ele apagaria).
 * - nunca faz checkout da issue (in_progress quebraria a recuperação) nem menciona o dev com
 *   agent:// (a menção troca o wake de changes_requested por outro).
 *
 * Uso manual: php verify-stage.php --issue <id> [--dry-run]
 * Exit: 0 decidiu (ou nada a fazer), 3 erro de ambiente ou de API (a plataforma tenta de novo).
 */
declare(strict_types=1);

namespace MagentoToolbox;

require __DIR__ . '/lib/Report.php';
require __DIR__ . '/lib/StageDecision.php';

$opts = getopt('', ['issue:', 'dry-run']);
$dryRun = isset($opts['dry-run']) || (bool)getenv('VERIFY_DRY_RUN');
$api = TwinforgeApi::fromEnv($dryRun);
$me = (string)getenv('TWINFORGE_AGENT_ID');
$mode = getenv('VERIFY_MODE') === 'enforce' ? 'enforce' : 'observe';
$maxRounds = (int)(getenv('VERIFY_MAX_ROUNDS') ?: 3);
$workspace = rtrim((string)getenv('VERIFY_WORKSPACE'), '/');
$base = 'origin/' . (getenv('BUNGE_BASE_BRANCH') ?: 'twinforge');
$settle = (int)(getenv('VERIFY_SETTLE_SEC') ?: 60);
// O adapter process herda o ambiente do servidor: as libs do Postgres embutido no LD_LIBRARY_PATH
// quebram extensões do PHP (xsl, exigida pelo Magento) nos processos filhos.
if ($ld = getenv('LD_LIBRARY_PATH')) {
    $keep = array_filter(explode(':', $ld), fn($dir) => $dir !== '' && !str_contains($dir, 'embedded-postgres'));
    putenv($keep ? 'LD_LIBRARY_PATH=' . implode(':', $keep) : 'LD_LIBRARY_PATH');
}

try {
    $wake = '';
    $issueIds = isset($opts['issue']) ? [(string)$opts['issue']] : [];
    if (!$issueIds && ($runId = (string)getenv('TWINFORGE_RUN_ID')) !== '') {
        $context = (array)($api->get('/heartbeat-runs/' . rawurlencode($runId))['contextSnapshot'] ?? []);
        $wake = (string)($context['wakeReason'] ?? '');
        $fromRun = (string)($context['issueId'] ?? $context['taskId'] ?? '');
        $issueIds = $fromRun !== '' ? [$fromRun] : [];
    }
    if (!$issueIds) {
        // wake manual ou de recuperação não trazem a issue: as revisões pendentes deste agente
        $issueIds = $api->pendingReviews($me, (string)getenv('TWINFORGE_COMPANY_ID'));
    }
    if (!$issueIds) {
        echo "verify-stage: nada a revisar (wake $wake)\n";
        exit(0);
    }
    $failed = false;
    foreach ($issueIds as $issueId) {
        $failed = review($api, $issueId, $me, $wake, $mode, $maxRounds, $workspace, $base, $settle) || $failed;
    }
    exit($failed ? 3 : 0);
} catch (ApiError $e) {
    fwrite(STDERR, 'verify-stage: ' . $e->getMessage() . "\n");
    exit(3);
}

/** Revisa uma issue; true = erro de ambiente (a plataforma tenta de novo). */
function review(TwinforgeApi $api, string $issueId, string $me, string $wake, string $mode, int $maxRounds,
                string $workspace, string $base, int $settle): bool
{
    $issue = $api->get('/issues/' . rawurlencode($issueId));
    $ref = (string)($issue['identifier'] ?? $issueId);
    $state = (array)($issue['executionState'] ?? []);
    $participant = (array)($state['currentParticipant'] ?? []);
    // blocked: a recuperação da plataforma bloqueia a issue e mantém o estágio pendente para o Verificador
    $pendingForMe = in_array($issue['status'] ?? '', ['in_review', 'blocked'], true) && ($state['status'] ?? '') === 'pending'
        && ($participant['type'] ?? '') === 'agent' && ($me === '' || ($participant['agentId'] ?? '') === $me);
    if (!$pendingForMe) {
        handBack($api, $issue, $state, $me, $wake);
        return false;
    }

    $worktree = Worktree::fromDescription((string)($issue['description'] ?? ''), $workspace);
    $history = Rounds::fromComments($api->comments($issueId), $me);
    if ($worktree === null || (!is_dir($worktree . '/.git') && !is_file($worktree . '/.git'))) {
        $decision = StageDecision::unverifiable("worktree nao encontrado (linha Worktree: da descricao: "
            . ($worktree ?? 'ausente') . ')', $history, $ref, $mode, $maxRounds);
    } else {
        $dirty = Worktree::waitClean($worktree, $settle);
        [$json, $exit, $summary] = Worktree::verify($worktree, $base);
        if ($dirty && is_array($json)) {
            $json['findings'][] = ['id' => 'VERIFY-UNCOMMITTED', 'severity' => 'error', 'file' => $dirty[0]];
            $summary = "0 [VERIFY-UNCOMMITTED] " . count($dirty) . ' arquivo(s) sem commit: ' . implode(', ', array_slice($dirty, 0, 5))
                . "\n  O Verificador e o PR so levam o que esta commitado: faca commit no worktree.\n" . $summary;
        }
        $sha = Worktree::head($worktree);
        $decision = StageDecision::decide($json, $exit, $summary, $history, $ref, $mode, $maxRounds, $sha);
    }

    echo "$ref: {$decision->action} (r{$decision->round}/{$decision->maxRounds}, modo $mode)\n";
    match ($decision->action) {
        StageDecision::APPROVE, StageDecision::ESCALATE => $api->patchIssue($issueId, ['status' => 'done', 'comment' => $decision->comment]),
        StageDecision::CHANGES => $api->patchIssue($issueId, ['status' => 'in_progress', 'comment' => $decision->comment]),
        StageDecision::INFRA => $api->comment($issueId, $decision->comment),
    };
    return $decision->action === StageDecision::INFRA;
}

/**
 * Acordado fora de uma revisão pendente para ele (ex.: humano comentou depois da aprovação e a
 * plataforma reabriu a issue com o Verificador como responsável): devolve ao dev.
 *
 * @param array<string,mixed> $issue
 * @param array<string,mixed> $state
 */
function handBack(TwinforgeApi $api, array $issue, array $state, string $me, string $wake): void
{
    $status = (string)($issue['status'] ?? '');
    $mine = $me !== '' && ($issue['assigneeAgentId'] ?? '') === $me;
    if (!$mine || !in_array($status, ['todo', 'in_progress', 'blocked'], true)) {
        echo "verify-stage: nada a revisar (status $status, estagio " . ($state['status'] ?? 'nenhum') . ", wake $wake)\n";
        return;
    }
    $back = ($state['returnAssignee']['type'] ?? '') === 'agent' ? (string)($state['returnAssignee']['agentId'] ?? '') : '';
    $text = "Verificador: esta issue voltou para mim fora de uma revisao (wake $wake). Eu so reviso subtarefas entregues.";
    if ($back !== '') {
        $api->patchIssue((string)$issue['id'], ['assigneeAgentId' => $back, 'status' => 'todo',
            'comment' => "$text Devolvida ao dev responsavel."]);
        echo "verify-stage: devolvida ao dev $back\n";
        return;
    }
    $api->comment((string)$issue['id'], "$text Sem dev de retorno: reatribua.");
    echo "TWINFORGE_DISPOSITION: blocked: Verificador so revisa; reatribua ao dev\n";
}

/** Worktree da subtarefa e execução do magento-verify nele. */
final class Worktree
{
    /**
     * "Branch: feature/BUN-x · Worktree: $WS/worktrees/x" (cabeçalho do contrato de subtarefa). Sem a
     * linha Worktree (subtarefa de OpenSpec só com "Branch:"), o worktree da card é $WS/worktrees/x.
     */
    public static function fromDescription(string $description, string $workspace): ?string
    {
        if (!preg_match('/Worktree:\s*`?([^\s`]+)/', $description, $m)) {
            if (!preg_match('/Branch:\s*`?feature\/BUN-([\w.-]+)/', $description, $b)) {
                return null;
            }
            $m = [1 => '$WS/worktrees/' . rtrim($b[1], '.')];
        }
        $path = str_replace(['${WS}', '$WS'], $workspace, rtrim($m[1], '.,;'));
        return str_starts_with($path, '~/') ? getenv('HOME') . substr($path, 1) : $path;
    }

    /**
     * Espera até $seconds o worktree ficar sem mudança não commitada.
     *
     * @return string[] arquivos ainda sem commit (vazio = limpo)
     */
    public static function waitClean(string $worktree, int $seconds): array
    {
        $deadline = time() + $seconds;
        while (($dirty = self::uncommitted($worktree)) && time() < $deadline) {
            sleep(10);
        }
        return $dirty;
    }

    /** @return string[] */
    private static function uncommitted(string $worktree): array
    {
        $out = (string)shell_exec('git -C ' . escapeshellarg($worktree) . ' status --porcelain 2>/dev/null');
        $files = [];
        foreach (array_filter(explode("\n", $out)) as $line) {
            $file = trim(substr($line, 3));
            // arquivo novo só conta dentro de app/: na raiz do worktree ficam os links simbólicos
            // (vendor, docs) e as pastas do runtime dos agentes (.pi, .agent-memory)
            $untracked = str_starts_with($line, '??');
            // link versionado na raiz (docs -> twinforge-repo) que o setup do worktree reaponta
            $rootLink = !str_contains(rtrim($file, '/'), '/') && is_link($worktree . '/' . rtrim($file, '/'));
            if ($rootLink || preg_match('#^(var|generated|pub/static|pub/media)(/|$)#', $file)
                || ($untracked && (!str_starts_with($file, 'app/') || is_link($worktree . '/' . rtrim($file, '/'))))) {
                continue;
            }
            $files[] = $file;
        }
        return $files;
    }

    public static function head(string $worktree): string
    {
        return trim((string)shell_exec('git -C ' . escapeshellarg($worktree) . ' rev-parse --short=8 HEAD 2>/dev/null'));
    }

    /** @return array{0: ?array, 1: int, 2: string} json, exit code, resumo */
    public static function verify(string $worktree, string $base): array
    {
        $script = __DIR__ . '/magento-verify.php';
        // TWINFORGE_RUN_ID vazio: o teto de execuções por run é do dev, não do Verificador
        $errFile = (string)tempnam(sys_get_temp_dir(), 'verify-stage');
        $cmd = 'cd ' . escapeshellarg($worktree) . ' && TWINFORGE_RUN_ID= ' . escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($script) . ' --base ' . escapeshellarg($base) . ' 2>' . escapeshellarg($errFile);
        exec($cmd, $lines, $exit);
        // stderr vai para o log do run; no comentário só entra quando explica um erro de ambiente
        $stderr = trim((string)preg_replace('/^PHP (Warning|Notice|Deprecated): +PHP Startup.*$\n?/m', '', (string)@file_get_contents($errFile)));
        @unlink($errFile);
        if ($stderr !== '') {
            fwrite(STDERR, $stderr . "\n");
        }
        $summary = implode("\n", $lines) . ($exit === 2 && $stderr !== '' ? "\n" . $stderr : '');
        $last = $worktree . '/var/verify/last.json';
        $json = ($exit === 0 || $exit === 1) && is_file($last) ? json_decode((string)file_get_contents($last), true) : null;
        return [is_array($json) ? $json : null, $exit, $summary];
    }
}

final class ApiError extends \RuntimeException
{
}

/** Cliente mínimo da API da TwinForge (só o que o Verificador usa). */
final class TwinforgeApi
{
    public function __construct(private string $base, private string $key, private bool $dryRun)
    {
    }

    public static function fromEnv(bool $dryRun): self
    {
        // TWINFORGE_API_URL pode vir com ou sem /api
        $url = preg_replace('#/api$#', '', rtrim((string)(getenv('TWINFORGE_API_URL') ?: 'http://127.0.0.1:3100'), '/'));
        return new self($url . '/api', (string)getenv('TWINFORGE_API_KEY'), $dryRun);
    }

    /** @return array<string,mixed> */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @return array<int,array<string,mixed>> comentários em ordem cronológica */
    public function comments(string $issueId): array
    {
        $res = $this->request('GET', '/issues/' . rawurlencode($issueId) . '/comments?order=asc&limit=500');
        return array_is_list($res) ? $res : (array)($res['items'] ?? $res['comments'] ?? []);
    }

    /** @return string[] issues com estágio pendente para este agente (mais antigas primeiro) */
    public function pendingReviews(string $me, string $companyId): array
    {
        if ($me === '' || $companyId === '') {
            return [];
        }
        $res = $this->request('GET', '/companies/' . rawurlencode($companyId) . '/issues?assigneeAgentId='
            . rawurlencode($me) . '&status=in_review,blocked');
        $items = array_is_list($res) ? $res : (array)($res['items'] ?? $res['issues'] ?? []);
        usort($items, fn($a, $b) => strcmp((string)($a['updatedAt'] ?? ''), (string)($b['updatedAt'] ?? '')));
        $ids = [];
        foreach ($items as $item) {
            $id = (string)($item['id'] ?? '');
            $state = (array)($item['executionState'] ?? $this->request('GET', '/issues/' . rawurlencode($id))['executionState'] ?? []);
            if ($id !== '' && ($state['status'] ?? '') === 'pending' && ($state['currentParticipant']['agentId'] ?? '') === $me) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function comment(string $issueId, string $body): void
    {
        $this->request('POST', '/issues/' . rawurlencode($issueId) . '/comments', ['body' => $body]);
    }

    /** @param array<string,mixed> $patch */
    public function patchIssue(string $issueId, array $patch): void
    {
        $this->request('PATCH', '/issues/' . rawurlencode($issueId), $patch);
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if ($this->dryRun && $method !== 'GET') {
            echo "[dry-run] $method $path " . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            return [];
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->key !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->key;
        }
        if ($runId = getenv('TWINFORGE_RUN_ID')) {
            $headers[] = 'X-TwinForge-Run-Id: ' . $runId;
        }
        // sem User-Agent o nginx da instância devolve 403, e o PHP não manda um por padrão
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 30,
            'user_agent' => 'twinforge-verifier/1.0',
            'content' => $body === null ? '' : (string)json_encode($body),
        ]]);
        $response = @file_get_contents($this->base . $path, false, $context);
        $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})(\s|$)/', $http_response_header[0], $m) ? (int)$m[1] : 0;
        if ($response === false || $status >= 400 || $status === 0) {
            throw new ApiError("$method $path falhou (HTTP $status): " . substr((string)$response, 0, 300));
        }
        return (array)json_decode((string)$response, true);
    }
}
