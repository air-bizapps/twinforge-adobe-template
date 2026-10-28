// Lê policies.notifications de twinforge/workflow.yaml (+ roles.yaml pra resolver quem pode agir).
// O workflow.yaml é a fonte ÚNICA das regras; o bridge só executa. Inválido => o bridge não sobe.
import { readFileSync } from 'node:fs';
import YAML from 'yaml';

export const KIND_ACTIONS = {
  blocked: ['guide_and_resume', 'cancel'],
  confirmation: ['accept', 'reject'],
  question: ['answer'],
  approval: ['approve', 'reject_approval'],
  other: [],
};
export const KINDS = Object.keys(KIND_ACTIONS);
const COVERED = 'blocked_waiting_children';
const USER_RE = /^[UW][A-Z0-9]{8,}$/;

// nome da ação a partir do action_id (botão) ou callback_id (modal)
export function actionName(id = '') {
  if (id === 'tf_comment' || id === 'tf_m_comment') return 'guide_and_resume';
  if (id === 'tf_cancel') return 'cancel';
  if (id === 'tf_accept') return 'accept';
  if (id === 'tf_reject' || id === 'tf_m_reject') return 'reject';
  if (id.startsWith('tf_answer_')) return 'answer';
  if (id === 'tf_approve') return 'approve';
  if (id === 'tf_apprej' || id === 'tf_m_apprej') return 'reject_approval';
  return null;
}

// Conjuntos que o grafo `phases` implica — o bloco `covers`/`not_covered` tem que espelhar isso.
export function derivePhaseSets(workflow) {
  const phases = workflow.phases || [];
  const gates = phases.filter((p) => (p.choices || []).some((c) => c.gate === 'approval_required')).map((p) => p.id);
  const external = phases.filter((p) => String(p.owner || '').startsWith('external:')).map((p) => p.id);
  const human_manual = phases.filter((p) => String(p.owner || '').startsWith('human:') && p.mode === 'manual').map((p) => p.id);
  return { gates, external, human_manual };
}

const same = (a = [], b = []) => JSON.stringify([...a].sort()) === JSON.stringify([...b].sort());

export function parsePolicy(workflow, roles) {
  const errors = [];
  const n = workflow?.policies?.notifications;
  if (!n) return { policy: null, errors: ['workflow.yaml sem policies.notifications'] };

  const events = {};
  for (const [k, v] of Object.entries(n.events || {})) {
    if (k !== COVERED && !KINDS.includes(k)) errors.push(`events.${k}: tipo desconhecido (válidos: ${[...KINDS, COVERED].join(', ')})`);
    if (typeof v?.notify !== 'boolean') errors.push(`events.${k}.notify deve ser true/false`);
    const actions = v?.actions || [];
    for (const a of actions) if (!(KIND_ACTIONS[k] || []).includes(a)) errors.push(`events.${k}.actions: "${a}" não existe para esse tipo (válidas: ${(KIND_ACTIONS[k] || []).join(', ') || 'nenhuma'})`);
    events[k] = { notify: v?.notify === true, actions: new Set(actions) };
  }
  for (const k of KINDS) if (!events[k]) errors.push(`events.${k} ausente (todo tipo precisa declarar notify)`);

  const hours = n.reminders?.after_hours;
  const max = n.reminders?.max;
  if (!(hours > 0)) errors.push('reminders.after_hours deve ser > 0');
  if (!(Number.isInteger(max) && max >= 0)) errors.push('reminders.max deve ser inteiro >= 0');

  const allowedUsers = new Set();
  for (const ref of n.who_may_act || []) {
    const m = /^human:(.+)$/.exec(ref);
    const role = m && roles?.roles?.[m[1]];
    if (!role || role.kind !== 'human') errors.push(`who_may_act: "${ref}" não é um papel humano de roles.yaml`);
    else if (!USER_RE.test(role.slack_user_id || '')) errors.push(`who_may_act: "${ref}" não tem slack_user_id válido em roles.yaml`);
    else allowedUsers.add(role.slack_user_id);
  }

  const derived = derivePhaseSets(workflow);
  const drift = [];
  if (!same(n.covers?.gates, derived.gates)) drift.push(`covers.gates=${JSON.stringify(n.covers?.gates)} mas as fases com gate são ${JSON.stringify(derived.gates)}`);
  if (!same(n.covers?.external, derived.external)) drift.push(`covers.external=${JSON.stringify(n.covers?.external)} mas as fases external são ${JSON.stringify(derived.external)}`);
  if (!same(n.not_covered?.human_manual, derived.human_manual)) drift.push(`not_covered.human_manual=${JSON.stringify(n.not_covered?.human_manual)} mas as fases humanas manuais são ${JSON.stringify(derived.human_manual)}`);

  const enabledActions = new Set();
  for (const k of KINDS) if (events[k]?.notify) for (const a of events[k].actions) enabledActions.add(a);

  return {
    errors,
    drift,
    policy: {
      events,
      includeCovered: events[COVERED]?.notify === true,
      reminderHours: hours,
      maxReminders: max,
      allowedUsers,
      enabledActions,
    },
  };
}

export function loadPolicy(workflowPath, rolesPath) {
  return parsePolicy(YAML.parse(readFileSync(workflowPath, 'utf8')), YAML.parse(readFileSync(rolesPath, 'utf8')));
}

// Política embutida usada só em testes/legado (tudo notifica, todas as ações). Em produção o index.js
// exige o workflow.yaml.
export const OPEN_POLICY = {
  events: Object.fromEntries(KINDS.map((k) => [k, { notify: true, actions: new Set(KIND_ACTIONS[k]) }])),
  includeCovered: false,
  reminderHours: 24,
  maxReminders: 3,
  allowedUsers: null, // null = usa o que vier do ambiente
  enabledActions: new Set(Object.values(KIND_ACTIONS).flat()),
};
