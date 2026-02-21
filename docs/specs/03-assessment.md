# Domain Spec: Assessment (API-First)

## Visão Geral
Gerenciará formas de validação de conhecimento (Quizzes/Provas) dentro de aulas e também a emissão formal, revogação e validação de diplomas/certificados com base nos critérios de conclusão das Trilhas de Catalogo (Domínio isolado, conversando via Event Driven com Catalog).

## 1. Entidades Principais
### Quizzes (`Quiz`, `QuizQuestion`, `QuizQuestionOption`)
- **Quiz:** Anexado a uma Lesson (Aula tipo "Prova"). Configurações de expiração, shuffle (randomize_questions) ativadas. Suportará nota de corte (`passing_score`) e tempo limite.
  - **Quizzes Intermediários:** Podem ser posicionados em qualquer parte da trilha do curso (ex: após as 5 primeiras aulas), não se limitando apenas ao final do curso.
- **QuizQuestion:** Alternativas variadas e ativas/inativas. A estrutura deve prever escalabilidade para plugins pagos (ex: Múltipla escolha com várias corretas, V/F, dissertativas, ordenação, hotspots).
- **Banco de Questões:** Questões poderão ser construídas num "Pool" e reaproveitadas para gerar simulados dinâmicos (randomizados de um banco maior).

### Execução de Prova (`QuizAttempt`, `QuizAttemptAnswer`)
- **QuizAttempt:** A prova que um aluno começou a fazer. Guarda Score Final e um *Snapshot* gigante da hora que o aluno começou/terminou a prova, travando o nome do curso e lições para não invalidar tentativas passadas caso um Admin mude o nome do curso amanhã.
  - Guarda o status de progressão (e.g., `paused`, `finished`) permitindo ao aluno "Salvar Rascunho".
  - Validações "Anti-cheating": Controle estrito de `attempt_count` limitando X vezes por dia.
- **QuizAttemptAnswer:** Resposta isolada ativando correções automáticas. Registra o tempo (`time_spent_seconds`) por questão para métricas/heatmaps da turma.

### Documentos (`Certificate`, `CertificateTemplate`)
- **CertificateTemplate:** Metadados, HTML configurável e lista de variáveis ativas (student_name, course_name).
- **Certificate:** O documento gerado, contendo número único de rastreio (`CERT-2026-XYZ`), Hashes MD5/SHA256, Status (issued, revoked) e UUID.

## 2. Endpoints Principais (JSON)
*Base URL: `api/v1/assessment`*

### Endpoint de Exames (Aluno)
- `GET /quizzes/{id}`: Metadados globais da Prova (quantas questões, title).
- `POST /quizzes/{id}/attempts`: Inicia a prova e gera um State de Draft no banco (Grava Snapshot das tabelas base para integridade). Gera o ID da sessão da Prova.
- `GET /attempts/{attempt_id}/questions`: Puxa a lista randômica do escopo.
- `POST /attempts/{attempt_id}/answers`: Envia em bulk ou singular as marcações do usuário na prova, salva na base da *Attempt*.
- `POST /attempts/{attempt_id}/finish`: Encerra a prova, calcula Score e avalia Aprovado/Reprovado via Action.

### Diplomas e Validações
- `GET /certificates`: Lista carteira de certificados do usuário logado.
- `GET /certificates/{code}/pdf`: Faz o stream binário ou redirecionamento do S3 do Certificado gerado em PDF/Imagem.
- `GET /public/verification/{validation_hash}`: **Endpoint 100% público** (Sem Auth) onde RHs de empresas batem para checar se o link/QR Code do certificado do aluno EAD de fato existe e está Ativo (Não `revoked`).

## 3. Regras Críticas e Padrões de Arquitetura
- **Imutabilidade de Avaliações:** Todas as tentativas (`Attempt`) são *Append-Only* / Imutáveis. Uma vez finalizado o Quiz, Snapshot guardará se alterarem perguntas amanhã.
- **Sincronia Assíncrona de Emissão:** 
  - Ao finalizar 100% do progresso de Cursos (Detectado no Evento `EnrollmentCompletedEvent` escutado de fora), despachamos o Observer Assíncrono para fila (Horizon/Redis) para invocar o Action `IssueCertificateAction`. Ele gerará a tela Base64/PDF e armazenará via AWS, batendo na master. Não bloqueia a navegação de quem acabou de bater 100% do vídeo.
- **Revogação de Diplomas:** Status de Certificados poderão ser atualizados de `issued` para `revoked` pelo `Tenant_Admin` se fraude for detectada, inviabilizando na hora o Endpoint de `verification`.
