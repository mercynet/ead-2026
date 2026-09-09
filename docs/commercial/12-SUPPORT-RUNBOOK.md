# Support runbook — paid pilot assistido

O piloto usa suporte assistido, sem prometer SLA enterprise. Owner real, canal e janela são inputs
externos e devem aparecer no checklist antes da abertura.

## Canal e janela

`HUMAN_PENDING`: definir um canal primário do cliente, backup e horário comercial local. Não usar
secrets ou PII em chats; pedir referência de tenant/usuário e request ID.

| Severity | Exemplo | Resposta operacional esperada | Owner |
|---|---|---|---|
| P1 | serviço indisponível, suspeita de perda/vazamento de dados | reconhecer imediatamente na janela, conter tráfego, preservar evidência, atualizar até contenção | platform + support |
| P2 | login, matrícula ou consumo essencial bloqueado | priorizar no mesmo período de suporte, oferecer workaround seguro, registrar impacto | support + platform |
| P3 | problema funcional contornável | registrar, orientar workaround, planejar correção | support |
| P4 | dúvida, melhoria ou pedido | responder quando possível, classificar feedback, sem promessa | support/account |

## Fluxo

1. Support registra customer, tenant, severity, hora, impacto, passo para reproduzir e request ID.
2. Não pede senha/token; usa repro seguro e o menor acesso necessário.
3. P1/P2 avisam platform owner e congelam mudanças relacionadas.
4. Workaround precisa ser reversível e seguir API/Actions; nunca SQL manual para criar efeito de negócio.
5. Fechar só após confirmação do cliente ou evidência de recovery; converter aprendizado em [feedback](16-FEEDBACK-INTAKE.md).

## Escala

Incidente de dados/indisponibilidade → platform owner; backup/restore → backup owner; dúvida de
conteúdo → Admin/Instructor; pedido comercial → account owner. Assuntos jurídicos vão para
`LEGAL_REVIEW_REQUIRED`.
