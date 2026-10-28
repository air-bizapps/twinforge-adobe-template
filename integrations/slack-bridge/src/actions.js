// Ações que um humano autorizado dispara no Slack. Cada uma REVALIDA o estado antes de agir
// (duplo clique, outra pessoa já decidiu, item resolvido na UI) e deixa a autoria no texto.
// Regra de ordem: decidir (accept/reject/respond) ANTES de comentar — um comentário de
// usuário supersede a interação pendente.

export function makeActions({ tf, cfg }) {
  const stamp = (u) => `[via Slack — ${u.name || u.id} (${u.id})]`;
  const no = (reason) => ({ ok: false, reason });

  async function pendingInteraction(issueId, interactionId) {
    const list = await tf(['issue', 'interactions', issueId]);
    const it = (list || []).find((x) => x.id === interactionId);
    return it && it.status === 'pending' ? it : null;
  }

  return {
    isAllowed: (userId) => cfg.allowedUsers.has(userId),
    stamp,

    async commentResume(issueId, text, user) {
      const issue = await tf(['issue', 'get', issueId]);
      if (issue?.status !== 'blocked') return no('a issue não está mais bloqueada');
      await tf(['issue', 'comment', issueId, '--resume', '--body', `${stamp(user)} ${text}`]);
      return { ok: true, summary: `orientou e retomou ${issue.identifier}` };
    },

    async cancelIssue(issueId, user) {
      const issue = await tf(['issue', 'get', issueId]);
      if (issue?.status !== 'blocked') return no('a issue não está mais bloqueada');
      await tf(['issue', 'update', issueId, '--status', 'cancelled', '--comment', `${stamp(user)} Issue cancelada pelo Slack.`]);
      return { ok: true, summary: `cancelou ${issue.identifier}` };
    },

    async accept(issueId, interactionId, user) {
      if (!(await pendingInteraction(issueId, interactionId))) return no('esse pedido já foi resolvido');
      await tf(['issue', 'interaction:accept', issueId, interactionId]);
      await tf(['issue', 'comment', issueId, '--body', `${stamp(user)} Aceitou o pedido de confirmação.`]);
      return { ok: true, summary: 'aceitou' };
    },

    async reject(issueId, interactionId, reason, user) {
      if (!(await pendingInteraction(issueId, interactionId))) return no('esse pedido já foi resolvido');
      await tf(['issue', 'interaction:reject', issueId, interactionId, '--reason', `${stamp(user)} ${reason || 'Rejeitado sem motivo informado.'}`]);
      return { ok: true, summary: 'rejeitou' };
    },

    async answer(issueId, interactionId, questionId, optionId, user) {
      const it = await pendingInteraction(issueId, interactionId);
      if (!it) return no('essa pergunta já foi respondida');
      const q = (it.payload?.questions || []).find((x) => x.id === questionId);
      const opt = q?.options?.find((o) => o.id === optionId);
      if (!q || !opt) return no('opção inválida');
      await tf(['issue', 'interaction:respond', issueId, interactionId, '--answers-json', JSON.stringify([{ questionId, optionIds: [optionId] }])]);
      await tf(['issue', 'comment', issueId, '--body', `${stamp(user)} Respondeu: ${opt.label}`]);
      return { ok: true, summary: `respondeu "${opt.label}"` };
    },

    async approve(approvalId, user) {
      const a = await tf(['approval', 'get', approvalId]);
      if (a?.status !== 'pending') return no('essa aprovação já foi decidida');
      await tf(['approval', 'approve', approvalId, '--decision-note', `${stamp(user)} Aprovado pelo Slack.`]);
      return { ok: true, summary: 'aprovou' };
    },

    async rejectApproval(approvalId, note, user) {
      const a = await tf(['approval', 'get', approvalId]);
      if (a?.status !== 'pending') return no('essa aprovação já foi decidida');
      await tf(['approval', 'reject', approvalId, '--decision-note', `${stamp(user)} ${note || 'Rejeitado sem motivo informado.'}`]);
      return { ok: true, summary: 'rejeitou' };
    },
  };
}
