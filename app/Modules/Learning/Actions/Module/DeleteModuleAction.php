<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;

class DeleteModuleAction
{
    public function handle(CourseModule $module): void
    {
        $module->delete();
    }
}
