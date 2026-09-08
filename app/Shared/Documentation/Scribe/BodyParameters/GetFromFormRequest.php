<?php

namespace App\Shared\Documentation\Scribe\BodyParameters;

use App\Shared\Documentation\Scribe\FiltersProhibitedFormRequestParameters;
use Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest as BaseGetFromFormRequest;

class GetFromFormRequest extends BaseGetFromFormRequest
{
    use FiltersProhibitedFormRequestParameters;
}
