// Barreiras de segurança, num único lugar. Tudo aqui é puro/testável.
//  1) SAÍDA: o cliente "fixado" só consegue falar com UM canal (qualquer outro lança erro) e só expõe
//     chat.postMessage/update/postEphemeral — sem DM, sem outros métodos da API do Slack.
//  2) ENTRADA: toda interação (clique/modal) passa por inspect(): só vale se veio do canal fixado,
//     de um usuário da allowlist (e, se configurado, do workspace esperado). Falha fechada.
import { appendFileSync } from 'node:fs';
import { actionName } from './policy.js';

const CHANNEL_RE = /^[CG][A-Z0-9]{8,}$/;
const USER_RE = /^[UW][A-Z0-9]{8,}$/;

export function validateConfig(cfg) {
  const problems = [];
  if (!CHANNEL_RE.test(cfg.channel)) problems.push('SLACK_CHANNEL deve ser o ID do canal (C… ou G…), não o nome');
  if (cfg.allowedUsers.size === 0) problems.push('SLACK_ALLOWED_USER_IDS vazio: ninguém poderá agir (só notificações)');
  for (const u of cfg.allowedUsers) if (!USER_RE.test(u)) problems.push(`ID de usuário inválido na allowlist: ${u}`);
  return problems;
}

export function pinSlack(client, channel) {
  const only = (fn) => (args) => {
    if (args?.channel !== channel) throw new Error(`bloqueado: o bridge só pode usar o canal ${channel} (tentou ${args?.channel})`);
    return fn(args);
  };
  return {
    channel,
    chat: {
      postMessage: only((a) => client.chat.postMessage(a)),
      update: only((a) => client.chat.update(a)),
      postEphemeral: only((a) => client.chat.postEphemeral(a)),
    },
    views: { open: (a) => client.views.open(a) }, // abre modal só pra quem clicou; não é endereçado a canal
  };
}

const parseMeta = (s) => {
  try {
    return JSON.parse(s || '{}');
  } catch {
    return {};
  }
};

export function origin(body) {
  if (body?.type === 'block_actions') {
    return { type: 'block_actions', channel: body.channel?.id || body.container?.channel_id, action: body.actions?.[0]?.action_id };
  }
  if (body?.type === 'view_submission') {
    return { type: 'view_submission', channel: parseMeta(body.view?.private_metadata).ref?.channel, action: body.view?.callback_id };
  }
  return { type: body?.type || 'desconhecido', channel: undefined, action: undefined };
}

export function inspect(body, cfg) {
  const o = origin(body);
  const user = body?.user?.id;
  const base = { user, channel: o.channel, type: o.type, action: o.action };
  // eventos do Events API (mensagens no canal etc.) não são ações: descarta sem auditar nem responder
  if (o.type === 'event_callback') return { ok: false, silent: true, reason: 'evento ignorado', ...base };
  if (o.type !== 'block_actions' && o.type !== 'view_submission') return { ok: false, reason: 'tipo de interação não suportado', ...base };
  // botão de link ("Abrir na TwinForge") também gera um block_actions; não executa nada, só é reconhecido
  if (o.type === 'block_actions' && o.action === 'tf_open' && o.channel === cfg.channel && user && cfg.allowedUsers.has(user)) return { ok: true, noop: true, ...base };
  if (o.channel !== cfg.channel) return { ok: false, reason: 'canal não autorizado', ...base };
  if (!user || !cfg.allowedUsers.has(user)) return { ok: false, reason: 'usuário fora da allowlist', ...base };
  if (cfg.teamId && body?.team?.id !== cfg.teamId) return { ok: false, reason: 'workspace não autorizado', ...base };
  // ação desligada no workflow.yaml (ex.: card antigo depois de uma mudança de política) não executa
  if (cfg.policy && !cfg.policy.enabledActions.has(actionName(o.action))) return { ok: false, reason: 'ação desabilitada pela política do workflow.yaml', ...base };
  return { ok: true, ...base };
}

// Trilha de auditoria (JSONL). Nunca grava texto digitado nem tokens — só quem/onde/o quê.
export function makeAudit(path, log = () => {}) {
  return (entry) => {
    const line = { at: new Date().toISOString(), ...entry };
    log(`audit ${JSON.stringify(line)}`);
    if (path) {
      try {
        appendFileSync(path, `${JSON.stringify(line)}\n`);
      } catch (e) {
        log(`audit: falha ao gravar (${e.message})`);
      }
    }
  };
}
