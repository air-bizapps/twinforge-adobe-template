# knowledge/

Memória de defeitos e casos de avaliação dos agentes de código. **Não é lido pelos agentes**:
o que eles recebem é a regra que nasce daqui, na mensagem do `magento-verify`
(`skills/adobe-magento-toolbox`). Contexto em prosa para modelo pequeno ajuda pouco; checagem que
devolve o erro exato ajuda muito.

| Pasta | O que é |
|---|---|
| `defects/` | Um arquivo por defeito real que chegou a um PR: classe (C1–C11), sintoma, errado → certo, commit de origem, detector e status |
| `eval/` | Cards históricas com `base`, `agent_head` e `human_head`, para medir o verificador e o loop |

## Ciclo (Claude rascunha, Kaio revisa)

1. Todo dia o Claude junta as falhas (vereditos do verificador, revisões, commits de correção em
   branch de agente) e escreve um registro por defeito novo.
2. Para cada um, propõe na ordem de preferência: **regra** no `magento-verify` (com caso ruim e bom
   em `tests/fixtures`) > **modelo canônico** apontado na mensagem > linha de skill.
3. A regra só entra se acusar o commit ruim e não acusar o corrigido (`status: promovido`).
4. PR neste repo; o Kaio revisa e faz o merge; depois `/tfrepo:push` com confirmação.

Revisão de um registro: (1) o modelo citado existe e roda no Bunge? (2) a armadilha bate com o que
você corrige? (3) tem detector que prova? (4) não tem código de outro cliente?

Classes: C1 classe/interface/constante inexistente · C2 XML fora do XSD · C3 UI Component quebrado
no navegador · C4 PHP que não carrega · C5 DI · C6 rota/handle/URL · C7 LESS/RequireJS ·
C8 segurança · C9 semântica REST · C10 dado de negócio pelo nome · C11 processo.
