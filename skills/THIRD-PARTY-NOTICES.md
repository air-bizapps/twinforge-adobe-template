# Avisos de terceiros

Parte do conteúdo de algumas skills `adobe-*` deste diretório foi adaptada (reescrita, corrigida para
Magento 2.4.8 e fundida às skills existentes) a partir de dois projetos com licença MIT:

- [maxnorm/magento2-agent-skills](https://github.com/maxnorm/magento2-agent-skills), commit `b3a20f96`
- [rubenzantingh/claude-code-magento-agents](https://github.com/rubenzantingh/claude-code-magento-agents),
  commit `faa1aac5`, de onde o primeiro foi derivado

Análise feita antes da adoção (2026-10-21):
- **Segurança:** só Markdown, sem scripts e sem campos que liberem ferramentas; links só para documentação oficial.
- **Ressalvas técnicas:** os exemplos de UI Component da fonte estavam incompletos (sem `dataSource`/`template`) e o índice falava só de Elasticsearch. Nada disso foi copiado.

## De onde veio cada parte

Cada skill externa virou uma skill aqui, `adobe-<nome original>`. Parte do conteúdo de 15 delas também foi fundida numa skill que os agentes já usavam; a skill dedicada aponta para ela em vez de repetir.

| Skill externa | Skill aqui | Também fundida em |
|---|---|---|
| magento-alpine-specialist | `adobe-magento-alpine-specialist` | - |
| magento-api-developer | `adobe-magento-api-developer` | `adobe-magento-backend-standards` |
| magento-cache-analyst | `adobe-magento-cache-analyst` | - |
| magento-catalog-specialist | `adobe-magento-catalog-specialist` | - |
| magento-code-reviewer | `adobe-magento-code-reviewer` | - |
| magento-configuration-specialist | `adobe-magento-configuration-specialist` | `adobe-create-config` |
| magento-cronjob-developer | `adobe-magento-cronjob-developer` | `adobe-create-cronjob` |
| magento-css-specialist | `adobe-magento-css-specialist` | `adobe-magento-frontend-standards` |
| magento-deployment-engineer | `adobe-magento-deployment-engineer` | - |
| magento-environment-engineer | `adobe-magento-environment-engineer` | - |
| magento-feature-developer | `adobe-magento-feature-developer` | `adobe-commerce-standards` |
| magento-frontend-developer | `adobe-magento-frontend-developer` | `adobe-magento-frontend-standards` |
| magento-hyva-specialist | `adobe-magento-hyva-specialist` | - |
| magento-index-analyst | `adobe-magento-index-analyst` | `adobe-magento-backend-standards` |
| magento-issue-debugger | `adobe-magento-issue-debugger` | - |
| magento-knockout-specialist | `adobe-magento-knockout-specialist` | `adobe-magento-frontend-standards` |
| magento-local-environment-specialist | `adobe-magento-local-environment-specialist` | - |
| magento-luma-specialist | `adobe-magento-luma-specialist` | `adobe-magento-frontend-standards` |
| magento-magewire-specialist | `adobe-magento-magewire-specialist` | - |
| magento-model-developer | `adobe-magento-model-developer` | `adobe-create-entity` |
| magento-module-developer | `adobe-magento-module-developer` | `adobe-commerce-standards` |
| magento-order-specialist | `adobe-magento-order-specialist` | - |
| magento-performance-analyst | `adobe-magento-performance-analyst` | - |
| magento-php-specialist | `adobe-magento-php-specialist` | `adobe-commerce-standards` |
| magento-requirejs-specialist | `adobe-magento-requirejs-specialist` | `adobe-magento-frontend-standards` |
| magento-security-analyst | `adobe-magento-security-analyst` | - |
| magento-theme-developer | `adobe-magento-theme-developer` | `adobe-magento-frontend-standards` |
| magento-ui-component-developer | `adobe-magento-ui-component-developer` | - |
| magento-upgrade-specialist | `adobe-magento-upgrade-specialist` | - |
| magento-xml-specialist | `adobe-magento-xml-specialist` | `adobe-commerce-standards` |

## Licenças

### rubenzantingh/claude-code-magento-agents

```
MIT License

Copyright (c) 2025 Ruben Zantingh

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### maxnorm/magento2-agent-skills

```
MIT License

Copyright (c) 2026 MN

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
