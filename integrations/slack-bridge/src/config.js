import { OPEN_POLICY } from './policy.js';

// Toda configuração vem de variáveis de ambiente — nenhum token/id fica no repo.
const list = (v) => new Set((v || '').split(',').map((s) => s.trim()).filter(Boolean));
const num = (v, d) => (v === undefined || v === '' || Number.isNaN(Number(v)) ? d : Number(v));

export function loadConfig(env = process.env, policy = OPEN_POLICY) {
  const envUsers = list(env.SLACK_ALLOWED_USER_IDS);
  // Com policy do workflow.yaml: quem pode agir = papéis de who_may_act; o ambiente só pode RESTRINGIR
  // mais (interseção), nunca ampliar. Sem allowlist no ambiente vale o que o workflow declara.
  const allowed = policy.allowedUsers
    ? new Set([...policy.allowedUsers].filter((u) => envUsers.size === 0 || envUsers.has(u)))
    : envUsers;
  return {
    policy,
    companyId: env.TWINFORGE_COMPANY_ID || '',
    uiBase: (env.TWINFORGE_UI_BASE || '').replace(/\/+$/, ''),
    slackBotToken: env.SLACK_BOT_TOKEN || '',
    slackAppToken: env.SLACK_APP_TOKEN || '',
    channel: env.SLACK_CHANNEL || '',
    teamId: env.SLACK_TEAM_ID || '', // opcional: só aceita interações desse workspace
    // vazio = ninguém age (falha fechada)
    allowedUsers: allowed,
    auditPath: env.BRIDGE_AUDIT_PATH || new URL('../audit.jsonl', import.meta.url).pathname,
    statePath: env.BRIDGE_STATE_PATH || new URL('../state.json', import.meta.url).pathname,
    pollSeconds: num(env.BRIDGE_POLL_SECONDS, 60),
    maxPerPoll: num(env.BRIDGE_MAX_PER_POLL, 10),
    // regras de negócio (lembretes, o que notifica) vêm do workflow.yaml, não do ambiente
    reminderHours: policy.reminderHours,
    maxReminders: policy.maxReminders,
    includeCovered: policy.includeCovered,
  };
}
