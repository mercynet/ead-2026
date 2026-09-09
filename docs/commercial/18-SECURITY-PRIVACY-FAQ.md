# Security & privacy FAQ para clientes

Respostas limitadas ao que o código/testes/ops comprovam. Questões jurídicas são
`LEGAL_REVIEW_REQUIRED`.

### Como os tenants são separados?

As rotas e Actions usam contexto/escopo de tenant, RBAC e policies; os testes de isolamento e o
synthetic cobrem negativas cross-tenant. Isso não é promessa de escala ou imunidade a qualquer
defeito futuro.

### Quem pode acessar?

Access é limitado por autenticação, área/persona, tenant e permission/ownership. O operador faz o
onboarding; o cliente deve controlar seus usuários e informar revogações pelo canal de suporte.

### Onde ficam arquivos?

Storage privado e persistência são checados por readiness/backup. O comportamento concreto depende
do storage contratado e do host provisionado; CDN/media provider avançado não faz parte do piloto.

### Existem backups e recuperação?

O processo exige backup DB+storage, checksum, monitor de idade, cópia remota e rehearsal de restore.
No host real, remote destination, retenção, RPO/RTO e receipt precisam ser confirmados antes de abrir.

### Como passwords/secrets são tratados?

Não compartilhar secrets no intake, chat ou receipt. O validator rejeita placeholders e o receipt
registra somente identificadores/resultados. Política jurídica de retenção e resposta a incidente:
`LEGAL_REVIEW_REQUIRED`.

### O suporte acessa dados do cliente?

Somente o menor acesso necessário para diagnosticar, via fluxo combinado; request ID e referências
seguras são preferidos. Não enviar senha/token. Definir suporte owner/canal no aceite.

### Há garantia LGPD/compliance?

Não declarar conformidade jurídica automática. DPA, bases legais, retenção, residência, subprocessadores
e resposta a titulares precisam de `LEGAL_REVIEW_REQUIRED`.
