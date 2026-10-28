import { collect, detect, lastComment } from './detect.js';
import { card, resolvedCard, reminderText, overflowText } from './blocks.js';

// Uma varredura. `state` é mutado (o chamador persiste). Em dryRun nada vai ao Slack
// e o estado recebido não é alterado.
export async function poll({ tf, slack, cfg, state, dryRun = false, now = Date.now(), log = () => {} }) {
  const snapshot = await collect(tf, cfg);
  const items = detect(snapshot, cfg).filter((i) => cfg.policy?.events[i.kind]?.notify !== false);
  const byKey = new Map(items.map((i) => [i.key, i]));
  const st = dryRun ? structuredClone(state) : state;
  const out = { baselined: false, posted: [], resolved: [], reminded: [], overflow: 0 };

  // 1ª execução (sem state.json): registra o que já está aberto sem repostar — evita flood.
  if (!st.exists) {
    for (const it of items) st.open[it.key] = { item: it, ts: null, reminders: 0 };
    st.exists = true;
    out.baselined = true;
    log(`baseline: ${items.length} item(ns) já abertos registrados sem notificar`);
    return out;
  }

  // 1) itens que deixaram de estar pendentes -> atualiza o card
  for (const [key, rec] of Object.entries(st.open)) {
    if (byKey.has(key)) continue;
    if (rec.ts && !dryRun) {
      try {
        await slack.chat.update({ channel: rec.channel, ts: rec.ts, ...resolvedCard(rec.item, 'Não está mais pendente na TwinForge.', { cfg }) });
      } catch (e) {
        log(`falha ao atualizar card ${key}: ${e.message}`);
      }
    }
    delete st.open[key];
    out.resolved.push(key);
  }

  // 2) itens novos -> card (com teto por varredura)
  const fresh = items.filter((i) => !st.open[i.key]);
  const batch = fresh.slice(0, cfg.maxPerPoll);
  for (const it of batch) {
    const detail = it.kind === 'blocked' ? await lastComment(tf, it.issueId).catch(() => '') : '';
    const msg = card(it, { cfg, detail, now });
    if (dryRun) {
      out.posted.push({ key: it.key, msg });
      continue;
    }
    try {
      const res = await slack.chat.postMessage({ channel: cfg.channel, ...msg });
      st.open[it.key] = { item: it, channel: res.channel, ts: res.ts, postedAt: now, lastReminderAt: now, reminders: 0 };
      out.posted.push(it.key);
    } catch (e) {
      log(`Slack não notificado (${it.key}): ${e.data?.error || e.message}`); // não marca: tenta de novo na próxima
    }
  }

  const over = fresh.length - batch.length;
  out.overflow = over;
  if (over > 0 && over !== st.overflow && !dryRun) {
    await slack.chat.postMessage({ channel: cfg.channel, text: overflowText(over) }).catch((e) => log(`overflow: ${e.message}`));
  }
  st.overflow = over;

  // 3) lembretes limitados
  const gap = cfg.reminderHours * 3600_000;
  for (const [key, rec] of Object.entries(st.open)) {
    if (!rec.ts || rec.reminders >= cfg.maxReminders) continue;
    if (now - (rec.lastReminderAt || rec.postedAt) < gap) continue;
    if (!dryRun) {
      try {
        await slack.chat.postMessage({ channel: rec.channel, thread_ts: rec.ts, text: reminderText(rec.item, cfg.reminderHours) });
      } catch (e) {
        log(`lembrete ${key}: ${e.message}`);
        continue;
      }
      rec.reminders += 1;
      rec.lastReminderAt = now;
    }
    out.reminded.push(key);
  }
  return out;
}
