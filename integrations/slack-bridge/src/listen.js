import bolt from '@slack/bolt';
import { resolvedCard, textModal } from './blocks.js';
import { inspect, origin } from './guard.js';

const { App } = bolt;
const parse = (s) => JSON.parse(s || '{}');

// Socket Mode: cliques e modais. TODA interação passa antes por guard.inspect (canal fixado +
// allowlist de usuários); o que falha é auditado, recebe ack e é descartado. Toda saída vai por
// `slack` (cliente fixado no canal): nada aqui consegue falar com outro canal nem com DM.
export function startListener({ cfg, actions, slack, audit, log = console.log }) {
  const app = new App({ token: cfg.slackBotToken, appToken: cfg.slackAppToken, socketMode: true });

  app.use(async ({ ack, body, next }) => {
    const v = inspect(body, cfg);
    if (v.silent) return typeof ack === 'function' ? ack() : undefined;
    if (v.noop) return typeof ack === 'function' ? ack() : undefined; // link button: nada a executar
    if (v.ok) return next();
    audit({ decision: 'negado', reason: v.reason, user: v.user, channel: v.channel, type: v.type, action: v.action });
    if (typeof ack === 'function') await ack();
    // só avisa (efêmero) quando veio do canal certo; de qualquer outro lugar, silêncio total
    if (v.channel === cfg.channel && v.user) {
      await slack.chat.postEphemeral({ channel: cfg.channel, user: v.user, text: 'Você não está autorizado a agir nesses cards.' }).catch(() => {});
    }
  });

  const who = (body) => ({ id: body.user.id, name: body.user.name || body.user.username });
  const ref = (body) => ({ channel: body.container?.channel_id || body.channel?.id, ts: body.container?.message_ts || body.message?.ts });
  const cardItem = (body) => {
    const m = (body.message?.blocks?.[0]?.text?.text || '').match(/^[^\n]*\n([^\n]*)/);
    return { kind: 'other', title: (m?.[1] || '').replace(/[*]/g, '') };
  };
  const outcome = (body, name, r) => audit({ decision: r.ok ? 'executado' : 'sem efeito', reason: r.ok ? undefined : r.reason, user: body.user.id, channel: origin(body).channel, action: name });
  const warn = (user, text) => slack.chat.postEphemeral({ channel: cfg.channel, user, text }).catch((e) => log(`ephemeral: ${e.message}`));
  const finish = async (r, item, how) => {
    if (!r?.channel || !r?.ts) return;
    await slack.chat.update({ channel: r.channel, ts: r.ts, ...resolvedCard(item, how, { cfg }) }).catch((e) => log(`update: ${e.message}`));
  };
  const safe = (p) => p.catch((e) => ({ ok: false, reason: `erro: ${e.message}` }));

  const direct = (id, fn) =>
    app.action(id, async ({ ack, body, action }) => {
      await ack();
      const r = await safe(fn(parse(action.value), who(body)));
      outcome(body, action.action_id, r);
      if (!r.ok) return warn(body.user.id, `Nada foi feito: ${r.reason}`);
      await finish(ref(body), cardItem(body), `*${who(body).name}* ${r.summary} às ${new Date().toLocaleTimeString('pt-BR')}.`);
    });
  direct('tf_cancel', (v, u) => actions.cancelIssue(v.i, u));
  direct('tf_accept', (v, u) => actions.accept(v.i, v.x, u));
  direct('tf_approve', (v, u) => actions.approve(v.x, u));
  direct(/^tf_answer_/, (v, u) => actions.answer(v.i, v.x, v.q, v.o, u));

  const modalFor = (id, title, label, cb, opts) =>
    app.action(id, async ({ ack, body, client, action }) => {
      await ack();
      audit({ decision: 'modal aberto', user: body.user.id, channel: origin(body).channel, action: action.action_id });
      await client.views.open({ trigger_id: body.trigger_id, view: textModal(cb, title, label, { v: parse(action.value), ref: ref(body) }, opts) });
    });
  modalFor('tf_comment', 'Orientar e retomar', 'Orientação para o agente', 'tf_m_comment', { submit: 'Enviar e retomar' });
  modalFor('tf_reject', 'Rejeitar', 'Motivo (opcional)', 'tf_m_reject', { optional: true, submit: 'Rejeitar' });
  modalFor('tf_apprej', 'Rejeitar aprovação', 'Nota (opcional)', 'tf_m_apprej', { optional: true, submit: 'Rejeitar' });

  const modalSubmit = (cb, fn) =>
    app.view(cb, async ({ ack, body, view }) => {
      const meta = parse(view.private_metadata);
      const text = (view.state.values.txt.v.value || '').trim();
      if (cb === 'tf_m_comment' && !text) return ack({ response_action: 'errors', errors: { txt: 'Escreva a orientação.' } });
      await ack();
      const r = await safe(fn(meta.v, text, who(body)));
      outcome(body, cb, r);
      if (!r.ok) return warn(body.user.id, `Nada foi feito: ${r.reason}`);
      await finish(meta.ref, { kind: 'other', title: '' }, `*${who(body).name}* ${r.summary} às ${new Date().toLocaleTimeString('pt-BR')}.`);
    });
  modalSubmit('tf_m_comment', (v, t, u) => actions.commentResume(v.i, t, u));
  modalSubmit('tf_m_reject', (v, t, u) => actions.reject(v.i, v.x, t, u));
  modalSubmit('tf_m_apprej', (v, t, u) => actions.rejectApproval(v.x, t, u));

  app.error(async (e) => log(`bolt: ${e.message}`));
  return app.start().then(() => log(`listener Socket Mode ativo (canal ${cfg.channel}, ${cfg.allowedUsers.size} usuário(s) autorizado(s))`));
}
