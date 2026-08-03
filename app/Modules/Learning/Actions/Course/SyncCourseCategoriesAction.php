<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class SyncCourseCategoriesAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * @param  array<array-key, array{id: int, is_featured?: bool|null}>  $categories
     */
    public function handle(Course $course, array $categories): Course
    {
        return $this->database->transaction(function () use ($course, $categories): Course {
            $lockedCourse = Course::query()
                ->whereKey($course->id)
                ->lockForUpdate()
                ->firstOrFail();

            $categories = array_values($categories);
            $selectable = $this->selectableCategories($lockedCourse, $categories);

            $lockedCourse->categories()->detach();

            foreach ($categories as $position => $category) {
                $lockedCourse->categories()->attach($selectable[(int) $category['id']]->id, [
                    'tenant_id' => $lockedCourse->tenant_id,
                    'sort_order' => $position + 1,
                    'is_featured' => (bool) ($category['is_featured'] ?? false),
                ]);
            }

            return $lockedCourse->load('categories');
        });
    }

    /**
     * Categorias vinculáveis ao curso: de sistema ou do mesmo tenant. Soft-deletadas
     * ficam fora pelo global scope e caem no mesmo erro de inexistente.
     *
     * @param  list<array{id: int, is_featured?: bool|null}>  $categories
     * @return array<int, Category>
     */
    private function selectableCategories(Course $course, array $categories): array
    {
        $ids = array_map(static fn (array $category): int => (int) $category['id'], $categories);

        /** @var array<int, Category> $found */
        $found = Category::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();

        foreach ($ids as $id) {
            if (! array_key_exists($id, $found)) {
                throw ValidationException::withMessages([
                    'categories' => "Category {$id} was not found.",
                ]);
            }

            $categoryTenantId = $found[$id]->tenant_id;

            if ($categoryTenantId !== null && $categoryTenantId !== $course->tenant_id) {
                throw ValidationException::withMessages([
                    'categories' => "Category {$id} belongs to another tenant.",
                ]);
            }
        }

        return $found;
    }
}
