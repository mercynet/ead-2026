<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;

class UpdateModuleAction
{
    public function handle(CourseModule $module, array $attributes): CourseModule
    {
        $module->fill([
            'title' => $attributes['title'],
        ]);

        $module->save();

        return $module->refresh();
    }
}
