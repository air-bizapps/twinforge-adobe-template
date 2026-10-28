#!/usr/bin/env node
/**
 * page-health: abre páginas do Magento num Chromium de verdade e reprova o que só aparece no
 * navegador (L2 do plano): spinner que não some com a rede parada, erro de JavaScript, request com status >= 400,
 * página de exceção, grid sem linhas nem estado vazio, form sem campos, redirect para o login.
 *
 * Uso:
 *   node page-health.mjs --base http://127.0.0.1:8099 --admin-user tf_verify --admin-password-file <arquivo> \
 *        --admin admin/acme_reference/item/index --admin admin/acme_reference/item/edit/id/2 \
 *        --page acme-reference [--out var/verify/page-health]
 * Playwright: usa o node_modules de PLAYWRIGHT_DIR (padrão: ~/workspace/automated-tests-bunge-playwright).
 * Saída: uma linha por página (OK/FAIL, tempo, motivos) e screenshot das que falharam. Exit 1 se alguma falhou.
 */
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

const require = createRequire(join(process.env.PLAYWRIGHT_DIR || join(homedir(), 'workspace/automated-tests-bunge-playwright'), 'package.json'));
const { chromium } = require('playwright');

const args = { admin: [], page: [] };
for (let i = 2; i < process.argv.length; i += 2) {
    const key = process.argv[i].replace(/^--/, '');
    const value = process.argv[i + 1];
    if (key === 'admin' || key === 'page') args[key].push(value); else args[key] = value;
}
const base = (args.base || 'http://127.0.0.1:8099').replace(/\/$/, '');
const out = args.out || 'var/verify/page-health';
const SPINNERS = '[data-role="spinner"]:visible, .admin__data-grid-loading-mask:visible, .loading-mask:visible';
const WAIT_MS = 60000;   // teto para a rede parar
const QUIET_MS = 1500;   // rede parada por este tempo = página terminou de carregar

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
let failed = 0;

async function check(url, kind) {
    const page = await context.newPage();
    const problems = [];
    let inflight = 0;
    let lastActivity = Date.now();
    page.on('request', () => { inflight++; lastActivity = Date.now(); });
    const done = () => { inflight = Math.max(0, inflight - 1); lastActivity = Date.now(); };
    page.on('requestfinished', done);
    page.on('requestfailed', done);
    page.on('pageerror', (e) => problems.push(`erro JS: ${e.message.split('\n')[0]}`));
    page.on('console', (m) => { if (m.type() === 'error') problems.push(`console: ${m.text().split('\n')[0].slice(0, 160)}`); });
    page.on('response', (r) => {
        if (r.status() >= 400 && !r.url().includes('favicon')) problems.push(`HTTP ${r.status()} ${r.url().replace(base, '')}`);
    });
    const started = Date.now();
    try {
        await page.goto(url, { waitUntil: 'load', timeout: 180000 });
        if (/\/admin\/?$|admin\/auth\/login/.test(page.url()) && kind !== 'login') problems.push('redirecionou para o login');
        const body = await page.content();
        if (/Exception #\d|There has been an error processing your request/.test(body)) problems.push('pagina de excecao');
        // spinner só conta com a rede parada: servidor lento não é defeito, spinner sem request
        // pendente é o defeito C3 (deps que nunca resolvem, sem erro nenhum no console)
        const deadline = Date.now() + WAIT_MS;
        while (Date.now() < deadline && (inflight > 0 || Date.now() - lastActivity < QUIET_MS)) {
            await page.waitForTimeout(250);
        }
        if (inflight > 0) problems.push(`rede ainda ocupada apos ${WAIT_MS / 1000}s (${inflight} requests)`);
        else if (await page.locator(SPINNERS).count() > 0) problems.push('spinner visivel com a rede parada: componente que nunca termina de carregar (C3)');
        if (kind === 'grid') {
            const rows = await page.locator('tr.data-row').count();
            const empty = await page.locator('.data-grid-tr-no-data').count();
            if (rows === 0 && empty === 0) problems.push('grid sem linhas e sem estado vazio');
        }
        if (kind === 'form' && await page.locator('.admin__field').count() === 0) problems.push('form sem campos');
        if (kind === 'page' && args.expect && !(await page.content()).includes(args.expect)) problems.push(`nao achei "${args.expect}"`);
    } catch (e) {
        problems.push(`falha ao abrir: ${e.message.split('\n')[0]}`);
    }
    const ms = Date.now() - started;
    const ok = problems.length === 0;
    if (!ok) {
        failed++;
        mkdirSync(out, { recursive: true });
        await page.screenshot({ path: join(out, url.replace(/[^a-z0-9]+/gi, '_').slice(-80) + '.png'), fullPage: true }).catch(() => {});
    }
    console.log(`${ok ? 'OK  ' : 'FAIL'} ${url.replace(base, '')} (${ms} ms)${ok ? '' : '\n     ' + [...new Set(problems)].slice(0, 8).join('\n     ')}`);
    await page.close();
}

if (args.admin.length) {
    const login = await context.newPage();
    await login.goto(`${base}/admin`, { waitUntil: 'load', timeout: 180000 });
    await login.fill('#username', args['admin-user']);
    await login.fill('#login', readFileSync(args['admin-password-file'], 'utf8').trim());
    await Promise.all([login.waitForLoadState('load'), login.click('.action-login')]);
    await login.close();
    for (const path of args.admin) {
        await check(`${base}/${path}`, /\/(edit|new)(\/|$)/.test(path) ? 'form' : 'grid');
    }
}
for (const path of args.page) {
    await check(`${base}/${path}`, 'page');
}
await browser.close();
process.exit(failed ? 1 : 0);
