<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CoursePriceHistory;
use Illuminate\Events\Dispatcher;

beforeEach(function (): void {
    $this->tenant = makeTenant();
    [$this->admin, $this->headers] = actingAsUserType(UserType::Admin, $this->tenant);
    $this->course = Course::query()->create([
        'tenant_id' => $this->tenant->id,
        'instructor_id' => $this->admin->id,
        'title' => 'Curso de preço',
        'slug' => 'curso-de-preco-'.$this->tenant->id,
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);
});

it('records actor and values when price changes from free to paid', function (): void {
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, [
        'price_cents' => 9900,
    ], $this->headers)->assertSuccessful();

    $this->assertDatabaseHas('course_price_histories', [
        'tenant_id' => $this->tenant->id,
        'course_id' => $this->course->id,
        'changed_by_user_id' => $this->admin->id,
        'old_price_cents' => 0,
        'new_price_cents' => 9900,
    ]);
    expect(CoursePriceHistory::query()->sole()->changed_at)->not->toBeNull();
});

it('does not record history when price is omitted', function (): void {
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, [
        'title' => 'Novo título',
    ], $this->headers)->assertSuccessful();

    expect(CoursePriceHistory::query()->count())->toBe(0);
});

it('does not record history when price remains unchanged', function (): void {
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, [
        'price_cents' => 0,
    ], $this->headers)->assertSuccessful();

    expect(CoursePriceHistory::query()->count())->toBe(0);
});

it('records every persisted price change in sequence', function (): void {
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, ['price_cents' => 9900], $this->headers)
        ->assertSuccessful();
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, ['price_cents' => 12900], $this->headers)
        ->assertSuccessful();

    expect(CoursePriceHistory::query()
        ->orderBy('id')
        ->get(['old_price_cents', 'new_price_cents'])
        ->map(fn (CoursePriceHistory $history): array => [$history->old_price_cents, $history->new_price_cents])
        ->all())
        ->toBe([[0, 9900], [9900, 12900]]);
});

it('rejects updates and deletes to price history', function (): void {
    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, ['price_cents' => 9900], $this->headers)
        ->assertSuccessful();

    $history = CoursePriceHistory::query()->sole();
    $history->new_price_cents = 12900;

    expect(fn () => $history->save())->toThrow(\LogicException::class, 'Course price history is append-only.');
    expect(fn () => $history->delete())->toThrow(\LogicException::class, 'Course price history is append-only.');
    expect(CoursePriceHistory::query()->find($history->id))
        ->not->toBeNull()
        ->new_price_cents->toBe(9900);
});

it('rolls back course price when recording history fails', function (): void {
    $eventDispatcher = CoursePriceHistory::getEventDispatcher();
    CoursePriceHistory::setEventDispatcher(new Dispatcher($this->app));
    CoursePriceHistory::creating(fn (): never => throw new \RuntimeException('History insert failed.'));

    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->patchJson('/api/v1/learning/courses/'.$this->course->id, [
            'price_cents' => 9900,
        ], $this->headers))->toThrow(\RuntimeException::class, 'History insert failed.');
    } finally {
        CoursePriceHistory::setEventDispatcher($eventDispatcher);
    }

    expect($this->course->fresh()->price_cents)->toBe(0);
    expect(CoursePriceHistory::query()->count())->toBe(0);
});

it('denies cross-tenant price mutation without recording history', function (): void {
    $tenantB = makeTenant();
    [, $headersB] = actingAsUserType(UserType::Admin, $tenantB);

    $this->patchJson('/api/v1/learning/courses/'.$this->course->id, [
        'price_cents' => 9900,
    ], $headersB)->assertNotFound();

    expect($this->course->fresh()->price_cents)->toBe(0);
    expect(CoursePriceHistory::query()->count())->toBe(0);
});
