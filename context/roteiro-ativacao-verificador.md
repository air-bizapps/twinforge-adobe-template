# Roteiro de ativação do verificador (quando a remota voltar)

Ordem para ligar o `magento-verify` nos agentes e preparar o estágio do Verificador. Cada passo que
escreve na instância precisa de confirmação do Kaio. Nenhum dado de instância aqui: agentes por
papel (`twinforge/roles.yaml`), comandos pelos slash commands do repo.

## 0. Antes (repo)

1. Revisar e fazer merge do PR do toolbox (`feature/magento-toolbox`) e, depois, do PR das instruções
   (`feature/verifier-rollout`, empilhado sobre ele).
2. `git pull` na `main` local.

## 1. Estado

`/tfrepo:status`. Esperado:

- só no repo: `adobe-magento-toolbox`;
- conteúdo diferente: `bunge-subtask-contract`, `bunge-engineering-practices`, `openspec-apply-change`;
- `desiredSkills` diferente em `backend_dev`, `frontend_dev` e `tech_lead` (só a skill nova a mais).

Qualquer outra diferença: parar e entender antes (alguém mudou a instância).

## 2. Publicar a skill (teste 1h nº 2: arquivo de skill executado com `php <caminho>`)

1. Publicar a `adobe-magento-toolbox` (173 arquivos). Cada `skill file:update`, que o `/tfrepo:push`
   usa para os arquivos além do `SKILL.md`, grava uma versão com o conteúdo de **todos** os arquivos da
   skill: arquivo a arquivo dariam ~170 versões e ~150 MB no banco da instância. Em ordem de preferência:
   1. import pelo GitHub, como as skills do `agentes-forjados` (uma versão; atualiza com
      `skill install-update`):
      `twinforgecli skill import -C <company> --payload-json '{"source":"https://github.com/air-bizapps/twinforge-adobe-template/tree/main/skills/adobe-magento-toolbox"}'`.
      Precisa que o token da instância leia este repo, que é privado; se falhar, nada é criado. A skill
      fica `github` (não editável na UI): mudança entra por PR aqui e `install-update` lá.
   2. sem acesso ao GitHub: `/tfrepo:push` sem a pasta `tests/` (116 arquivos, ~50 MB de versões);
      o autoteste do passo 2.2 roda no laptop e, na remota, só o `--list`.
2. Issue de teste para o DevOps com a linha `Atribuição direta: teste da skill` logo no começo. Sem card
   pai e sem essa linha, o agente devolve a issue para curadoria (visto no teste local). O pedido,
   literal:
   "Na raiz do workspace do projeto, rode e cole a saída completa:
   `T=$(dirname \"$(find /twinforge/.pi/agent/skills \"$HOME\" -path '*adobe-magento-toolbox/scripts/magento-verify.php' -print -quit 2>/dev/null)\")`,
   `echo $T`, `php \"$T/magento-verify.php\" --self-test` e `php \"$T/magento-example.php\" --list | head -5`."
3. Esperado: `todos os 22 casos passaram`, 11 linhas `ok   estagio:` e a lista de temas. O `echo $T`
   mostra onde a skill fica no disco do servidor: é o `{{toolbox_dir}}` do Verificador (passo 7). Se o
   `find` não achar o arquivo, a skill não chegou ao runtime do agente: conferir se ela está nas
   `desiredSkills` do DevOps para o teste (ou rodar com o Tech Lead, que vai recebê-la no passo 4).

## 3. WAF (teste 1h nº 3)

O comentário do DevOps no passo 2 já traz a saída do autoteste (ASCII, sem `<` nem `>`). Se ele foi
publicado, o WAF aceita o formato. Se falhar, o DevOps verá erro ao comentar: anotar a mensagem.

## 4. Ligar o verify nos agentes

1. `desiredSkills` de `backend_dev`, `frontend_dev` e `tech_lead` a partir do `agent.yaml` do repo.
   Atenção: `skills agent sync` substitui a lista inteira; mande a lista completa do arquivo.
2. `/tfrepo:push` das instruções: `AGENTS.md` dos três e as skills `bunge-subtask-contract`,
   `bunge-engineering-practices` e `openspec-apply-change`.
3. `/tfrepo:status` limpo; conferir `env` e secrets à mão (o status não compara):
   `twinforgecli agent get <id>` (`adapterConfig.env`) e `twinforgecli secrets usage <id>`.

## 5. Primeira card com o verify

Na próxima card de código, conferir:

- o relatório do dev traz a linha `VERIFY OK` (e não `OK: ...` do script antigo);
- se o dev teve `VERIFY FAIL`, corrigiu os itens listados em até 3 execuções;
- o Tech Lead rodou o `magento-verify` no gate antes de liberar a próxima subtarefa.

Desvio (dev ignora a ferramenta, loop, falso positivo): registrar em `knowledge/defects/` e ajustar
a regra ou a instrução; não corrigir a tarefa em andamento.

## 6. Banco de verificação na remota (teste 1h nº 1, para o render e o smoke)

Pedido ao DevOps, num worktree de verificação (nunca no workspace principal):

1. criar `app/etc/verify/di.xml`, fora do git (em `.git/info/exclude`), com o tipo
   `Magento\Framework\DB\Adapter\SqlVersionProvider` e o item `MariaDB-11.8` = `^11\.8\.` no
   argumento `supportedVersionPatterns`;
2. rodar `bin/magento setup:upgrade` contra um banco de verificação (cópia, nunca o banco
   compartilhado) e colar o fim da saída.

Esperado: o `setup:upgrade` passa da checagem de versão. Alternativa suportada: MariaDB 11.4 em
tarball no runtime service, como o OpenSearch.

## 7. Estágio do Verificador (teste 1h nº 4)

Testado na TwinForge local em 2026-10-21. O Verificador devolveu a subtarefa em 2 s com os 3 defeitos
semeados, o dev corrigiu só aqueles IDs e o Verificador aprovou na rodada 2. O que a plataforma exige
(detalhe no docblock do `verify-stage.php`):

- adapter `process` com `args` em lista (string é ignorada) e `cwd` explícito; `heartbeat.enabled`
  falso e `maxConcurrentRuns` 1 (o padrão é 20); a política de adapters da instância precisa aceitar
  `process`;
- a issue vem do run (`/api/heartbeat-runs/<run>`), não do ambiente, e o `inbox-lite` não lista
  `in_review`;
- só a chamada da API leva o `done` do dev ao estágio. A linha `TWINFORGE_DISPOSITION: done` sozinha
  fecha a subtarefa sem revisão (a plataforma aplica sem passar pela política); por isso os devs marcam
  `done` pela API e o gate do Tech Lead roda o `magento-verify` de novo;
- o wake do dev depois da devolução não traz o comentário: a regra de ler o `VERIFY FAIL` e rodar
  `magento-verify --last` está no `AGENTS.md` dos devs;
- o nginx da instância responde 403 a requisição sem `User-Agent`, e o PHP não manda um por padrão:
  o `verify-stage` manda;
- wake manual ou de recuperação não traz a issue: o Verificador procura as revisões pendentes dele.
  Run de revisão que falha ganha um retry e uma recuperação; depois a plataforma bloqueia a issue
  com o estágio ainda pendente. Corrigida a causa, `twinforgecli agent wake <Verificador>` retoma;
- `$WS` não é checkout: o principal é `$WS/bunge-magento2` e as cards ficam em `$WS/worktrees/<ID>`.

Passos, cada um com confirmação:

1. Criar o agente a partir de `agents/verifier/agent.yaml` (`{{toolbox_dir}}` sai do passo 2.3;
   `{{workspace_dir}}` é o `$WS`), com `VERIFY_MODE=observe`: comenta e aprova sempre.
2. Trocar `VERIFIER_AGENT_ID` pelo id real em `twinforge/roles.yaml` e no `bunge-subtask-contract`,
   fazer o commit e `/tfrepo:push` do contrato e dos `AGENTS.md` do Tech Lead e dos devs (PR do
   estágio).
3. Medir 3 dias: comparar os comentários "Observacao (nao bloqueia)" com o que o Kaio corrigiu. Sem
   falso positivo, passar para `VERIFY_MODE=enforce`.

## Execução

- **2026-10-21:** passos 0 a 4 aplicados.
  - Merge dos PRs #20, #21 e #23 (este deixou a toolbox publicável pelo WAF).
  - A skill subiu sem `tests/` e só com 4 dos 93 arquivos de `reference/`: o upload custa ~45 s
    por arquivo, crescendo. O resto espera a correção da plataforma.
  - Teste do DevOps, rodada 1: sem a skill nas `desiredSkills`, o `find` não achou nada.
  - Teste do DevOps, rodada 2: com a skill adicionada temporariamente (lista restaurada depois),
    autoteste, `magento-api` e `magento-verify` rodaram no runtime e o WAF aceitou a saída. O
    `magento-verify` no workspace, que estava numa card em andamento, acusou 13 classes inexistentes no código do agente.
  - Passo 4: `desiredSkills` e instruções do Tech Lead e dos devs iguais à `main`.
- **2026-10-21 (tarde):** passo 7 em modo observação.
  - Verificador criado (`process`) e scripts do estágio publicados (os 11 arquivos que ele usa,
    conferidos contra o repo); o id dele entrou no contrato e no `roles.yaml`.
  - Teste 1: 403 do nginx por falta de `User-Agent`. A plataforma tentou de novo, recuperou e
    bloqueou a issue; com o script corrigido, um wake manual retomou e fechou.
  - Teste 2: o Verificador rodou o `magento-verify` no checkout principal em 12 s e comentou os 13
    erros que já se conheciam, sem acordar nenhum dev.
  - Instruções do Tech Lead, dos devs e o contrato enviados: subtarefa de dev nova nasce com o estágio.
  - Próximo: medir 3 dias (observações contra o que o Kaio corrige) e passar a `VERIFY_MODE=enforce`.

## Desfazer

Reverter o PR das instruções e `/tfrepo:push` dos arquivos revertidos. A skill pode ficar: sem
instrução que a chame, nenhum agente a usa.
