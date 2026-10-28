import test from 'node:test';
import assert from 'node:assert/strict';
import { detect } from '../src/detect.js';
import { card, resolvedCard } from '../src/blocks.js';
import { poll } from '../src/notify.js';
import { makeActions } from '../src/actions.js';
import { loadConfig } from '../src/config.js';

const NOW = Date.parse('2026-10-06T22:00:00Z');
const agents = [{ id: 'a1', name: 'Tech Lead' }, { id: 'a2', name: 'PO' }];
const issue = (o) => ({ id: 'i1', identifier: 'BUN-1', title: 'Titulo', assigneeAgentId: 'a1', updatedAt: '2026-10-06T20:00:00Z', ...o });
const snap = (o = {}) => ({ agents, issues: [], approvals: [], interactions: {}, ...o });
const cfg = { ...loadConfig({ SLACK_ALLOWED_USER_IDS: 'U1', TWINFORGE_UI_BASE: 'https://tf.example/' }), channel: 'C1', maxPerPoll: 2, reminderHours: 24, maxReminders: 2 };

test('bloqueio esperando filhas (covered) não notifica; os demais sim', () => {
  const s = snap({ issues: [
    issue({ id: 'c', status: 'blocked', blockedAt: 't', blockerAttention: { state: 'covered' } }),
    issue({ id: 'n', status: 'blocked', blockedAt: 't', blockerAttention: { state: 'needs_attention' } }),
    issue({ id: 'x', status: 'blocked', blockedAt: 't' }),
  ] });
  assert.deepEqual(detect(s).map((i) => i.issueId).sort(), ['n', 'x']);
  assert.equal(detect(s, { includeCovered: true }).length, 3);
});

test('só interações pendentes viram item; tipo define o card', () => {
  const s = snap({ issues: [issue({ status: 'in_review' })], interactions: { i1: [
    { id: 'p', status: 'pending', kind: 'request_confirmation', payload: { prompt: 'ok?' }, createdAt: '1' },
    { id: 'e', status: 'expired', kind: 'request_confirmation', payload: {}, createdAt: '0' },
    { id: 'q', status: 'pending', kind: 'ask_user_questions', createdAt: '2', payload: { questions: [{ id: 'q1', prompt: 'Qual?', selectionMode: 'single', options: [{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }] }] } },
    { id: 'm', status: 'pending', kind: 'ask_user_questions', createdAt: '3', payload: { questions: [{ id: 'q1', options: [] }, { id: 'q2', options: [] }] } },
    { id: 't', status: 'pending', kind: 'suggest_tasks', createdAt: '4', payload: {} },
  ] } });
  const items = detect(s);
  assert.deepEqual(items.map((i) => [i.interactionId, i.kind]), [['p', 'confirmation'], ['q', 'question'], ['m', 'other'], ['t', 'other']]);
});

test('card: botões certos por tipo e escape de mrkdwn', () => {
  const ids = (m) => m.blocks.at(-1).elements.map((e) => e.action_id);
  const b = card({ kind: 'blocked', issueId: 'i1', identifier: 'BUN-1', title: 'a <b> & c', agent: 'PO', since: '2026-10-06T20:00:00Z' }, { cfg, detail: '<@U1> olha', now: NOW });
  assert.deepEqual(ids(b), ['tf_comment', 'tf_cancel', 'tf_open']);
  assert.ok(b.blocks[0].text.text.includes('a &lt;b&gt; &amp; c'));
  assert.ok(b.blocks[0].text.text.includes('&lt;@U1&gt;'), 'menções vindas de agente não disparam');
  assert.ok(b.blocks[0].text.text.includes('há 2h'));
  const q = card({ kind: 'question', issueId: 'i', interactionId: 'x', title: 't', since: '2026-10-06T21:59:00Z', interaction: { questions: [{ id: 'q1', prompt: 'P', options: [{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }] }] } }, { cfg, now: NOW });
  assert.deepEqual(ids(q).slice(0, 2), ['tf_answer_a', 'tf_answer_b']);
  assert.ok(!JSON.stringify(resolvedCard({ kind: 'blocked', title: 't' }, 'ok', { cfg })).includes('"actions"'));
});

function fakes({ issues = [], interactions = {}, approvals = [] } = {}) {
  const calls = [];
  const tf = async (args) => {
    const k = args.slice(0, 2).join(' ');
    if (k === 'issue list') return issues;
    if (k === 'agent list') return agents;
    if (k === 'approval list') return approvals;
    if (k === 'issue interactions') return interactions[args[2]] || [];
    if (k === 'issue comments') return [{ createdAt: '1', body: 'motivo X' }];
    return null;
  };
  const slack = { chat: {
    postMessage: async (p) => { calls.push(['post', p]); return { ok: true, channel: 'C1', ts: `t${calls.length}` }; },
    update: async (p) => { calls.push(['update', p]); return { ok: true }; },
  } };
  return { tf, slack, calls };
}
const blocked = (id) => issue({ id, identifier: `BUN-${id}`, status: 'blocked', blockedAt: `b${id}` });

test('1ª execução faz baseline sem notificar; depois só o novo é postado, 1×', async () => {
  const f = fakes({ issues: [blocked('1')] });
  const st = { exists: false, open: {}, overflow: 0 };
  const a = await poll({ ...f, cfg, state: st, now: NOW });
  assert.equal(a.baselined, true); assert.equal(f.calls.length, 0);
  f.tf.issues = null;
  const f2 = fakes({ issues: [blocked('1'), blocked('2')] });
  const b = await poll({ ...f2, cfg, state: st, now: NOW });
  assert.deepEqual(b.posted, ['blocked:2:b2']);
  const c = await poll({ ...f2, cfg, state: st, now: NOW });
  assert.equal(c.posted.length, 0, 'dedupe');
});

test('resolvido atualiza o card e some do state', async () => {
  const st = { exists: true, open: {}, overflow: 0 };
  const f = fakes({ issues: [blocked('1')] });
  await poll({ ...f, cfg, state: st, now: NOW });
  const g = fakes({ issues: [] });
  const r = await poll({ ...g, cfg, state: st, now: NOW });
  assert.deepEqual(r.resolved, ['blocked:1:b1']);
  assert.equal(g.calls[0][0], 'update');
  assert.deepEqual(st.open, {});
});

test('teto por varredura + aviso de overflow uma vez; resto sai na próxima', async () => {
  const st = { exists: true, open: {}, overflow: 0 };
  const f = fakes({ issues: ['1', '2', '3', '4', '5'].map(blocked) });
  const a = await poll({ ...f, cfg, state: st, now: NOW });
  assert.equal(a.posted.length, 2); assert.equal(a.overflow, 3);
  assert.equal(f.calls.filter(([, p]) => /mais \*3\*/.test(p.text || '')).length, 1);
  const b = await poll({ ...f, cfg, state: st, now: NOW });
  assert.equal(b.posted.length, 2);
  assert.equal(f.calls.filter(([, p]) => /mais \*1\*/.test(p.text || '')).length, 1);
});

test('Slack falhando não marca como notificado (tenta de novo)', async () => {
  const st = { exists: true, open: {}, overflow: 0 };
  const f = fakes({ issues: [blocked('1')] });
  f.slack.chat.postMessage = async () => { throw Object.assign(new Error('x'), { data: { error: 'not_in_channel' } }); };
  const a = await poll({ ...f, cfg, state: st, now: NOW });
  assert.equal(a.posted.length, 0); assert.deepEqual(st.open, {});
});

test('falha ao ler a TwinForge aborta a varredura (nada vira "resolvido")', async () => {
  const st = { exists: true, open: { k: { item: { kind: 'blocked', title: 't' }, ts: '1', channel: 'C1', reminders: 0 } }, overflow: 0 };
  const f = fakes();
  f.tf = async () => { throw new Error('rede'); };
  await assert.rejects(poll({ ...f, cfg, state: st, now: NOW }), /rede/);
  assert.ok(st.open.k);
});

test('lembretes: só após o intervalo e no máximo N vezes', async () => {
  const st = { exists: true, open: {}, overflow: 0 };
  const f = fakes({ issues: [blocked('1')] });
  await poll({ ...f, cfg, state: st, now: NOW });
  const h = 3600_000;
  assert.equal((await poll({ ...f, cfg, state: st, now: NOW + 23 * h })).reminded.length, 0);
  assert.equal((await poll({ ...f, cfg, state: st, now: NOW + 25 * h })).reminded.length, 1);
  assert.equal((await poll({ ...f, cfg, state: st, now: NOW + 50 * h })).reminded.length, 1);
  assert.equal((await poll({ ...f, cfg, state: st, now: NOW + 80 * h })).reminded.length, 0, 'máx 2');
});

test('dry-run não posta nem altera o state', async () => {
  const st = { exists: true, open: {}, overflow: 0 };
  const f = fakes({ issues: [blocked('1')] });
  const r = await poll({ ...f, cfg, state: st, dryRun: true, now: NOW });
  assert.equal(r.posted.length, 1); assert.equal(f.calls.length, 0); assert.deepEqual(st.open, {});
});

function actionFakes(state) {
  const calls = [];
  const tf = async (args) => {
    calls.push(args);
    const k = args.slice(0, 2).join(' ');
    if (k === 'issue get') return { identifier: 'BUN-1', status: state.issue };
    if (k === 'issue interactions') return [{ id: 'x', status: state.inter, payload: { questions: [{ id: 'q', options: [{ id: 'a', label: 'A' }] }] } }];
    if (k === 'approval get') return { status: state.appr };
    return {};
  };
  return { calls, act: makeActions({ tf, cfg }) };
}
const U = { id: 'U1', name: 'Ana' };

test('allowlist: vazio nega todos; só os listados agem', () => {
  assert.equal(makeActions({ tf: null, cfg: { allowedUsers: new Set() } }).isAllowed('U1'), false);
  const { act } = actionFakes({});
  assert.equal(act.isAllowed('U1'), true); assert.equal(act.isAllowed('U2'), false);
});

test('ações revalidam o estado e não agem sobre item já resolvido', async () => {
  const { act, calls } = actionFakes({ issue: 'in_progress', inter: 'accepted', appr: 'approved' });
  for (const r of [await act.commentResume('i', 't', U), await act.cancelIssue('i', U), await act.accept('i', 'x', U), await act.reject('i', 'x', 'm', U), await act.answer('i', 'x', 'q', 'a', U), await act.approve('a', U), await act.rejectApproval('a', 'n', U)]) {
    assert.equal(r.ok, false);
  }
  assert.ok(calls.every((c) => ['get', 'interactions'].includes(c[1])), 'só leituras');
});

test('accept decide ANTES de comentar; texto leva a autoria; tf recebe args em array', async () => {
  const { act, calls } = actionFakes({ issue: 'blocked', inter: 'pending', appr: 'pending' });
  assert.equal((await act.accept('i', 'x', U)).ok, true);
  const verbs = calls.map((c) => c[1]);
  assert.ok(verbs.indexOf('interaction:accept') < verbs.indexOf('comment'));
  await act.commentResume('i', '"; rm -rf / #', U);
  const c = calls.at(-1);
  assert.ok(c.includes('--resume')); assert.equal(c.at(-1), '[via Slack — Ana (U1)] "; rm -rf / #');
  assert.equal((await act.answer('i', 'x', 'q', 'zzz', U)).ok, false, 'opção inexistente');
});

test('blocked com filho ativo (covered>0, nenhum travado) não notifica; com filho travado, sim', () => {
  const mk = (ba) => snap({ issues: [issue({ id: 'p', status: 'blocked', blockedAt: 't', blockerAttention: ba })] });
  assert.equal(detect(mk({ state: 'needs_attention', coveredBlockerCount: 1, stalledBlockerCount: 0, attentionBlockerCount: 2 })).length, 0);
  assert.equal(detect(mk({ state: 'needs_attention', coveredBlockerCount: 1, stalledBlockerCount: 1, attentionBlockerCount: 0 })).length, 1);
  assert.equal(detect(mk({ state: 'needs_attention', coveredBlockerCount: 0, stalledBlockerCount: 0, attentionBlockerCount: 2 })).length, 1);
});
