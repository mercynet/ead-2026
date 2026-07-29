<?php

namespace App\Modules\Learning\Contracts;

/**
 * @phpstan-type CourseCheckoutSnapshot array{title: string, slug: string}
 */
readonly class CourseCheckoutOffering
{
    /**
     * @param  CourseCheckoutSnapshot  $snapshot
     */
    public function __construct(
        public int $courseId,
        public int $priceCents,
        public array $snapshot,
        public string $purchaseCycleKey,
        public bool $isEligible,
    ) {
        $this->type = 'course';
    }

    public string $type;
}
