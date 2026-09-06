# WS1 — API Contract Convergence — 2026-09-05

## Verdict

`WS1_COMPLETE_WITH_EVIDENCE_PENDING`

The requested contract convergence was implemented and the focused verification passed. The
remaining evidence item is operational: Scribe generation could enumerate the canonical routes but
could not finish because the pre-existing ignored `.scribe/endpoints.cache` is owned by
`nobody:nogroup`. No current runtime/E2E evidence is promoted to `RUNTIME_VERIFIED`.

## Baseline

- D3 had been documented but `/api/v1/auth/*` was not executable; only
  `/api/v1/core/auth/*` existed.
- M-01 returned a hand-built 403 response with code `forbidden` from `EnsureTenantAccess`.
- M-02 used `abort(422, ...)` in seven action paths, bypassing the canonical validation renderer.
- M-03 had manual/top-level success responses in delete/status controllers; auth payloads and the
  public certificate verification projection were already deliberate manual contracts.
- The canonical error contract is rendered centrally as `{data: null, errors: [{code, message}]}`.

## D3 — Auth URL canonicalization

Applied. `app/Modules/Core/Routes/api.php` now registers the same auth route definition twice:

- `/api/v1/auth/*` — canonical public target;
- `/api/v1/core/auth/*` — legacy compatibility during v1.

Both surfaces share `AuthController`, `resolve.tenant.optional`, `api.context`, the same protected
middleware stack, and the same named throttles. Legacy was not removed and no removal date was
introduced. `config/scribe.php` excludes only the legacy auth signatures from primary generated docs;
the canonical routes are included.

## M-01 — Tenant access error contract

Applied. `EnsureTenantAccess` now throws the existing `AccessDeniedException`, allowing the central
renderer to produce HTTP 403 with code `access_denied` and the canonical envelope. A cross-tenant
test asserts status, envelope, and code.

## M-02 — `abort(422)` convergence

Applied to all identified occurrences across seven action paths in Assessment and Learning. Each now throws
`ValidationException::withMessages(...)`, preserving HTTP 422 and the original business message
while producing code `validation_error` through the central renderer. The invalid attempt,
duplicate answer, protected question, and unsafe material-path cases have discriminating assertions
for status, envelope, and code.

## M-03 — Success response convergence

Applied to the identified top-level message responses in enrollment, course, lesson, lesson media,
module, tenant/system category, and admin-user deletion controllers. They now return manual
`JsonResponse` payloads under `data`; no Resource was introduced solely to wrap a message.

Deliberate exceptions remain explicit:

- authentication login/logout/password acknowledgements already use manual `{data: ...}` payloads;
- enrollment lookup without a record intentionally returns `{data: null}`;
- public certificate verification retains its established top-level projection (`valid`,
  `certificate`, `message`) and remains protected by its existing contract tests.

The API conventions spec now records these distinctions so a blind “every controller must return a
Resource” rule is not introduced.

## Files changed by WS1

Implementation/configuration:

- `app/Modules/Core/Routes/api.php`
- `app/Modules/Core/Http/Middleware/EnsureTenantAccess.php`
- `app/Modules/Core/Http/Controllers/Admin/UserController.php`
- `app/Modules/Assessment/Actions/Attempt/FinishAttemptAction.php`
- `app/Modules/Assessment/Actions/Attempt/StartAttemptAction.php`
- `app/Modules/Assessment/Actions/Attempt/SubmitAnswerAction.php`
- `app/Modules/Assessment/Actions/Question/UpdateQuestionAction.php`
- `app/Modules/Assessment/Actions/Questionnaire/DeleteQuestionnaireAction.php`
- `app/Modules/Assessment/Actions/Questionnaire/StoreQuestionnaireAction.php`
- `app/Modules/Learning/Actions/Course/GenerateCourseMaterialDownloadUrlAction.php`
- `app/Modules/Learning/Http/Controllers/Admin/CategoryController.php`
- `app/Modules/Learning/Http/Controllers/Course/CourseController.php`
- `app/Modules/Learning/Http/Controllers/Enrollment/EnrollmentController.php`
- `app/Modules/Learning/Http/Controllers/Lesson/LessonController.php`
- `app/Modules/Learning/Http/Controllers/Lesson/LessonMediaController.php`
- `app/Modules/Learning/Http/Controllers/Module/ModuleController.php`
- `app/Modules/Learning/Http/Controllers/Mzrt/CategoryController.php`
- `config/scribe.php`

Canonical documentation/state:

- `AGENTS.md`
- `docs/STATE.md`
- `docs/ROADMAP.md`
- `docs/specs/00-architecture/api-conventions.md`
- `docs/specs/00-architecture/areas-surfaces.md`
- `docs/specs/10-core-identity/spec.md`
- `docs/specs/10-core-identity/subspecs/auth.md`
- `docs/specs/10-core-identity/tasks.md`

Tests:

- `tests/Architecture/RouteSecuritySurfaceTest.php`
- `tests/Architecture/TenantIsolationSmokeTest.php`
- `tests/Feature/Api/Core/Auth/AuthApiTest.php`
- `tests/Feature/Api/Core/Auth/PasswordResetApiTest.php`
- `tests/Feature/Api/Assessment/AttemptApiTest.php`
- `tests/Feature/Api/Assessment/QuestionApiTest.php`
- `tests/Feature/Api/Learning/Course/CourseMaterialDownloadApiTest.php`
- `tests/Feature/Api/Learning/Enrollment/EnrollmentApiTest.php`
- `tests/Feature/Api/Learning/Catalog/CategoryApiTest.php`
- `tests/Feature/Api/Learning/Course/CourseCrudApiTest.php`
- `tests/Feature/Api/Learning/Lesson/LessonApiTest.php`
- `tests/Feature/Api/Learning/Lesson/LessonMediaApiTest.php`
- `tests/Feature/Api/Learning/Module/ModuleApiTest.php`
- `tests/Feature/Api/Core/Users/AdminUserManagementApiTest.php`

## Verification

Passed:

- Focused architecture tests for route security, tenant isolation, and Scribe auth annotations.
- Focused auth tests: canonical login/me/logout, legacy login, canonical password-forgot, and
  existing legacy password flows — 25 tests, 128 assertions in the auth/password group.
- Focused M-02 tests for inactive attempts, invalid/duplicate answers, protected questions, and
  unsafe material paths.
- Focused M-03 delete response tests across course, enrollment, category, lesson, media, module,
  and admin-user surfaces.
- `./vendor/bin/sail pint --dirty --format agent` — pass.
- `./vendor/bin/sail bin phpstan analyse --memory-limit=1G` — no errors.
- `./vendor/bin/sail artisan route:list --path=api/v1 --json` — canonical and legacy auth routes
  present with matching middleware/throttles.
- `git diff --check` — pass.
- `scripts/ai/verify-changes.sh` — pass; six mapped Architecture invariants are green.
- `graphify update .` — graph refreshed after the code changes.

Attempted but not fully completed:

- `./vendor/bin/sail composer docs` enumerated canonical auth routes and omitted legacy auth routes,
  then failed while cleaning `.scribe/endpoints.cache/17.yaml` because that ignored cache is owned
  by `nobody:nogroup`. Generated ignored artifacts are not part of this change.
- Full `qa:gate` and HTTP E2E against a separately configured runtime were not run.

## Compatibility and remaining risks

- `/api/v1/core/auth/*` remains available and is tested as compatibility; no route removal occurred.
- No API version changed, no throttle or auth middleware was weakened, and no auth endpoint became
  anonymous beyond the existing public login/password routes.
- Consumers of the deliberate certificate verification top-level projection remain dependent on
  that documented exception.
- The `.scribe` ownership issue should be corrected in the environment before treating generated
  documentation as fully verified.

## Evidence state

- `TEST_VERIFIED`: focused contract tests, Pint, PHPStan, route inspection.
- `STATIC_EVIDENCE_ONLY`: central renderer convergence and Scribe configuration before successful
  artifact finalization.
- `EVIDENCE_PENDING`: current runtime/E2E capability evidence and successful Scribe artifact
  generation after the `.scribe` ownership issue is resolved.
- `NEEDS_RECONCILIATION`: `0`.

## Scope boundary

WS2 (Codex Harness Hardening) and WS3 (Legacy Surface & Module Boundary Debt) were not implemented
or modified by this workstream. M-04 remains separately evaluable as previously decided.
