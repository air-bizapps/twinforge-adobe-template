// Block Kit. O `value` dos botões é um JSON curto { i: issueId, x: interactionId|approvalId, o: optionId }.
// Nada aqui executa ação; só descreve o card.

const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const clip = (s, n) => (String(s).length > n ? `${String(s).slice(0, n - 1)}…` : String(s));

export const ICON = { blocked: ':no_entry:', confirmation: ':raised_hand:', question: ':question:', approval: ':ballot_box_with_check:', other: ':eyes:' };
export const LABEL = {
  blocked: 'Bloqueio',
  confirmation: 'Confirmação pendente',
  question: 'Pergunta pendente',
  approval: 'Aprovação pendente',
  other: 'Decisão pendente',
};

export function ago(iso, now = Date.now()) {
  const min = Math.max(0, Math.round((now - new Date(iso).getTime()) / 60000));
  if (Number.isNaN(min)) return 'há tempo indeterminado';
  if (min < 60) return `há ${min} min`;
  const h = Math.floor(min / 60);
  return h < 48 ? `há ${h}h` : `há ${Math.floor(h / 24)} dias`;
}

export const val = (o) => JSON.stringify(o);
const btn = (action_id, text, v, extra = {}) => ({
  type: 'button',
  action_id,
  text: { type: 'plain_text', text: clip(text, 70) },
  value: v,
  ...extra,
});

export function issueUrl(cfg, item) {
  return cfg.uiBase && item.identifier ? `${cfg.uiBase}/issues/${item.identifier}` : null;
}

export function card(item, { cfg, detail = '', now = Date.now() } = {}) {
  const url = issueUrl(cfg || {}, item);
  const head = item.identifier ? `*${esc(item.identifier)}* — ${esc(item.title)}` : `*${esc(item.title)}*`;
  const meta = [item.agent ? `Agente: ${esc(item.agent)}` : null, ago(item.since, now)].filter(Boolean).join(' · ');

  let body = '';
  if (item.kind === 'blocked') body = detail ? `> ${esc(clip(detail, 600)).replace(/\n/g, '\n> ')}` : '';
  if (item.kind === 'confirmation') body = `> ${esc(clip(item.interaction?.prompt || '', 600)).replace(/\n/g, '\n> ')}`;
  if (item.kind === 'question') {
    const q = item.interaction?.questions?.[0];
    body = `> ${esc(clip(q?.prompt || '', 600)).replace(/\n/g, '\n> ')}`;
  }

  const blocks = [
    { type: 'section', text: { type: 'mrkdwn', text: `${ICON[item.kind] || ICON.other} *${LABEL[item.kind] || LABEL.other}*\n${head}\n${meta}${body ? `\n${body}` : ''}` } },
  ];

  const v = { i: item.issueId, x: item.interactionId || item.approvalId || null };
  const allow = (name) => !cfg?.policy || cfg.policy.events[item.kind]?.actions.has(name);
  const actions = [];
  if (item.kind === 'blocked') {
    if (allow('guide_and_resume')) actions.push(btn('tf_comment', 'Orientar e retomar', val(v), { style: 'primary' }));
    if (allow('cancel')) actions.push(btn('tf_cancel', 'Cancelar issue', val(v), {
      style: 'danger',
      confirm: {
        title: { type: 'plain_text', text: 'Cancelar a issue?' },
        text: { type: 'mrkdwn', text: `Isso cancela *${esc(item.identifier)}*. É uma decisão de escopo.` },
        confirm: { type: 'plain_text', text: 'Cancelar issue' },
        deny: { type: 'plain_text', text: 'Voltar' },
      },
    }));
  } else if (item.kind === 'confirmation') {
    if (allow('accept')) actions.push(btn('tf_accept', 'Aceitar', val(v), { style: 'primary' }));
    if (allow('reject')) actions.push(btn('tf_reject', 'Rejeitar', val(v), { style: 'danger' }));
  } else if (item.kind === 'question' && allow('answer')) {
    for (const o of item.interaction.questions[0].options) {
      actions.push(btn(`tf_answer_${o.id}`.slice(0, 255), o.label, val({ ...v, q: item.interaction.questions[0].id, o: o.id })));
    }
  } else if (item.kind === 'approval') {
    if (allow('approve')) actions.push(btn('tf_approve', 'Aprovar', val(v), { style: 'primary' }));
    if (allow('reject_approval')) actions.push(btn('tf_apprej', 'Rejeitar', val(v), { style: 'danger' }));
  }
  if (url) actions.push({ type: 'button', action_id: 'tf_open', text: { type: 'plain_text', text: 'Abrir na TwinForge' }, url });
  if (actions.length) blocks.push({ type: 'actions', elements: actions.slice(0, 25) });

  return { text: `${LABEL[item.kind] || LABEL.other}: ${item.identifier || ''} ${item.title}`.trim(), blocks };
}

export function resolvedCard(item, how, { cfg } = {}) {
  const url = issueUrl(cfg || {}, item);
  const head = item.identifier ? `*${esc(item.identifier)}* — ${esc(item.title)}` : `*${esc(item.title)}*`;
  return {
    text: `Resolvido: ${item.identifier || ''} ${item.title}`.trim(),
    blocks: [
      { type: 'section', text: { type: 'mrkdwn', text: `:white_check_mark: ~${LABEL[item.kind] || LABEL.other}~ — *resolvido*\n${head}\n${esc(how)}${url ? `\n<${url}|Abrir na TwinForge>` : ''}` } },
    ],
  };
}

export function reminderText(item, hours) {
  return `:alarm_clock: Ainda pendente ${ago(item.since)} (${Math.round(hours)}h desde o último aviso): ${item.identifier || ''} ${esc(item.title)}`.trim();
}

export function overflowText(n) {
  return `:warning: Há mais *${n}* item(ns) pendente(s) além do limite por varredura; serão postados nas próximas.`;
}

export function textModal(callback_id, title, label, meta, { optional = false, submit = 'Enviar' } = {}) {
  return {
    type: 'modal',
    callback_id,
    private_metadata: JSON.stringify(meta),
    title: { type: 'plain_text', text: clip(title, 24) },
    submit: { type: 'plain_text', text: submit },
    close: { type: 'plain_text', text: 'Cancelar' },
    blocks: [{ type: 'input', block_id: 'txt', optional, label: { type: 'plain_text', text: label }, element: { type: 'plain_text_input', action_id: 'v', multiline: true } }],
  };
}
