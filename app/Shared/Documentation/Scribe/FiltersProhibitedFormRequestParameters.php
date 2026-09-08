<?php

namespace App\Shared\Documentation\Scribe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

trait FiltersProhibitedFormRequestParameters
{
    protected function getRouteValidationRules(FormRequest $formRequest): array
    {
        $rules = parent::getRouteValidationRules($formRequest);
        $route = $formRequest->route();

        if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/v1/instructor')) {
            return $rules;
        }

        return array_filter($rules, function (array $ruleSet): bool {
            foreach ($ruleSet as $rule) {
                if (is_string($rule) && preg_match('/^prohibited(?:_|$)/', $rule) === 1) {
                    return false;
                }
            }

            return true;
        });
    }
}
