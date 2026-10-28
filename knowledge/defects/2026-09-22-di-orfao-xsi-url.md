---
classe: [C2, C1]
card: BUN-14079
origem: 35cbae78 (agente) → e74b461f (fix)
detector: [XML-XSD, CLASS-MISSING]
status: promovido
---
**Sintoma:** `setup:upgrade` e `cache:flush` param com "Invalid Document" em qualquer ambiente.
**Errado:** `etc/adminhtml/di.xml` esquecido no módulo pai após mover classes para outro módulo,
com `<item xsi:type="url">` (só existe em UI Component) e classes que já não existem ali.
**Certo:** apagar o arquivo órfão; a config já existia no módulo novo.
