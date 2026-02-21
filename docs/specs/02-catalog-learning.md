# Domain Spec: Catalog & Learning (API-First)

## Status de Implementação

### ✅ Implementado
- [x] Models: `Course`, `Category`, `CourseModule`, `Lesson`, `Enrollment` com relacionamentos
- [x] Migrações com soft deletes, tenant_id, hierarchical categories
- [x] Sistema de categorias com `is_system` e `parent_id`
- [x] Regra de antiduplicação: tenant não pode criar categoria com mesmo nome de categoria padrão
- [x] Pivot `category_course` tenant-aware
- [x] Policies: `CategoryPolicy`, `CoursePolicy`, `CourseModulePolicy`, `EnrollmentPolicy`, `LessonPolicy`
- [x] Validação de categoria duplicada no `StoreCategoryAction`

### ✅ Endpoints Implementados
- [x] `GET /api/v1/learning/catalog/courses` - Lista cursos publicados com filtros (category, is_free, is_featured)
- [x] `GET /api/v1/learning/catalog/courses/{slug}` - Detalhe do curso com módulos e aulas
- [x] `GET /api/v1/learning/catalog/categories` - Lista categorias (sistema + tenant)
- [x] `POST /api/v1/learning/catalog/categories` - Cria categoria (tenant ou sistema se developer)

### ⏳ Pendente
- [ ] `GET /api/v1/learning/courses/{id}/enrollment` - Situação da matrícula
- [ ] `GET /api/v1/learning/courses/{id}/modules` - Árvore do curso com tracking
- [ ] `GET /api/v1/learning/lessons/{id}` - Aula com pre-signed URL
- [ ] `POST /api/v1/learning/lessons/{id}/progress` - Heartbeat de progresso
- [ ] `CourseMaterial` model e download tracking
- [ ] `LessonProgress` model e cálculo de progresso
- [ ] Eventos de domínio (`LessonCompletedEvent`)
- [ ] Job de cálculo assíncrono de progresso
- [ ] Ratings (1-5 estrelas, like/dislike)
- [ ] Pre-signed URLs para mídias (AWS S3, Vimeo)
- [ ] Preview de cursos draft para instrutores/admins

---

## Visão Geral
Domínio responsável por organizar o vitrine de cursos (Catálogo), a montagem estrutural dos cursos (Trilhas, Módulos, Aulas, Materiais) e a jornada de execução e progresso feita pelo aluno (Enrollments, Progress).

## 1. Padrões Arquiteturais
- **Divisão DTO:** Os payloads de leitura devem separar "Dados Frios" (Catálogo: Título do curso, Grade Curricular) de "Dados Quentes" (Progresso Pessoal do Aluno logado). Isso permite cachear a camada do modelo (`Course`) no Redis e consultar o progresso via banco.
- **Command/Query Actions (Obrigatório):**
  - Controllers de API devem ter métodos explícitos (`index`, `show`, `store`, etc.), sem `__invoke` como padrão.
  - Regras de negócio devem ser extraídas para `app/Actions/Learning/<Resource>/...`.
  - Cada método de controller deve aplicar autorização (Gate/Policy) antes de chamar a Action.
- **Paginação em Listagens:** Toda listagem deve usar `cursorPaginate`, com Resource Collection retornada diretamente no controller.
- **Controller Lean (obrigatório):** proíbe checks repetidos de contexto/infra no método (`tenant`, `auth`, payload de erro). Isso deve ficar em middleware, FormRequest e exceções centralizadas.
- **Media Decentralizada:**
  - Requisições das mídias (Vídeos e Arquivos de Material) resolverão `Pre-signed URLs` apontando para o Storage configurado pelo Tenant (ex: AWS S3 ou integração via Vimeo na API). O back-end não fará *proxy pass* binário de grandes arquivos. Carga aliviada no servidor da API.

### 1.1 Guardrails de Resposta e Tenant
- Não construir manualmente, em múltiplos lugares, payload de `tenant_not_resolved`.
- Usar exceção de domínio padronizada e render global para manter consistência.
- Quando não houver `JsonResource`/`ResourceCollection`, retornar `data` direto no payload.
- Evitar wrappers redundantes como `data.user`, `data.course`, `data.category` para respostas manuais.
- Não retornar `meta` vazio (`'meta' => []`). `meta` só existe quando tiver dados reais de metadados.

## 2. Entidades Principais
### Catalog (`Course`, `Category`, `CourseModule`)
- **Course:** Agrupador raiz. Status (`published`, `draft`, `archived`), `price`, `access_days`. Soft deletes globais. Atrelado a Categorias. Multiplos relacionamentos com Módulos.
  - O `access_days` deve prover uma lista fechada de presets (30 dias, 90 dias, 180 dias, 365 dias, e 0 para Vitalício). 
- **CourseModule:** Organiza a ordem das Aulas. Quando um Instrutor for criar um módulo, o filtro de Categorias exibirá apenas categorias onde o instrutor já possui cursos.
- **Category:** Estrutura hierárquica (aninhamento infinito via `parent_id`), com dois tipos de ownership:
  - **Categoria Padrão do Sistema** (`tenant_id = null`, `is_system = true`): disponível para todos os tenants e **editável somente por `developer`**.
  - **Categoria do Tenant** (`tenant_id = current_tenant`, `is_system = false`): criada e gerida pelo próprio tenant.

#### Regra Obrigatória: Categorias Padrão Globais e Antiduplicação
- Haverá um conjunto de categorias padrão globais, pré-cadastradas pela plataforma, reutilizáveis por todos os tenants.
- `tenant_admin` e demais usuários de tenant podem **usar** categorias padrão em seus cursos, mas **não podem criar/editar/excluir** categorias padrão.
- O tenant pode criar categorias próprias, porém **não pode criar categoria com mesmo nome (normalizado) de qualquer categoria padrão global**.
  - Exemplo: se já existe categoria padrão `Desenvolvimento de Software`, nenhum tenant pode criar outra com esse mesmo nome.
  - Exemplo: `Desenvolvimento de Programas` pode ser criada por um tenant.
- O isolamento entre tenants permanece: categorias próprias iguais entre tenants diferentes são permitidas, desde que não conflitem com categorias padrão globais.
- A validação de duplicidade deve usar nome normalizado (case-insensitive, sem espaços excedentes e sem acentuação para comparação).

#### Relação Curso x Categoria
- A relação entre cursos e categorias deve ser feita em tabela pivô tenant-aware contendo `tenant_id`, `course_id`, `category_id`.
- Regras da pivô:
  - `course_id` deve referenciar curso do mesmo `tenant_id`.
  - `category_id` pode referenciar:
    - categoria padrão (`tenant_id = null`), ou
    - categoria do mesmo `tenant_id` do curso.
  - Nunca permitir vínculo com categoria de outro tenant.

### Learning (`Lesson`, `CourseMaterial`)
- **Lesson:** Conteúdo da aula. Mídia vinculada (`LessonMedia`).
- **CourseMaterial:** Arquivos auxiliares (PDFs, PPTXs).

### Progresso e Matrícula (`Enrollment`, `LessonProgress`)
- **Enrollment:** Matrícula de um usuário (Student) a um curso. Registra expiração de acesso, progresso total (0-100%). Funciona como Agreggate Root para cálculos de conclusão.
- **LessonProgress:** Tracking granular por vídeo/aula.

## 3. Endpoints Principais (JSON)
*Base URL: `api/v1/learning`*

### Catálogo e Descoberta
_Acessíveis de forma pública ou apenas usuários logados (depende da flag do tenant)_
- `GET /catalog/courses`: Lista de cursos publicados. Suporta filtros dinâmicos por Category, is_free, is_featured. **Regra Forte**: Não devem aparecer cursos que o Aluno logado já comprou.
- `GET /catalog/courses/{slug}`: Retorna toda a matriz curricular do curso (Módulos e Lições) + DTO público completo (preço, descrição) formatado perfeitamente para montagem de uma Landing Page rica pelo Front-end / App.

### Consumo e Aulas (Arestas Autenticadas)
- `GET /courses/{id}/enrollment`: Verifica a situação da matrícula (Ativa, Expirada). Carrega o progresso acumulado (`progress_percentage`).
- `GET /courses/{id}/modules`: A árvore do curso já misturada com o Tracking de conclusão (Quais aulas estão lidas).
- `GET /lessons/{id}`: Resolve a Aula. Retorna `Pre-signed URL` da masterização de vídeo e links temporários AWS para downloads de `CourseMaterial`.
- `POST /lessons/{id}/progress`: (Heartbeat payload) Frontend envia `{"duration_watched": 120, "is_completed": true}` em intervalos ou ao término para atualizar o `LessonMediaProgress`.

## 4. Regras de Negócio e Lógica Crítica
- **Access Control na Lição (Middlewares):** 
  - Regra: O usuário só tem acesso à mídia / material se o curso for gratuito (`course.is_free = true`), ou se a aula for degustação (`lesson.is_free = true`), ou se o aluno tiver um `Enrollment` ativo e não expirado para o curso pago.
  - Alunos com `Enrollment.status = expired` podem continuar vendo a vitrine do curso (`canViewCourse()` = true), mas não podem consumir os vídeos/arquivos (`canAccessPaidContent()` = false).
- **Preview de Conteúdo (Drafts):** Cursos no estágio `draft` não podem ser acessados por alunos comuns. Eles possuem uma rota restrita de Preview acessível apenas pelos Instrutores donos do curso, Tenant Admins e Super Admins.
- **Tipos de Mídia e Estratégia de Progresso:**
  - Aulas suportarão múltiplos provedores via Enum `MediaType` (Vídeos do YouTube/Vimeo/AWS, Live Streaming, Áudio, Documentos PDF).
  - O cálculo de tempo assistido obedece a uma `ProgressStrategy` configurável por aula (ex: `80_percent`, `full_duration`, `manual` ou `time_based`). O Back-end guarda sessões de visualização e emite Evento ao bater a meta.
- **Engajamento e Materiais Extras:**
  - **Ratings:** Alunos podem dar estrelas (1-5) e like/dislikes para Cursos e Aulas. O sistema fará "rollup" das notas agregando num cache `RatingStats` global e também mapeando o top ranking por Tenant.
  - **Materiais Opcionistas:** Cada `CourseMaterial` que o instrutor subir (limitado a 50MB, guardado nas pastas do tenant) terá rastreamento granular de downloads (`MaterialDownload`), alimentando a agregação de uso (`MaterialStats`).
- **Matrículas Manuais por Instrutores:**
  - Uma flexibilidade concedida por um switch ativado pelo Tenant Admin. Instrutores podem pesquisar e matricular alunos em seus próprios cursos manualmente.
  - Se configurada a cobrança como `external` (Ex: aluno pagou PIX na conta física do instrutor), a matrícula pode cair como `pending` e notificar o Tenant Admin para aprovação, mantendo controle de evasão de receitas centralizadas.
- **Cálculo de Progresso Assíncrono:**
  - Ao atualizar `is_completed: true` de um `Lesson`, disparamos um evento de Domínio no Laravel (`LessonCompletedEvent`).
  - Um `Job` / *Listener* roda em background para recalcular em tempo real a grade baseada em count de Módulos (se o aluno fez 2 de 4 aulas, 50% `Enrollment`), ativando a Flag de Complete (via Eventos, engatilhando o módulo de Certificado posteriormente).
- **Decoupled Media:** Integrações de VIMEO ou AWS devolvem IDs, e a camada do App Laravel irá envelopar transformando em um Player URL configurável de acordo com as chaves globais da plataforma ou chaves do Tenant caso este tenha pago o recurso Adicional "Private External Storage" nos Plugins.
