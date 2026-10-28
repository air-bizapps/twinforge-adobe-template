#!/usr/bin/env node
import { WebClient } from '@slack/web-api';
import { loadConfig } from './config.js';
import { loadPolicy } from './policy.js';
import { resolve } from 'node:path';
import { makeTf } from './tf.js';
import { loadState, saveState } from './state.js';
import { poll } from './notify.js';
import { makeActions } from './actions.js';
import { validateConfig, pinSlack, makeAudit } from './guard.js';

const [cmd = 'help', ...flags] = process.argv.slice(2);
const dryRun = flags.includes('--dry-run');
const log = (m) => console.log(`[${new Date().toISOString()}] ${m}`);

// As regras (o que notifica, ações, quem age, lembretes) vêm de twinforge/workflow.yaml.
const here = new URL('.', import.meta.url).pathname;
const workflowPath = resolve(process.env.WORKFLOW_FILE || `${here}../../../twinforge/workflow.yaml`);
const rolesPath = resolve(process.env.ROLES_FILE || `${here}../../../twinforge/roles.yaml`);
let loaded;
try {
  loaded = loadPolicy(workflowPath, rolesPath);
} catch (e) {
  console.error(`Não consegui ler ${workflowPath}: ${e.message}`);
  process.exit(2);
}
if (loaded.errors.length) {
  for (const e of loaded.errors) console.error(`workflow.yaml: ${e}`);
  process.exit(2);
}
for (const d of loaded.drift) log(`AVISO workflow.yaml fora de sincronia com as fases: ${d}`);
const cfg = loadConfig(process.env, loaded.policy);

function need(...names) {
  if (!cfg.companyId) {
    console.error('Faltando configuração: TWINFORGE_COMPANY_ID');
    process.exit(2);
  }
  const map = { channel: cfg.channel, slackBotToken: cfg.slackBotToken, slackAppToken: cfg.slackAppToken };
  const missing = names.filter((n) => !map[n]);
  if (missing.length) {
    console.error(`Faltando configuração: ${missing.map((n) => ({ channel: 'SLACK_CHANNEL', slackBotToken: 'SLACK_BOT_TOKEN', slackAppToken: 'SLACK_APP_TOKEN' }[n])).join(', ')}`);
    process.exit(2);
  }
}

async function once(tf, slack) {
  const state = loadState(cfg.statePath);
  const out = await poll({ tf, slack, cfg, state, dryRun, log });
  if (!dryRun) saveState(cfg.statePath, state);
  const posted = out.posted.map((p) => (typeof p === 'string' ? p : p.key));
  log(`varredura: ${out.baselined ? 'baseline' : `${posted.length} novo(s), ${out.resolved.length} resolvido(s), ${out.reminded.length} lembrete(s), overflow ${out.overflow}`}${dryRun ? ' [dry-run]' : ''}`);
  if (dryRun) for (const p of out.posted) console.log(JSON.stringify(p.msg, null, 2));
}

function checkConfig() {
  const problems = validateConfig(cfg);
  const fatal = problems.filter((p) => !p.startsWith('SLACK_ALLOWED_USER_IDS vazio'));
  for (const p of problems) log(`config: ${p}`);
  if (fatal.length) process.exit(2);
}

if (cmd === 'once') {
  need(...(dryRun ? [] : ['channel', 'slackBotToken']));
  if (!dryRun) checkConfig();
  await once(makeTf(), dryRun ? null : pinSlack(new WebClient(cfg.slackBotToken), cfg.channel));
} else if (cmd === 'run') {
  need('channel', 'slackBotToken', 'slackAppToken');
  checkConfig();
  const tf = makeTf();
  const slack = pinSlack(new WebClient(cfg.slackBotToken), cfg.channel);
  const { startListener } = await import('./listen.js');
  await startListener({ cfg, actions: makeActions({ tf, cfg }), slack, audit: makeAudit(cfg.auditPath, log), log });
  for (;;) {
    await once(tf, slack).catch((e) => log(`varredura falhou (tenta de novo): ${e.message}`));
    await new Promise((r) => setTimeout(r, cfg.pollSeconds * 1000));
  }
} else {
  console.log('uso: node src/index.js once [--dry-run] | run   (config por variáveis de ambiente; ver README)');
}
