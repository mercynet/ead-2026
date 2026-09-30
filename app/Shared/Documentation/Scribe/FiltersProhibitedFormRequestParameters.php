<?php

namespace App\Shared\Documentation\Scribe;

use Illuminate\Foundation\Http\FormRequest;

trait FiltersProhibitedFormRequestParameters
{
    protected function getRouteValidationRules(FormRequest $formRequest): array
    {
        $rules = parent::getRouteValidationRules($formRequest);

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
