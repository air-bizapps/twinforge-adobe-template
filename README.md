# twinforge-adobe-template

Base para configurar um projeto **TwinForge + Adobe Commerce (Magento 2)**: o pacote de skills `twinforge` (setup, instâncias, diagnóstico, linguagem natural, envio de mudanças do OpenSpec), os comandos do repositório de configuração e uma base de agentes, skills e fluxo já testada em um projeto real.

> **É uma base a adaptar, não um padrão.** Ela nasceu da configuração do primeiro projeto configurado e ainda carrega referências a ele (principalmente nas skills `bunge-*` e nas instruções dos agentes). [`TEMPLATE.md`](./TEMPLATE.md) lista o que já foi neutralizado e o que falta adaptar.

## Como começar um projeto novo

1. Crie o repositório do projeto a partir deste template:
   ```bash
   gh repo create <org>/<projeto>-twinforge --template air-bizapps/twinforge-adobe-template --private --clone
   ```
2. Abra o Claude Code na pasta e rode `/twinforge:setup`: ele instala ou confere o `twinforgecli`, faz o login (aprovação no navegador), cria o perfil da instância e mostra company e projetos. Com mais de uma instância, escolha a do projeto e salve como padrão da pasta.
3. Adapte o repositório (checklist em `TEMPLATE.md`), rode `/tfrepo:status` e então `/tfrepo:push`. O `push` é barrado enquanto `scope.project_id` em `twinforge/roles.yaml` for um placeholder.

## O que tem aqui

| Onde | O que é |
|---|---|
| `.claude/skills/` | Pacote de skills `twinforge`: `twinforge`, `twinforge-setup`, `twinforge-instancias`, `twinforge-diagnostico`, `twinforge-apply`. |
| `commands/twinforge/` | `/twinforge:setup`, `:instances`, `:status`, `:projects`, `:update`, `:fix`, `:explore`, `:apply`. Cada um só aciona a skill. |
| `commands/tfrepo/` | Repositório de configuração: `/tfrepo:init`, `:status`, `:pull`, `:push`, `:agent`, `:local-sync`, `:help`. Fixam a instância e conferem o projeto antes de escrever. |
| `agents/` | 12 agentes (`agent.yaml` portável + `AGENTS.md`/`CONTEXT.md`). |
| `skills/` | 77 skills da plataforma: `adobe-*` (Magento 2; várias ainda citam o projeto de origem), `openspec-*`, skills de processo sem prefixo, 6 `projeto-*` (fluxo do projeto de origem, a adaptar) e 2 `bunge-*` (camadas de qualidade de código, a adaptar). |
| `twinforge/` | `workflow.yaml` (fases e aprovações), `roles.yaml` (papel → agente, com placeholders), `toolkit.yaml`. |
| `context/`, `knowledge/` | Como a plataforma e o `twinforgecli` se comportam e lições do fluxo de agentes. |
| `integrations/` | Ponte com o Slack e verificação local. |
| `docs/` | Vazia de propósito: documentação de domínio é de cada projeto. O pacote de skills está descrito em `docs/twinforge-skills.md`. |

Orientações de trabalho para o Claude Code estão em [`CLAUDE.md`](./CLAUDE.md).
