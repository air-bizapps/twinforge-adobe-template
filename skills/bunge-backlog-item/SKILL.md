---
name: bunge-backlog-item
description: "Transforma contexto já existente (SRS, discovery, ata, descrição do ticket externo, decisão técnica, post-mortem) em um card TwinForge pronto para refinamento — classificado como PBI, Débito Técnico ou tech task, com H1 prefixado [BACK]/[FRONT], Critérios de Aceite em Gherkin pt-BR e uma seção final de Casos de teste sempre presente e reprodutível, sem citar classe/módulo/arquivo/comando em nenhuma seção. Use sempre que pedirem para criar um card novo — 'cria a PBI dessa demanda', 'abre uma tech task pra esse refactor', 'documenta essa dívida técnica'. Complementa requirements-refinement-fundamentals (o processo de refinar) com o formato concreto do artefato. Adaptado da skill create-backlog-item de um time irmão (FTD B2C)."
---

# Bunge Backlog Item

Adaptado em 2026-09-11 de `air-bizapps/ftd-b2c-agent-skills`'s `create-backlog-item`.
**Uma mudança deliberada de mecanismo**: a skill original grava um arquivo `.md` em
`tasks/` na raiz do repositório (pasta não versionada). O Bunge já tem um board real — o
próprio TwinForge — então o destino aqui é **o card TwinForge** (título = H1 com o
prefixo, descrição = o resto do template), não um segundo arquivo competindo como fonte
de verdade. Isso preserva a regra já existente (id externo obrigatório, card nasce
`blocked` sem ele) em vez de duplicá-la com wording próprio.

## 1. Contrato

**Nunca inverta ordem: exigir contexto primeiro, preencher depois.** Escrever antes de
ter contexto produz um template preenchido por plausibilidade — indistinguível de um
preenchido por decisão real do time, e é isso que o torna caro.

**Entrada esperada:** material de contexto real (SRS, `proposal.md`, discovery, ata,
descrição do ticket externo, decisão técnica, post-mortem, ou o diff/branch) — nunca só
uma frase de pedido, nome de módulo, ou número de ticket sozinho.

**Saída garantida:** um card TwinForge com id externo (regra já existente, referenciada
não duplicada), H1 `[BACK] …` ou `[FRONT] …`, estrutura do template escolhido completa, e
a seção `### Casos de teste:` sempre presente com pelo menos um cenário.

**Fora de escopo:** implementar/refatorar qualquer código citado no card; estimar,
priorizar ou planejar sprint; produzir uma SRS completa (skill `srs`) ou uma change
OpenSpec (isso é do OpenSpec Manager, via `openspec-propose` — ver `bunge-openspec-flow`).

## 2. Regras de ouro

1. **Sem contexto, não há card.** Pedir o material custa uma mensagem; um requisito
   inventado custa uma implementação inteira.
2. **Toda lacuna é declarada, nunca preenchida.** `<...>` na linha e o item listado nas
   pendências do relatório — nunca um valor plausível no lugar de uma decisão real.
3. **A estrutura do template é intocável; a concisão vive dentro dela.** Seção sem
   conteúdo recebe "Não se aplica.", nunca é removida.
4. **Três tipos irmãos, fronteira é para trás × para frente.** PBI quando alguém percebe
   a mudança. Entre os dois técnicos: **Débito Técnico** quando o trabalho quita uma
   dívida com origem identificável (decisão, PR, incidente, versão) e custo já pago hoje;
   **tech task** quando eleva um patamar ou abre caminho, sem dívida anterior. O tema
   (performance, segurança, teste) não decide nada — a origem decide.
5. **Documento de alto nível, ponta a ponta, com uma única exceção de seção.** Nenhuma
   seção cita classe, método, módulo, arquivo, tabela, config, comando ou jargão de
   implementação — nem tech task nem Débito Técnico abrem exceção; eles nomeiam a
   funcionalidade afetada e o resultado garantido, não o artefato de código. A única
   exceção é o rótulo de interface (caminho no admin, nome de botão/grid/coluna) dentro
   de `Casos de teste:` — porque é o que o QA lê na tela, não um identificador de código.
6. **Critério de Aceite prova a Regra de Negócio, não a repete.** `Dado / Quando / Então`
   com resultado observável, só para o cenário que o QA reproduz de verdade pela UI/API.
   Se nenhum cenário for reproduzível, a seção vira um resumo em prosa do que a entrega
   garante — nunca inventa um cenário artificial só para ter CA.
7. **`### Casos de teste:` é obrigatória nos três tipos e nunca fica vazia.** `Cenário N`
   com `DADO/QUANDO/ENTÃO`. Se a entrega não muda comportamento observável, os cenários
   são de regressão sobre o que já funciona hoje — nunca um cenário que exige tela,
   config ou dado criado só para viabilizar o teste. Rótulo e caminho do painel
   administrativo são literais aqui, conferidos no repositório, nunca supostos.
8. **Um pedido, um card.** Se a demanda rende mais de um item, gere o mais fundamentado e
   proponha os demais no relatório — cards concorrentes se contradizem no refinamento.

## 3. O que é lícito inferir vs. o que nunca é

**Permitido** (forma, não conteúdo): reescrever em Gherkin pt-BR um critério que o
contexto já descreve em prosa; derivar `[BACK]`/`[FRONT]` do caminho dos arquivos
afetados; nomear o módulo quando o próprio contexto/diff o identifica; ordenar itens que
o contexto lista fora de ordem; derivar o roteiro de um cenário de teste a partir de uma
Regra de Negócio que o contexto já descreve.

**Nunca permitido:** escrever uma Regra de Negócio/RNF/CA/dependência sem origem no
contexto fornecido; completar a User Story com uma persona não citada; inferir número,
prazo ou percentual (SLA, "em até 3s") sem que o contexto os afirme; nomear módulo/classe
por semelhança de nome; preencher "Dependências" com "nenhuma" só por falta de
informação — "Não se aplica." só quando o contexto **afirma** que não há dependência.

Verificação antes de entregar: para cada Regra de Negócio/RNF/CA/dependência, responder
"qual frase do contexto fornecido sustenta este item?" — se a resposta não existe, vira
`<...>` e entra nas pendências do relatório.

## 4. Dados sensíveis

Nunca incluir no card: nome, e-mail, CPF/CNPJ, telefone, endereço ou id de cliente real
(risco de LGPD); número de pedido/`increment_id`/`entity_id` real de produção; token,
senha, chave de API ou string de conexão (citar o **nome** da variável/config, nunca o
valor); trecho de log colado em bloco (descrever o sintoma em uma linha, apontar onde o
log está).

## 5. Formato de saída

```
## Resumo
## Card TwinForge criado (id externo, tipo, prefixo)
## Contexto utilizado
## Decisões (| Decisão | Critério aplicado | Evidência |)
## Validação
## Pendências (cada <...> do documento, e cards adicionais sugeridos)
```

## Antipadrões

- Preencher o template com o pedido do usuário parafraseado — rende card completo,
  bonito e sem informação real, e ninguém percebe até o refinamento.
- Inventar RN/RNF/CA/dependência por plausibilidade em vez de marcar `<...>`.
- Citar classe/módulo/arquivo/comando fora da seção `Casos de teste:`.
- Escrever um cenário de teste que o resto do documento não sustenta — vira requisito
  pela porta dos fundos.
- Gerar mais de um card por pedido sem justificar por que a demanda exige isso.
