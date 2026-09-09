# Commercial claims matrix

| Claim | Status | Evidence | Limitation | Wording segura |
|---|---|---|---|---|
| Tenant dedicado e isolamento | WE CAN SELL | rotas/tenant policies, Architecture, synthetic negativo | escala e integridade de banco não são garantia enterprise | “Seu ambiente tem escopo de tenant e acesso controlado; validamos no onboarding.” |
| Admin opera usuários/conteúdo/matrículas | WE CAN SELL | rotas Admin, Feature e synthetic | convite é assistido; acceptance real depende do host | “O Admin opera o escopo contratado com onboarding assistido.” |
| Instructor cria conteúdo e vê roster/progresso | WE CAN SELL | rotas Instructor e testes de ownership | não prometer reporting avançado | “Instrutores podem preparar conteúdo e acompanhar alunos/progresso.” |
| Student consome curso e registra progresso | WE CAN SELL | rotas Student, S02/OPS-04 evidence | depende de enrollment e curso publicado | “Alunos matriculados consomem conteúdo publicado e registram progresso.” |
| Material/media | WE CAN SELL | Learning resources e storage/readiness | provider avançado/CDN não é promessa | “Material e mídia seguem o storage contratado e o fluxo suportado.” |
| Matrícula free/manual/cash | SELL WITH MANUAL OPERATION | checkout cash/manual + enrollment | confirmação comercial é assistida | “Podemos operar matrícula manual/cash durante o piloto.” |
| Billing | SELL WITH MANUAL OPERATION | operação externa/manual | PSP automático, reconciliação e webhook fora | “A cobrança do piloto é combinada fora da plataforma; não é checkout automático.” |
| Onboarding | SELL WITH MANUAL OPERATION | intake/runbook | não é self-service completo | “O primeiro tenant é ativado com acompanhamento do operador.” |
| Student Assessment | DO NOT SELL YET | capability gate/legacy concerns | contrato canônico e jornada independente não estão selados | “Não faz parte do piloto v0.1.” |
| Certificates | DO NOT SELL YET | capability gate e evidência limitada | não há promessa de emissão/verificação comercial | “Certificados estão fora do escopo inicial.” |
| PSP automático/webhooks | DO NOT SELL YET | cash é o preset seguro | adapters e reconciliação dependem de decisão/evidência | “Pagamento automático será uma etapa posterior.” |
| Advanced media/analytics/marketplace | DO NOT SELL YET | specs/tasks e reports | capability futura | não usar em proposta v0.1 |

Se o prospect tratar um item “DO NOT SELL YET” como condição de compra, registrar
`BLOCKING_CAPABILITY`; não contornar com promessa verbal.
