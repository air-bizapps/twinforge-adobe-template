---
name: adobe-magento-toolbox
description: "Ferramentas que conferem código Magento 2 de verdade, contra o vendor instalado: magento-verify (checagem dos arquivos alterados antes do done: classe inexistente, PHP que não carrega, XML fora do XSD, UI Component que trava em spinner, RequireJS, Web API, trapaça) e magento-api (assinatura real de uma classe, ou sugestão quando o nome não existe). Use antes de citar uma classe do Magento e antes de marcar qualquer subtarefa de código como done."
---

# Ferramentas Magento

Escrever o nome de uma classe ou um XML de memória é a principal causa de entrega quebrada.
Estas ferramentas leem o `vendor/` e os XSD instalados. A resposta delas vale mais que a sua
lembrança e mais que qualquer exemplo de outra versão.

Rode na raiz do worktree. Os scripts são PHP: chame com `php` e o caminho.

```bash
T=$(dirname "$(find /twinforge/.pi/agent/skills "$HOME" -path '*adobe-magento-toolbox/scripts/magento-verify.php' -print -quit 2>/dev/null)")
```

| Quando | Comando |
|---|---|
| Antes de criar qualquer artefato Magento (plugin, grid, form, REST, GraphQL, patch, observer, fila, cron, e-mail, config, layout, JS, checkout) | `php "$T/magento-example.php" <tema>`; lista de temas: `--list` |
| Antes de usar uma classe, interface ou constante do Magento | `php "$T/magento-api.php" 'Magento\Modulo\Classe'` |
| Não sabe o nome exato | `php "$T/magento-api.php" --search "palavras do que precisa"` |
| Antes do `done` (obrigatório) | `php "$T/magento-verify.php"` |
| Acordou com pedido de mudança da verificação | `php "$T/magento-verify.php" --last` (o detalhe completo) |
| Entender ou conferir uma página: rota, controller, layout, template, JS, UI Component | `php "$T/magento-trace.php" <url>` (ou `--handle <handle>`, `--ui <nome>`); `X` marca o elo quebrado |
| Ambiente com banco (stage verificador, laptop) | `php "$T/ui-render-check.php"` monta cada UI Component alterado como o Magento ao abrir a tela; `php "$T/magento-smoke.php"` cria cada classe alterada pelo ObjectManager e chama as rotas do `webapi.xml` com dados de teste (`--write` grava dentro de transação desfeita) |

## Módulo de referência

`magento-example` mostra primeiro o padrão do módulo de referência da skill (`Acme_Reference`,
instalado e testado em Magento 2.4.8: grid, form, REST, GraphQL, plugin before/around/after,
patches, fila, e-mail, cron, checkout, storefront). Sem padrão lá, ele mostra exemplos do
`app/code` do projeto e do `vendor/magento`. Copie a forma do arquivo e troque `Acme\Reference`,
`Acme_Reference` e `acme_reference` pelo seu módulo; código com esses nomes em `app/code` reprova.

## Regras do magento-verify

- `VERIFY OK` é condição para o `done`. Cole a última linha da saída no relatório.
- `VERIFY FAIL`: corrija só os itens listados, na ordem. Cada item traz arquivo:linha, o que
  fazer e, quando existe, um `Modelo:` no vendor. Abra o modelo e copie a forma; não invente.
- Item que não dá para corrigir (a classe certa não existe, a regra de negócio pede outra coisa):
  `blocked` citando o ID do item (ex.: `[UI-DS-NO-COMPONENT]`) e o arquivo.
- Ele roda no máximo 3 vezes por execução. Na 4ª, pare e marque `blocked` com os IDs que sobraram.
- Nunca silencie a checagem: `@phpstan-ignore`, `markTestSkipped`, apagar teste ou mexer em
  baseline, quarentena, `.github/`, `tools/`, `phpunit.xml` ou `app/etc/di.xml` reprova sozinho.
- Não rode `phpunit` nem `grumphp` direto: o que precisa rodar, a verificação roda.

`Ignorados (preexistentes)` são problemas em linhas que você não mexeu: não são seus, não corrija.
