# Pilot success criteria

Medir antes do piloto, no D7 e no exit. Se o denominador não existir, marcar `UNVERIFIED`, não
inventar percentual.

| Dimensão | Critério objetivo | Evidência mínima |
|---|---|---|
| Technical | readiness e synthetic passam nos dias observados; nenhum tenant leakage; nenhum dado perdido; restore rehearsal confiável | receipts, incident log, smoke cross-tenant, backup/restore reference |
| Operational | onboarding no esforço acordado; manual tasks contadas; suporte não dominado por P1/P2 | tempo por fase, tarefas manuais, tickets por severity |
| Product/Admin | Admin consegue operar usuários, course, publicação e matrícula com ajuda prevista | checklist smoke e confirmação do Admin |
| Product/Instructor | Instructor prepara conteúdo e consulta roster/progresso | course reference, ação e confirmação |
| Product/Student | estudantes entram, consomem conteúdo e usam progress | enrollment, acessos e progress observados |
| Commercial | cliente aceita continuar/pagar, há expansão ou referral plausível e objeções são entendidas | exit interview, decisão do cliente, próximos passos |

## Gate sugerido

`PASS` exige zero incidente de segurança/perda de dados, todos os fluxos de persona essenciais
operados, suporte dentro do acordo do piloto e decisão comercial explícita. Uma capability faltante
é `deal blocker` quando impede o resultado comprado; caso contrário é feature request.
