---
name: srs
description: "Produz uma Especificação de Requisitos de Software (SRS, IEEE 830 adaptado para Adobe Commerce) para cards grandes ou genuinamente ambíguos demais para o template padrão de bunge-backlog-item — requisitos funcionais testáveis, requisitos não-funcionais com métrica, escopo MVP vs. evolução futura, e lista de tarefas acionável por dev sem retrabalho. Use quando o Tech Lead julgar que um card precisa de mais estrutura antes de decompor em subtarefas. Adaptado da skill srs de um time irmão (FTD B2C) — sem o requisito de diagrama C4/Structurizr do original, que depende de uma skill que o Bunge não tem."
---

# SRS (Software Requirements Specification)

Adaptado em 2026-09-11 de `air-bizapps/ftd-b2c-agent-skills`'s `srs`. **Duas mudanças
deliberadas**: (1) o artefato final vive como plano/anexo do card TwinForge, não como
arquivo em `/SRS/` na raiz do repositório — mesma lógica de `bunge-backlog-item`, o board
já é o TwinForge; (2) **o requisito de gerar um diagrama C4 versionado em
`/structurizr/N/` foi removido** — depende da skill `diagrams` da FTD, que o Bunge não
tem. Se um diagrama de arquitetura for genuinamente necessário para um SRS específico,
isso é uma decisão explícita a tomar no momento, não uma etapa automática desta skill.

## 1. Contrato

**Nunca pule o levantamento de requisitos.** Escrever antes de entender o domínio produz
requisitos funcionais/não-funcionais genéricos que voltam como retrabalho — perguntar
custa menos que reescrever.

**Entrada esperada:** pedido para especificar uma funcionalidade/módulo novo, documentar
escopo (MVP vs. evolução), registrar premissas/restrições/perguntas em aberto, ou
atualizar uma SRS existente.

**Saída garantida:** um documento de plano completo anexado ao card TwinForge, cobrindo
requisitos funcionais testáveis, requisitos não-funcionais com métrica, escopo, e uma
lista de tarefas por dev (Backend/Frontend) acionável sem retrabalho.

**Fora de escopo:** implementar o código das tarefas descritas; gerar diagrama de
arquitetura (fora desta skill, ver nota acima); qualquer estrutura de documento diferente
do template desta skill.

## 2. Regras de ouro

1. **O template não muda por pedido nesta conversa.** Simplificar ou remover uma seção é
   sempre recusado, com o motivo explicado — mudança de template é decisão de dono da
   skill, fora de uma execução pontual.
2. **Negócio e técnica não se misturam.** As seções de contexto/objetivo/escopo ficam em
   linguagem de stakeholder; a partir da seção de requisitos técnicos, detalhe completo é
   esperado e correto.
3. **Toda tarefa da lista de decomposição é acionável por um dev sem retrabalho.** Nome
   do arquivo/classe afetada, o que muda, e critério de pronto — não uma descrição vaga
   tipo "ajustar o backend".
4. **Requisito funcional sempre testável; requisito não-funcional sempre com métrica.**
   "Deve ser rápido" não é RNF — "resposta em até 2s sob carga normal" é.

## 3. Estrutura (fixa, seções nunca omitidas mesmo vazias)

1. Contexto e objetivo (linguagem de negócio)
2. Escopo — o que entra no MVP, o que fica para depois, explicitamente separado
3. Requisitos funcionais (RF) — testáveis, numerados
4. Requisitos não-funcionais (RNF) — com métrica, numerados
5. Premissas e restrições
6. Perguntas em aberto — cada uma com quem precisa responder
7. Decomposição em tarefas por dev (Backend/Frontend), acionável sem retrabalho
8. Estimativa de esforço (escala de story points já usada pelo time, se houver — nunca
   inventar uma escala nova)

Seção sem conteúdo real recebe "Não se aplica." explicitamente — nunca é removida do
documento.

## 4. Unresolved open questions routing (regra já estabelecida, referenciada aqui)

Se a SRS termina com pelo menos uma pergunta em aberto genuinamente relevante para o
escopo, o card **vai para revisão humana** (regra 5.2 do fluxo de card-a-PR já
documentado) — o Tech Lead não decompõe em subtarefas enquanto a pergunta materialmente
relevante não for respondida. Isso não é uma regra nova desta skill, é a mesma regra de
"quando precisar de humano, vai pra review" já ativa, só que a SRS é o gatilho mais comum
de descobrir que existe uma pergunta assim.

## 5. Definição de pronto

- [ ] Levantamento de requisitos feito antes de escrever qualquer seção — nenhum RF/RNF
      genérico por falta de pergunta
- [ ] Todas as 8 seções presentes, mesmo as vazias ("Não se aplica.")
- [ ] Todo RF é testável; todo RNF tem métrica concreta, não um adjetivo
- [ ] Toda tarefa da decomposição nomeia o arquivo/classe/área afetada
- [ ] Nenhuma pergunta em aberto genuinamente relevante ficou sem rotear o card pra
      revisão humana
- [ ] Relatório final nomeia onde o documento foi anexado no card TwinForge

## Antipadrões

- Aceitar pedido para remover/simplificar uma seção do template — cria divergência
  silenciosa entre SRS diferentes e quebra comparabilidade.
- Escrever a seção de contexto/objetivo com nome de classe ou endpoint só porque o pedido
  original já veio técnico — traduzir para negócio mesmo assim.
- Preencher a decomposição de tarefas com item genérico sem arquivo/classe nomeado.
- Prosseguir para decompor em subtarefas com uma pergunta em aberto genuinamente
  relevante ainda sem resposta.
