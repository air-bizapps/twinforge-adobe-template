// Detecção pura (snapshot -> itens pendentes de humano). Adicionar um novo tipo de
// notificação = uma função aqui + um bloco em blocks.js; nenhum agente é tocado.

export const PENDING = 'pending';

// Lê o estado da TwinForge. Qualquer falha propaga: uma varredura parcial NUNCA pode
// fazer item aberto parecer "resolvido".
export async function collect(tf, cfg) {
  const C = cfg.companyId ? ['-C', cfg.companyId] : [];
  const [issues, agents, approvals] = await Promise.all([
    tf(['issue', 'list', ...C, '--status', 'blocked,in_review']),
    tf(['agent', 'list', ...C]),
    tf(['approval', 'list', ...C, '--status', PENDING]),
  ]);
  const interactions = {};
  for (const i of issues.filter((x) => x.status === 'in_review')) {
    interactions[i.id] = await tf(['issue', 'interactions', i.id]);
  }
  return { issues, agents, approvals: approvals || [], interactions };
}

export async function lastComment(tf, issueId) {
  const comments = await tf(['issue', 'comments', issueId]);
  const list = Array.isArray(comments) ? comments : [];
  const last = [...list].sort((a, b) => String(a.createdAt).localeCompare(String(b.createdAt))).at(-1);
  return last?.body || '';
}

export function detect(snapshot, { includeCovered = false } = {}) {
  const agentName = new Map(snapshot.agents.map((a) => [a.id, a.name]));
  const items = [];

  for (const issue of snapshot.issues) {
    const base = {
      issueId: issue.id,
      identifier: issue.identifier,
      title: issue.title,
      agent: agentName.get(issue.assigneeAgentId) || null,
    };

    if (issue.status === 'blocked') {
      // esperando filhas: legítimo. Vale 'covered' da plataforma OU pelo menos um blocker em andamento
      // (covered>0) sem nenhum travado — ex.: card OpenSpec esperando a proposta enquanto as subtarefas de
      // dev nascem sem responsável por desenho.
      const ba = issue.blockerAttention || {};
      const covered = ba.state === 'covered' || (ba.coveredBlockerCount > 0 && !(ba.stalledBlockerCount > 0));
      if (!covered || includeCovered) {
        items.push({
          ...base,
          key: `blocked:${issue.id}:${issue.blockedAt || ''}`,
          kind: 'blocked',
          since: issue.blockedAt || issue.updatedAt,
        });
      }
    }

    for (const it of snapshot.interactions[issue.id] || []) {
      if (it.status !== PENDING) continue;
      items.push({
        ...base,
        key: `interaction:${it.id}`,
        kind: interactionKind(it),
        interactionId: it.id,
        interaction: summarizeInteraction(it),
        since: it.createdAt,
      });
    }
  }

  for (const a of snapshot.approvals) {
    items.push({
      key: `approval:${a.id}`,
      kind: 'approval',
      approvalId: a.id,
      issueId: null,
      identifier: null,
      title: a.title || a.type || 'Aprovação',
      agent: agentName.get(a.requestedByAgentId) || null,
      since: a.createdAt,
    });
  }

  return items.sort((x, y) => String(x.since).localeCompare(String(y.since)));
}

function interactionKind(it) {
  if (it.kind === 'request_confirmation') return 'confirmation';
  if (it.kind === 'ask_user_questions') {
    const qs = it.payload?.questions || [];
    const simple = qs.length === 1 && qs[0].selectionMode === 'single' && (qs[0].options || []).length <= 5;
    return simple ? 'question' : 'other';
  }
  return 'other';
}

function summarizeInteraction(it) {
  const p = it.payload || {};
  if (it.kind === 'request_confirmation') return { prompt: p.prompt || it.title || '' };
  if (it.kind === 'ask_user_questions') {
    return {
      questions: (p.questions || []).map((q) => ({
        id: q.id,
        prompt: q.prompt,
        options: (q.options || []).map((o) => ({ id: o.id, label: o.label })),
      })),
    };
  }
  return { prompt: it.title || it.summary || it.kind };
}
