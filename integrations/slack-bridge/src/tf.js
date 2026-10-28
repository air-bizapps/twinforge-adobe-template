import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const run = promisify(execFile);

// Executa twinforgecli sem shell (args em array — nada vindo do Slack é interpolado num comando).
export function makeTf(bin = process.env.TWINFORGECLI || 'twinforgecli') {
  return async function tf(args) {
    const { stdout } = await run(bin, [...args, '--json'], { maxBuffer: 32 * 1024 * 1024, timeout: 60_000 });
    return stdout.trim() ? JSON.parse(stdout) : null;
  };
}
