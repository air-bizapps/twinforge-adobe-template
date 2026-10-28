# skills/

46 skills ativas (anexadas a pelo menos 1 agente) da company TwinForge do
Bunge, puxadas direto da instância remota — não do `ftd-b2c-agent-skills`
original, que foi só a referência inicial de algumas delas. **A instância
remota é sempre a fonte de verdade**; o que está aqui é um espelho do que
está em produção, não um catálogo teórico.

> **`adobe-magento-toolbox` na instância desde 2026-10-21, incompleta de propósito.**
> As ferramentas (`SKILL.md` e `scripts/`) estão inteiras e ligadas ao Tech Lead e aos devs.
> `tests/` fica só no repo: o autoteste completo roda no laptop,
> `php skills/adobe-magento-toolbox/scripts/magento-verify.php --self-test` na raiz de um Magento.
> O módulo de referência vai para a instância empacotado em `reference/bundle-N.json` (3 arquivos
> em vez de 97, porque cada arquivo publicado custa uma versão da skill; ver
> `context/twinforge-context.md`, "Skills e publicação"). A pasta `reference/Acme`, os mapas e o
> `patterns.json` ficam só no repo, e o `magento-example` lê o pacote quando ele existe. Mudou o
> módulo, um mapa ou o `patterns.json`: rode `php scripts/magento-example.php --build-bundle` (o
> `--self-test` reprova pacote desatualizado) e publique os pedaços. O `/tfrepo:status` mostra a
> pasta como "só no repo", e é esperado.

> **Fusão com magento2-agent-skills (2026-10-21).** As 30 skills de
> [maxnorm/magento2-agent-skills](https://github.com/maxnorm/magento2-agent-skills) (MIT, derivadas de
> rubenzantingh/claude-code-magento-agents) foram reescritas segundo
> `context/engenharia-de-contexto-e-prompt.md` e conferidas no vendor 2.4.8-p5.
> - **Fundidas em 7 skills existentes:** `adobe-commerce-standards`, `adobe-create-entity`,
>   `adobe-magento-backend-standards`, `adobe-create-cronjob`, `adobe-create-config`,
>   `adobe-magento-frontend-standards` e `adobe-magento-ui-component-developer`.
> - **Uma skill por externa:** cada uma virou `adobe-<nome original>` (29 novas, mais a
>   `adobe-magento-ui-component-developer`, que já existia). A dedicada é a referência aprofundada do tema
>   e aponta para a skill fundida em vez de repetir a regra.
>
> Publicadas na instância em 2026-10-22. Ligação aos agentes (menor privilégio; a lista fica nos `agent.yaml`):
> - **Backend:** as de backend e operação, mais `-catalog-specialist` e `-order-specialist`.
> - **Frontend:** as do tema Luma, mais `-xml-specialist` e `-issue-debugger`.
> - **Tech Lead:** `-code-reviewer`, `-catalog-specialist` e `-order-specialist`.
> - **DevOps** (ambiente Magento, git e workspace TwinForge, acionado pelo Tech Lead): `adobe-magento-toolbox`,
>   `-environment-engineer`, `-deployment-engineer`, `-upgrade-specialist`, `-cache-analyst`, `-index-analyst`,
>   `-local-environment-specialist` e `-issue-debugger`.
> - **Nenhum agente:** as de Hyvä (`-hyva-specialist`, `-alpine-specialist`, `-magewire-specialist`), que não se
>   aplicam ao Bunge, cujo tema é Luma.
> Licenças e origem de cada parte: `THIRD-PARTY-NOTICES.md`.

## Regra de prefixo

| Prefixo | Quando | Exemplo |
|---|---|---|
| `bunge-` | Conteúdo específico deste projeto/company (ids, convenções, nomes de agente embutidos) | `projeto-openspec-flow`, `projeto-engineering-practices` |
| `adobe-` | Desenvolvimento Magento 2 / Adobe Commerce genérico, reaproveitável em qualquer projeto Magento | `adobe-create-entity`, `adobe-magento-backend-standards` |
| sem prefixo | Nem uma coisa nem outra — processo/plataforma geral (uso do próprio TwinForge) ou categoria própria já estabelecida (`openspec-*`, nome da ferramenta) | `twinforge`, `engineering-memory`, `openspec-propose`, `react-native-standards` |

## Compatibilidade — por que a estrutura de arquivo importa

O adapter `crucible_local` (desde 2026-10-07 nenhum agente da company o usa; era o
adapter do Backend, PO, DevOps e QA) só
enxerga o `SKILL.md` de uma skill — qualquer coisa em `rules/`,
`guardrails/`, `references/`, `assets/` fica invisível pra esse agente
especificamente. Por isso a maioria das skills aqui é um `SKILL.md` único
— não é simplicidade por preguiça, é compatibilidade real com o adapter
mais restritivo que consome essas skills. As poucas exceções (`twinforge`,
`para-memory-files`, que têm `references/`/`scripts/` de verdade) são
assim porque é exatamente essa a estrutura que já está em produção — **a
estrutura de cada skill aqui é um espelho exato do `fileInventory` que a
API da instância reporta**, nunca inventada.

## O que NÃO está aqui

- **Skills vindas de `github`/`catalog`** (ex.
  `blocked-exige-blocker-declarado`, `reflection-coach`, os `qa-*`) — não
  são do Bunge pra versionar; já têm sua própria fonte de verdade fora
  daqui. Continuam referenciadas normalmente em `agents/*/agent.yaml`,
  só não têm uma pasta correspondente aqui.
- **Skills sem nenhum agente anexado hoje** (`task-planning`,
  `twinforge-board`, `twinforge-create-agent`, etc.) — fora do escopo
  porque o objetivo deste repo é refletir o estado real em uso, não um
  catálogo teórico.
- **4 skills do `ftd-b2c-agent-skills` original** (`add-gtm-event`,
  `create-lumi-component`, `use-lumi-component`, `use-lumi-drawer`) —
  específicas da stack de outro projeto (módulo `FTD_GADataLayer`, design
  system "Lumi"), sem equivalente nem uso no Bunge. Decisão explícita de
  não trazer.

## Renomeação das skills — já em vigor

Os nomes com prefixo (`adobe-create-entity`, etc.) **já existem na
instância remota** e os 10 agentes já os usam em `desiredSkills`
(verificado em 2026-10-06 com `/tfrepo:status`: 44 skills e 10 agentes
idênticos ao repo). Isso substitui a pendência que constava aqui antes.
