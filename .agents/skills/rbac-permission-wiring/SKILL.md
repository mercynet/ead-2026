---
name: rbac-permission-wiring
description: Liga uma permission de ponta a ponta — config/permissions.php → seeder → Policy → Gate::define no provider → authorize no controller → teste. Ativa ao criar/renomear/remover permission, escrever ou alterar Policy, definir Gate, ver 403 inesperado (ou 200 que deveria ser 403), e quando PermissionDriftTest / PermissionMetadataShapeTest ficar vermelho. Cobre o teto efetivo por UserType, que faz "dei a role e continua negado" parecer bug.
---

# RBAC Permission Wiring

Uma permission só funciona quando **6 pontos** concordam. Faltando um, o sintoma é 403 silencioso
(ou pior: 200 indevido). Esta skill é o caminho curto e a ordem certa.

Contrato: `AGENTS.md` → invariantes 2 e 6. Matriz de domínio: `docs/specs/00-architecture/rbac.md`.

## Modelo mental (o que decide o quê)

1. **Área** (`area.guard:<área>`) = teto de *superfície* — qual audiência alcança a rota.
2. **Permission** (`domain.resource.action`) = teto de *ação* dentro da superfície.
3. **Policy** = regra de *instância* (pertence ao tenant? é `is_system`? é o próprio usuário?).
4. **Teto efetivo por `UserType`** = filtro **acima** da role (ver abaixo) — a pegadinha do repo.

Nunca emule área com permission, nem instância com permission.

## O teto efetivo (leia antes de debugar 403)

`App\Modules\Core\Models\User` sobrescreve `hasPermissionTo()` e `getAllPermissions()`: além do
Spatie, filtra por `config('permissions')[<nome>]['user_types']`. Consequências:

- **Ter a role não basta.** Se o `user_types` da permission não lista o `UserType` do usuário, a
  permission é invisível — mesmo atribuída direto.
- **Só as superfícies do model User valem.** `PermissionMetadataShapeTest` proíbe, em qualquer arquivo
  de `app/` fora do `User.php`: `hasDirectPermission`, `hasAnyDirectPermission`,
  `hasAllDirectPermissions`, `getDirectPermissions`, `getPermissionsViaRoles`, `getPermissionNames`,
  `permissions()`. Use `getAllPermissions()->contains('name', '<perm>')` ou `hasPermissionTo()`.
- **`developer` é obrigatório** em `user_types` de toda permission (invariante do mesmo teste).

## Passo 1 — declarar em `config/permissions.php`

```php
'learning.categories.update' => [
    'label' => 'Atualizar categoria',
    'user_types' => ['developer', 'admin'],
],
```

- Nome: `domain.resource.action`, recurso no plural, ação em kebab-case (`update-self`,
  `update-password`). Segmento extra é permitido quando qualifica escopo
  (`learning.categories.system.manage`) — use com parcimônia.
- `user_types` = quem **pode** receber, não quem recebe hoje. Mais restrito é mais seguro: alargar
  depois é 1 linha; estreitar depois é breaking change de contrato.
- Remover permission: apague do config **e** do código no mesmo commit (o teste de drift pega o resto).

## Passo 2 — não escreva seeder

`PermissionsSeeder` cria as rows a partir de `array_keys(config('permissions'))`.
`RolesSeeder` deriva uma role global por `UserType` com exatamente as permissions que o listam.
**Lista manual em seeder é violação do invariante 6.** Depois de mexer no config:

```bash
./vendor/bin/sail artisan db:seed --class=Database\\Seeders\\PermissionsSeeder
./vendor/bin/sail artisan db:seed --class=Database\\Seeders\\RolesSeeder
```

Em teste, `seedRbac()` (helper de `tests/Pest.php`) já roda os dois — e `actingAsUserType()` chama
`seedRbac()` sozinho.

## Passo 3 — Policy no módulo dono do recurso

Padrão real (`app/Modules/Learning/Policies/CategoryPolicy.php`):

```php
public function update(User $user, ?Tenant $tenant, Category $category): bool
{
    if ($user->isDeveloper()) {
        return $category->is_system
            ? $user->getAllPermissions()->contains('name', 'learning.categories.system.manage')
            : $user->getAllPermissions()->contains('name', 'learning.categories.update');
    }

    if ($tenant === null || ! $user->belongsToTenant($tenant)) {
        return false;
    }

    if ($category->is_system || (int) $category->tenant_id !== (int) $tenant->id) {
        return false;
    }

    return $user->getAllPermissions()->contains('name', 'learning.categories.update');
}
```

Invariantes de estilo: `?Tenant $tenant` chega nulo (rota sem tenant) → **negue**, não assuma;
pertencimento sempre por `belongsToTenant()`; recurso de sistema exige permission de sistema;
nunca `where('tenant_id')` dentro da policy.

## Passo 4 — `Gate::define` no `<M>ServiceProvider`

Duas formas em uso, ambas válidas:

```php
// Policy-backed direto (args padrão: user, tenant)
Gate::define('learning.categories.list', [CategoryPolicy::class, 'list']);

// Closure quando a ability recebe argumento extra (id, model) — sufixo `-check` por convenção
Gate::define('learning.lessons.update-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
    return app(LessonPolicy::class)->update($user, $tenant, $lesson);
});
```

Abilities registradas via `Gate::define` **não** precisam existir em `config/permissions.php`
(`PermissionDriftTest` extrai as definições dos providers). Mas toda **string de permission** usada em
`->authorize()`, `hasPermissionTo()`, `->can()` ou `getAllPermissions()->contains('name', ...)`
precisa estar declarada no config — senão o drift test aponta órfã.

## Passo 5 — autorizar no controller (um estilo só)

```php
Gate::forUser($ctx->requiredUser())->authorize('learning.categories.update-check', [$tenant, $category]);
```

Nunca `Gate::check(...) { abort(403) }`, nunca autorizar dentro da Action, nunca `if` de `UserType`
no controller (isso é área ou policy).

## Passo 6 — testar as três negativas

```php
[$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);
```

Cubra: (a) persona autorizada → 2xx; (b) persona da mesma área **sem** a permission → 403
`access_denied` via `assertApiErrorEnvelope`; (c) outro tenant → `assertTenantIsolation`.
Se a permission tem `user_types` restrito, teste também a persona fora do teto — é o caso que o
Spatie sozinho deixaria passar.

## Checklist

- [ ] Entrada no config com `label` + `user_types` contendo `developer`.
- [ ] Nenhuma lista manual de permission/role em seeder.
- [ ] Policy no módulo dono, tenant nulo → nega, pertencimento por `belongsToTenant()`.
- [ ] `Gate::define` no provider do módulo; ability com args usa closure `-check`.
- [ ] `Gate::forUser(...)->authorize(...)` no controller, uma vez, antes da Action.
- [ ] Testes de 2xx + 403 + isolamento; permission removida saiu do config e do código.

## Verificar

```bash
./vendor/bin/sail artisan test --compact --testsuite=Architecture
./vendor/bin/sail artisan test --compact --filter=Permission
```

## Debug de 403 na ordem certa

1. É **área**? Código do erro `area_forbidden` → problema de rota/guard (skill `api-area-routing`),
   não de permission.
2. `user_types` do config inclui o `UserType` do usuário? Se não, é o teto efetivo — não é bug.
3. A role foi semeada depois da última mudança do config? (`seedRbac()` / seeders.)
4. A Policy recebeu `$tenant` nulo (rota sem `resolve.tenant*`/`api.context`)?
5. A ability do `authorize()` existe (config ou `Gate::define`) e recebe os args na ordem certa?
