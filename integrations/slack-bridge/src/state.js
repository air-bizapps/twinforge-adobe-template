import { existsSync, readFileSync, writeFileSync, renameSync } from 'node:fs';

// Só guarda "qual card foi postado pra qual item". A fonte de verdade é a TwinForge.
export function loadState(path) {
  if (!existsSync(path)) return { exists: false, open: {}, overflow: 0 };
  const s = JSON.parse(readFileSync(path, 'utf8'));
  return { exists: true, open: s.open || {}, overflow: s.overflow || 0 };
}

export function saveState(path, state) {
  const tmp = `${path}.tmp`;
  writeFileSync(tmp, JSON.stringify({ open: state.open, overflow: state.overflow }, null, 2));
  renameSync(tmp, path);
}
