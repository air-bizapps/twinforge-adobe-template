#!/usr/bin/env python3
"""Confere, sem escrever nada, se o WAF da instância deixa publicar os arquivos de uma skill.

Cada arquivo vai no formato do `skill file:update` para uma skill que não existe (id zerado):
"Skill not found" (404) = passou pelo WAF; 403 em HTML = o WAF barrou. Com --bisect, acha a linha
(ou o par de linhas) que dispara o bloqueio. O WAF soma pontos por padrão (path traversal, variáveis
como $argv, require...): um padrão sozinho pode passar e dois juntos barrar, por isso a prova é esta
sondagem, não uma lista de palavras.

Uso (perfil do twinforgecli já apontando para a instância):
  python3 twinforge/waf-probe.py <company-id> skills/<slug> [--bisect]
A pasta tests/ é ignorada: ela não é publicada.
"""
import json
import pathlib
import subprocess
import sys

ZERO = "{{uuid}}"


def blocked(company: str, rel: str, text: str) -> bool:
    payload = json.dumps({"path": rel, "content": text})
    r = subprocess.run(["twinforgecli", "skill", "file:update", ZERO, "-C", company, "--payload-json", payload, "--json"],
                       capture_output=True, text=True, cwd="/tmp")
    out = r.stdout + r.stderr
    if "Skill not found" in out:
        return False
    if "403" in out:
        return True
    raise RuntimeError(f"resposta inesperada para {rel}: {out.strip()[:200]}")


def bisect(company: str, rel: str, lines: list, lo: int, hi: int, acc: list) -> None:
    if not blocked(company, rel, "\n".join(lines[lo:hi])):
        return
    if hi - lo == 1:
        acc.append(f"linha {lo + 1}: {lines[lo].strip()[:150]}")
        return
    mid = (lo + hi) // 2
    before = len(acc)
    bisect(company, rel, lines, lo, mid, acc)
    bisect(company, rel, lines, mid, hi, acc)
    if len(acc) == before:
        acc.append(f"linhas {lo + 1}-{hi}: so barram juntas")


def main() -> int:
    if len(sys.argv) < 3:
        print(__doc__)
        return 2
    company, root = sys.argv[1], pathlib.Path(sys.argv[2])
    files = sorted(p for p in root.rglob("*") if p.is_file() and "tests" not in p.relative_to(root).parts)
    bad = []
    for p in files:
        rel = str(p.relative_to(root))
        if blocked(company, rel, p.read_text(encoding="utf-8")):
            bad.append(p)
    print(f"{len(files) - len(bad)} de {len(files)} arquivos passam pelo WAF")
    for p in bad:
        rel = str(p.relative_to(root))
        print(f"BARRADO {rel}")
        if "--bisect" in sys.argv:
            acc: list = []
            lines = p.read_text(encoding="utf-8").split("\n")
            bisect(company, rel, lines, 0, len(lines), acc)
            for a in acc:
                print(f"  {a}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
