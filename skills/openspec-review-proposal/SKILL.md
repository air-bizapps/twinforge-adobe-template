---
name: openspec-review-proposal
description: "Checklist do dev (Backend/Frontend) para revisar uma proposta OpenSpec no stage de review: confere viabilidade, tarefas faltando, arquivos/modulos reais e testes. Aprova (status done) ou pede mudancas (status in_progress + comentario no template). Nunca edita openspec/ nem escreve codigo nesta etapa."
---

# openspec-review-proposal (Backend / Frontend)

Você é reviewer técnico. O OpenSpec Manager escreveu a proposta; você confere se
ela é implementável **por você**, na sua especialidade. Quem corrige é o
OpenSpec Manager. Você nunca edita `openspec/`, nunca escreve código e nunca dá
`git checkout` nesta etapa (o checkout compartilhado pode estar em uso).

## Passos

**1. Confirme que é sua vez.** `GET /api/issues/<id>`: `executionState.currentParticipant.agentId`
precisa ser o seu ID. Se não for, saia sem comentar.

**2. Leia os artefatos sem trocar de branch.**
```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO" && git fetch origin --prune
B="origin/feature/BUN-<EXT>"; CH="<nome da change>"
git show "$B:openspec/changes/$CH/proposal.md"
git show "$B:openspec/changes/$CH/design.md"
git show "$B:openspec/changes/$CH/tasks.md"
git ls-tree -r --name-only "$B" -- "openspec/changes/$CH/specs" | xargs -I{} git show "$B:{}"
```
`EXT` e `CH` estão no comentário "Proposta OpenSpec pronta" da issue.

**3. Checklist (só a sua especialidade: `[BACK]` se Backend, `[FRONT]` se Frontend).**
Para cada item, anote OK ou o problema concreto:

| # | Pergunta |
|---|---|
| C1 | Todo arquivo, classe, módulo, layout ou template citado existe? Confira com `git show "$B:<caminho>"` ou `git ls-tree`. |
| C2 | A abordagem do `design.md` segue as regras do projeto (só `app/code/Webjump/*` e tema Webjump; vendor só por plugin/preference/observer)? |
| C3 | Cada tarefa do seu grupo é executável em uma sessão e diz o que entregar? |
| C4 | Falta tarefa? (escrever teste unitário de classe nova — sem rodar, `setup:upgrade`/`di:compile`, `db_schema.xml`/whitelist, `static-content:deploy`, ACL, cache, tradução) |
| C5 | Os cenários das specs que tocam sua área têm um THEN que dá para apontar no código? |
| C6 | Existe dependência entre `[BACK]` e `[FRONT]` que a ordem dos grupos não respeita? |
| C7 | Algum risco óbvio não mapeado (checkout/pagamento, performance, integração SAP)? |
| C8 | As regras da seção `Regras de negócio consultadas` do `design.md` batem com o código? Abra 1–2 dos arquivo:linha citados na sua área. Regra que você conhece no código e que ficou de fora também conta. |
| C9 | **Rotas REST**: cada `webapi.xml` citado ou implícito nas tarefas tem URL + método HTTP que batem **literalmente** com o `design.md`? Compare caractere por caractere — `/V1/bunge/listprice` ≠ `/V1/bunge/listprices` (singular ≠ plural). Rota que diverge do design aprovado é um bug, não uma variação aceitável. |
| C10 | **`getList()` e REST**: se uma tarefa expõe `getList()` via REST, o tipo de retorno declarado na interface é `\Magento\Framework\Api\SearchResultsInterface` (CRUD, serializa campos da entidade) ou `\Magento\Framework\Api\Search\SearchResultInterface` (índice, serializa só `id`)? Se a interface reusa um repositório existente, abra o arquivo da interface e confira o `@return` — `Search\SearchResultInterface` (com `Search\` prefixo) não funciona para CRUD REST. |
| C11 | **PUT parcial**: se uma tarefa implementa `update()` com corpo parcial, usa `addData()` (merge) ou `setData()` (substitui o array inteiro)? `setData()` num PUT parcial zera campos não enviados e quebra serialização de getters tipados. |
| C12 | **Propriedade herdada sem tipo**: se uma classe nova estende uma classe base do Magento core (`AbstractDataProvider`, etc.) e redeclara uma propriedade protegida/pública do pai (`$collection`, `$resource`, ...), o pai declara essa propriedade **com tipo nativo**? Se o pai declara sem tipo (`protected $collection;`), a classe filha **não pode** adicionar tipo (`protected Collection $collection;`) — é fatal de PHP em tempo de carregamento da classe, derruba `setup:upgrade`/`setup:di:compile` inteiro. Abra a classe pai de verdade e confira. |
| C13 | **Fidelidade literal com o design.md**: todo valor literal que o `design.md` explicitamente assume ou decide — nome de campo de payload externo, identificador, convenção de nomenclatura — bate exatamente com o valor usado no código (constantes, chaves de array, nomes de atributo)? Não é sobre a regra de negócio em si (isso é o C8), é sobre o **valor literal exato**: se o design diz "o campo se chama `vendedor`" e o código declara `const SELLER = 'seller'`, isso é uma divergência real, mesmo que a lógica de negócio esteja correta. |
| C14 | **Classe nova que estende classe do Magento core**: para cada `extends`/`parent::__construct()`, você abriu a classe pai de verdade (`git show`/`vendor/`) e conferiu — não assumiu — 1) toda propriedade/visibilidade redeclarada bate com o pai? 2) todo tipo usado numa assinatura de método (inclusive tipo de retorno) tem `use` correspondente no arquivo, não só o `use` da factory? 3) `parent::__construct(...)` passa exatamente os parâmetros que o pai tem, sem parâmetro inventado nem faltando? E se a tarefa configura um `virtualType` em `di.xml`: o `name` é diferente do `type` (nunca self-referencing)? Quatro fatais reais do BUN-889 vieram exatamente desse padrão — assinatura "no estilo Magento" em vez de conferida. |
| C15 | **`db_schema.xml`: unicidade é `constraint`, não `index`**: todo `<index indexType="unique">` é inválido — `indexType` só aceita `fulltext`/`btree`/`hash`. Unicidade é sempre `<constraint xsi:type="unique" referenceId="...">`. Se a spec tem um cenário de "identificador/valor duplicado deve ser rejeitado", confirme que a tabela usa `constraint`, não `index`, pra essa coluna — senão o cenário não é garantido no banco, só se algum código aplicativo lembrar de checar. |
| C16 | **Formulário admin (UI Component): botão `save` existe, o controller `Save` lê o POST certo, E não deixa o ID vazio virar UPDATE fantasma?** Se a tarefa cria/edita um `*_form.xml` com `dataSource` do tipo `Magento_Ui/js/form/provider`: (a) confira em `<settings><buttons>` se existe `<button name="save" class="...SaveButton"/>` e se essa classe existe de verdade; (b) confira o `<dataScope>` do form (geralmente `data`, submissão plana, sem wrapper) contra o que o controller `Save::execute()` lê — se o form usa `<dataScope>data</dataScope>`, o controller precisa ler `$this->getRequest()->getPostValue()`, nunca `getPost('<algo inventado>')`; (c) confira se o controller anula o campo de ID quando vier vazio do POST (`if (empty($data['<entidade>_id'])) { $data['<entidade>_id'] = null; }`, padrão de `Magento\Cms\Controller\Adminhtml\Block\Save`) **antes** de `addData()` — sem isso, todo cadastro novo vira um UPDATE silencioso que não afeta nenhuma linha (sem exceção, com mensagem de sucesso, registro nunca aparece na grid). Nenhum dos três problemas aparece em `di:compile`/`setup:upgrade`/teste escrito-não-executado — só clicando em Salvar de verdade e conferindo se o registro fica no banco/grid. |

Não reprove por estilo ou preferência. Reprove só por algo que impede ou
atrapalha implementar, verificar ou revisar.

**4. Decida (um único PATCH).**
- Tudo OK → `PATCH /api/issues/<id>` com `{"status":"done","comment":"<aprovação>"}`.
- Algum problema → `PATCH /api/issues/<id>` com `{"status":"in_progress","comment":"<pedido>"}`.
  A issue volta sozinha para o OpenSpec Manager, que aplica.

Template de aprovação:
```
Review <Backend|Frontend> — APROVADO
C1–C16 OK para o grupo <[BACK]|[FRONT]>.
Observações (não bloqueiam): <lista ou "nenhuma">
```

Template de pedido de mudanças (um item por problema, sempre com arquivo):
```
Review <Backend|Frontend> — MUDANÇAS PEDIDAS
R1 [C4] tasks.md, grupo 2: falta tarefa de escrever o teste unitário de Model/Command/GetX.php
R2 [C1] design.md: cita Webjump_Foo::Bar que não existe; o equivalente real é app/code/Webjump/Baz/...
Tudo o mais: OK
```

## Nunca
- Editar `openspec/`, código, ou trocar de branch.
- Aprovar sem ter lido `tasks.md` e specs inteiros.
- Pedir mudança fora da sua especialidade (comente como observação, não como R#).






