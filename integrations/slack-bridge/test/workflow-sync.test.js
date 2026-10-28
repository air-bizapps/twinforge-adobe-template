// Garante que policies.notifications do workflow.yaml (fonte única das regras) bate com as `phases`,
// com roles.yaml e com o que o bridge sabe fazer. Quebrou aqui = workflow e bridge divergiram.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import YAML from 'yaml';
import { parsePolicy, derivePhaseSets, KINDS, KIND_ACTIONS } from '../src/policy.js';
import { loadConfig } from '../src/config.js';
import { card } from '../src/blocks.js';
import { inspect } from '../src/guard.js';
import { poll } from '../src/notify.js';

const read = (f) => YAML.parse(readFileSync(new URL(`../../../twinforge/${f}`, import.meta.url), 'utf8'));
const workflow = () => read('workflow.yaml');
const roles = () => read('roles.yaml');
const CH = 'C0C77CLQ6RY';
const ME = 'U0AQ7A2EE22';

test('workflow.yaml real: bloco válido, sem divergência com as fases e com roles.yaml', () => {
  const { errors, drift, policy } = parsePolicy(workflow(), roles());
  assert.deepEqual(errors, []);
  assert.deepEqual(drift, [], 'covers/not_covered têm que espelhar as phases');
  assert.deepEqual([...policy.allowedUsers], [ME], 'quem pode agir vem de who_may_act -> roles.yaml');
  for (const k of KINDS) assert.ok(policy.events[k], `tipo ${k} declarado`);
});

test('toda fase com gate / owner external / human manual está classificada (nada some do radar sem querer)', () => {
  const w = workflow();
  const n = w.policies.notifications;
  const d = derivePhaseSets(w);
  assert.deepEqual([...n.covers.gates].sort(), d.gates.sort());
  assert.deepEqual([...n.covers.external].sort(), d.external.sort());
  assert.deepEqual([...n.not_covered.human_manual].sort(), d.human_manual.sort());
  const ids = new Set(w.phases.map((p) => p.id));
  for (const id of [...n.covers.gates, ...n.covers.external, ...n.not_covered.human_manual]) assert.ok(ids.has(id), `${id} existe em phases`);
});

test('criar uma fase nova com gate sem atualizar `covers` é detectado', () => {
  const w = workflow();
  w.phases.push({ id: 'nova_aprovacao', type: 'choice', choices: [{ when: 'x', gate: 'approval_required', approver: 'human:client_approver', next: 'merge' }] });
  const { drift } = parsePolicy(w, roles());
  assert.match(drift.join(), /covers\.gates/);
});

test('validação: tipo/ação inexistentes, papel sem slack_user_id e lembrete inválido derrubam', () => {
  const w = workflow();
  w.policies.notifications.events.blocked.actions.push('accept'); // ação de outro tipo
  w.policies.notifications.events.inventado = { notify: true, actions: [] };
  w.policies.notifications.reminders.after_hours = 0;
  const r = roles();
  delete r.roles.client_approver.slack_user_id;
  const { errors } = parsePolicy(w, r);
  const all = errors.join('\n');
  assert.match(all, /events\.blocked\.actions: "accept"/);
  assert.match(all, /events\.inventado: tipo desconhecido/);
  assert.match(all, /after_hours/);
  assert.match(all, /não tem slack_user_id válido/);
  const w2 = workflow(); w2.policies.notifications.who_may_act = ['agent:tech_lead'];
  assert.match(parsePolicy(w2, roles()).errors.join(), /não é um papel humano/);
  const w3 = workflow(); delete w3.policies.notifications.events.question;
  assert.match(parsePolicy(w3, roles()).errors.join(), /events\.question ausente/);
});

test('allowlist: o ambiente só pode restringir mais (interseção), nunca ampliar', () => {
  const { policy } = parsePolicy(workflow(), roles());
  assert.deepEqual([...loadConfig({}, policy).allowedUsers], [ME]);
  assert.deepEqual([...loadConfig({ SLACK_ALLOWED_USER_IDS: `${ME},U0INTRUSO99` }, policy).allowedUsers], [ME], 'não amplia');
  assert.deepEqual([...loadConfig({ SLACK_ALLOWED_USER_IDS: 'U0INTRUSO99' }, policy).allowedUsers], [], 'sem interseção = ninguém');
});

test('lembretes e "blocked esperando filhas" vêm do workflow, não do ambiente', () => {
  const { policy } = parsePolicy(workflow(), roles());
  const c = loadConfig({ BRIDGE_REMINDER_HOURS: '1', BRIDGE_MAX_REMINDERS: '99', BRIDGE_INCLUDE_COVERED: '1' }, policy);
  assert.equal(c.reminderHours, 24); assert.equal(c.maxReminders, 3); assert.equal(c.includeCovered, false);
  const w = workflow(); w.policies.notifications.events.blocked_waiting_children.notify = true;
  assert.equal(loadConfig({}, parsePolicy(w, roles()).policy).includeCovered, true);
});

test('o card só renderiza as ações que o workflow habilita', () => {
  const w = workflow(); w.policies.notifications.events.confirmation.actions = ['accept'];
  const { policy } = parsePolicy(w, roles());
  const cfg = { ...loadConfig({}, policy), uiBase: '' };
  const ids = (item) => card(item, { cfg }).blocks.at(-1)?.elements?.map((e) => e.action_id) || [];
  assert.deepEqual(ids({ kind: 'confirmation', issueId: 'i', interactionId: 'x', title: 't', since: '2026-10-06T00:00:00Z', interaction: { prompt: 'p' } }), ['tf_accept']);
  w.policies.notifications.events.blocked.actions = [];
  const cfg2 = { ...loadConfig({}, parsePolicy(w, roles()).policy), uiBase: '' };
  assert.deepEqual(card({ kind: 'blocked', issueId: 'i', title: 't', since: '2026-10-06T00:00:00Z' }, { cfg: cfg2 }).blocks.length, 1, 'sem ações = sem botões');
});

test('ação desligada no workflow é negada mesmo vindo de card antigo', () => {
  const w = workflow(); w.policies.notifications.events.blocked.actions = ['guide_and_resume']; // tira "cancel"
  const cfg = { ...loadConfig({ SLACK_CHANNEL: CH }, parsePolicy(w, roles()).policy) };
  const click = (action_id) => ({ type: 'block_actions', user: { id: ME }, channel: { id: CH }, actions: [{ action_id }] });
  assert.equal(inspect(click('tf_comment'), cfg).ok, true);
  const r = inspect(click('tf_cancel'), cfg);
  assert.equal(r.ok, false); assert.match(r.reason, /desabilitada pela política/);
  assert.equal(inspect(click('tf_inventado'), cfg).ok, false, 'ação desconhecida nega');
});

test('notify:false no workflow silencia o tipo na varredura', async () => {
  const w = workflow(); w.policies.notifications.events.blocked.notify = false;
  const cfg = { ...loadConfig({ SLACK_CHANNEL: CH }, parsePolicy(w, roles()).policy), maxPerPoll: 5 };
  const issues = [{ id: 'i1', identifier: 'BUN-1', title: 't', status: 'blocked', blockedAt: 'b', assigneeAgentId: 'a', updatedAt: '2026-10-06T00:00:00Z' }];
  const tf = async (a) => ({ 'issue list': issues, 'agent list': [], 'approval list': [] })[a.slice(0, 2).join(' ')];
  const calls = [];
  const slack = { chat: { postMessage: async (p) => { calls.push(p); return { ok: true, channel: CH, ts: '1' }; }, update: async () => ({}) } };
  const out = await poll({ tf, slack, cfg, state: { exists: true, open: {}, overflow: 0 }, now: Date.now() });
  assert.equal(out.posted.length, 0); assert.equal(calls.length, 0);
});

test('cada tipo que o detector produz está declarado no workflow', () => {
  const { policy } = parsePolicy(workflow(), roles());
  for (const k of ['blocked', 'confirmation', 'question', 'approval', 'other']) assert.ok(k in policy.events && k in KIND_ACTIONS);
});
