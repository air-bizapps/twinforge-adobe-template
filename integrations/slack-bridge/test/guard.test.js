import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { inspect, pinSlack, validateConfig, makeAudit } from '../src/guard.js';
import { loadConfig } from '../src/config.js';
import { poll } from '../src/notify.js';

const CH = 'C0C77CLQ6RY';
const ME = 'U0AQ7A2EE22';
const cfg = loadConfig({ SLACK_CHANNEL: CH, SLACK_ALLOWED_USER_IDS: ME });
const click = (o = {}) => ({ type: 'block_actions', user: { id: ME }, channel: { id: CH }, actions: [{ action_id: 'tf_accept' }], ...o });
const submit = (o = {}) => ({ type: 'view_submission', user: { id: ME }, view: { callback_id: 'tf_m_comment', private_metadata: JSON.stringify({ ref: { channel: CH, ts: '1' } }) }, ...o });

test('entrada: só vale clique do canal fixado por usuário da allowlist', () => {
  assert.equal(inspect(click(), cfg).ok, true);
  assert.equal(inspect(click({ container: { channel_id: CH }, channel: undefined }), cfg).ok, true, 'canal via container');
  assert.equal(inspect(click({ user: { id: 'U0000OUTRO1' } }), cfg).reason, 'usuário fora da allowlist');
  assert.equal(inspect(click({ channel: { id: 'C0OUTROCANAL' } }), cfg).reason, 'canal não autorizado');
  assert.equal(inspect(click({ channel: undefined }), cfg).reason, 'canal não autorizado', 'sem canal = nega (ex.: DM/App Home)');
  assert.equal(inspect(click({ channel: { id: 'D0DIRECTMSG' } }), cfg).ok, false, 'DM nega');
});

test('entrada: modal herda o canal do card; metadata adulterada/ausente nega', () => {
  assert.equal(inspect(submit(), cfg).ok, true);
  const meta = (m) => submit({ view: { callback_id: 'x', private_metadata: m } });
  assert.equal(inspect(meta(JSON.stringify({ ref: { channel: 'C0OUTROCANAL' } })), cfg).ok, false);
  assert.equal(inspect(meta('{lixo'), cfg).ok, false);
  assert.equal(inspect(meta(''), cfg).ok, false);
});

test('entrada: tipos não suportados e ausência de usuário negam; allowlist vazia nega todos', () => {
  assert.equal(inspect({ type: 'shortcut', user: { id: ME }, channel: { id: CH } }, cfg).reason, 'tipo de interação não suportado');
  assert.equal(inspect(click({ user: undefined }), cfg).ok, false);
  assert.equal(inspect(click(), loadConfig({ SLACK_CHANNEL: CH })).ok, false);
});

test('entrada: SLACK_TEAM_ID opcional exige o workspace esperado', () => {
  const c = loadConfig({ SLACK_CHANNEL: CH, SLACK_ALLOWED_USER_IDS: ME, SLACK_TEAM_ID: 'T0ABC12345' });
  assert.equal(inspect(click({ team: { id: 'T0ABC12345' } }), c).ok, true);
  assert.equal(inspect(click({ team: { id: 'T0OUTRO999' } }), c).reason, 'workspace não autorizado');
  assert.equal(inspect(click(), c).ok, false);
});

function fakeClient() {
  const calls = [];
  const mk = (n) => async (a) => { calls.push([n, a]); return { ok: true, channel: a.channel, ts: '1' }; };
  return { calls, chat: { postMessage: mk('post'), update: mk('update'), postEphemeral: mk('eph'), delete: mk('delete') }, views: { open: mk('views') }, conversations: { open: mk('dm') } };
}

test('saída: cliente fixado só fala com o canal permitido e só expõe chat.post/update/ephemeral', async () => {
  const c = fakeClient();
  const s = pinSlack(c, CH);
  await s.chat.postMessage({ channel: CH, text: 'ok' });
  await s.chat.update({ channel: CH, ts: '1' });
  await s.chat.postEphemeral({ channel: CH, user: ME, text: 'x' });
  for (const bad of ['C0OUTROCANAL', ME, 'D0DIRECTMSG', '#twinforge-adobe-template', undefined]) {
    await assert.rejects(async () => s.chat.postMessage({ channel: bad, text: 'x' }), /bloqueado/);
    await assert.rejects(async () => s.chat.update({ channel: bad, ts: '1' }), /bloqueado/);
    await assert.rejects(async () => s.chat.postEphemeral({ channel: bad, user: ME, text: 'x' }), /bloqueado/);
  }
  assert.equal(c.calls.length, 3, 'nada além das 3 chamadas legítimas chegou ao Slack');
  assert.equal(s.chat.delete, undefined); assert.equal(s.conversations, undefined);
});

test('saída: state adulterado apontando pra outro canal não consegue atualizar/lembrar lá', async () => {
  const c = fakeClient();
  const slack = pinSlack(c, CH);
  const st = { exists: true, overflow: 0, open: { k: { item: { kind: 'blocked', title: 't', since: '2026-01-01T00:00:00Z' }, ts: '1', channel: 'C0OUTROCANAL', postedAt: 0, lastReminderAt: 0, reminders: 0 } } };
  const tf = async (a) => ({ 'issue list': [], 'agent list': [], 'approval list': [] })[a.slice(0, 2).join(' ')];
  const logs = [];
  await poll({ tf, slack, cfg: { ...cfg, reminderHours: 1, maxReminders: 3, maxPerPoll: 5 }, state: st, now: Date.now() + 1e10, log: (m) => logs.push(m) });
  assert.equal(c.calls.length, 0, 'nenhuma chamada ao Slack');
});

test('config: exige ID de canal (não nome) e ids de usuário válidos; avisa allowlist vazia', () => {
  assert.deepEqual(validateConfig(cfg), []);
  assert.match(validateConfig(loadConfig({ SLACK_CHANNEL: '#twinforge-adobe-template', SLACK_ALLOWED_USER_IDS: ME })).join(), /ID do canal/);
  assert.match(validateConfig(loadConfig({ SLACK_CHANNEL: CH, SLACK_ALLOWED_USER_IDS: 'kaio' })).join(), /inválido/);
  assert.match(validateConfig(loadConfig({ SLACK_CHANNEL: CH })).join(), /vazio/);
});

test('auditoria: grava JSONL sem texto digitado nem tokens', () => {
  const p = join(mkdtempSync(join(tmpdir(), 'aud-')), 'a.jsonl');
  makeAudit(p)({ decision: 'negado', reason: 'canal não autorizado', user: 'U1', channel: 'C1', action: 'tf_accept' });
  const l = JSON.parse(readFileSync(p, 'utf8').trim());
  assert.equal(l.decision, 'negado'); assert.ok(l.at);
  assert.ok(!/text|token|body/i.test(Object.keys(l).join()));
});

test('botão "Abrir na TwinForge" é reconhecido sem executar nada; de quem não pode, continua negado', () => {
  const open = (o = {}) => ({ type: 'block_actions', user: { id: ME }, channel: { id: CH }, actions: [{ action_id: 'tf_open' }], ...o });
  const r = inspect(open(), cfg);
  assert.equal(r.ok, true); assert.equal(r.noop, true);
  assert.equal(inspect(open({ user: { id: 'U0INTRUSO99' } }), cfg).ok, false);
  assert.equal(inspect(open({ channel: { id: 'C0OUTROCANAL' } }), cfg).ok, false);
});

test('event_callback é descartado em silêncio (sem auditoria nem resposta)', () => {
  const r = inspect({ type: 'event_callback', event: { type: 'message' } }, cfg);
  assert.equal(r.ok, false); assert.equal(r.silent, true);
});
