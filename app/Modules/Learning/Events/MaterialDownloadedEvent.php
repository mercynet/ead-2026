<?php

namespace App\Modules\Learning\Events;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialDownload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialDownloadedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly CourseMaterial $courseMaterial,
        public readonly User $user,
        public readonly Course $course,
        public readonly MaterialDownload $download,
    ) {}
}
