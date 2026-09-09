# Tenant activation runbook

Fluxo: `lead accepted → tenant provision → Admin invited → Course prepared → Instructor prepared →
students enrolled → smoke → pilot opened`.

| Fase | Platform operator | Customer Admin | Instructor | Support operator | Evidence / recovery |
|---|---|---|---|---|---|
| Lead accepted | valida ICP, score, escopo e exclusões | confirma owner e contatos | — | registra canal | intake aprovado; se escopo exigir capability excluída, parar |
| Tenant provision | cria tenant pelo caminho MZRT/API; confere slug, timezone, locale e capability | confirma dados | — | observa primeiro acesso | tenant ID + resposta; em falha, não repetir cegamente, verificar idempotência e limpar somente o que o contrato permitir |
| Admin invited | usa convite da API; não compartilha senha | aceita convite e define credencial | — | acompanha expiração | invitation reference; se parcial, revogar/reemitir pelo fluxo suportado |
| Course prepared | confere storage e escopo | cria/ajusta course | pode revisar conteúdo | valida smoke editorial | course draft → modules → lessons → media/material → publish; se falhar, manter draft |
| Instructor prepared | associa usuário/owner pelo fluxo suportado | confirma pessoa | revisa e testa autoria | acompanha permission issues | course/instructor reference; sem inserir FK direto |
| Students enrolled | prepara lotes | fornece cohort autorizado | — | monitora convites/matrículas | enrollment IDs/contagem; desfazer somente via API e guardar relatório |
| Smoke | executa ou coordena | login e operação básica | abre lesson e confere roster | registra anomalias | Admin→Instructor→Student, progress e isolamento; qualquer falha bloqueia abertura |
| Pilot opened | sela checklist e receipt | confirma data de abertura | inicia rotina de conteúdo | assume suporte | acceptance reference; rollback = fechar abertura, preservar dados e recuperar se necessário |

## Regras de parcialidade

- Nunca provisionar por SQL direto para “destravar” onboarding.
- Cada etapa deve ter referência/contagem antes de avançar.
- Se a falha for depois de tenant criado, identificar se a operação é repetível antes de repetir.
- Em incidente de dados, congelar alterações, preservar evidência e usar restore/runbook da
  plataforma; não apagar tenant para esconder falha.
- O operador é responsável por configuração; Admin por dados e pessoas do cliente; Instructor por
  conteúdo; Support por triagem e comunicação.
