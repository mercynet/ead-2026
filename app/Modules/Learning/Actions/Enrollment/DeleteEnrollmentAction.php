<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;

class DeleteEnrollmentAction
{
    public function handle(Enrollment $enrollment): void
    {
        $enrollment->fill([
            'status' => 'cancelled',
        ]);
        $enrollment->save();
    }
}
