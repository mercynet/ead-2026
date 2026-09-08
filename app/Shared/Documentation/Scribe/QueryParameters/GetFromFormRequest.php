<?php

namespace App\Shared\Documentation\Scribe\QueryParameters;

use App\Shared\Documentation\Scribe\FiltersProhibitedFormRequestParameters;
use Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromFormRequest as BaseGetFromFormRequest;

class GetFromFormRequest extends BaseGetFromFormRequest
{
    use FiltersProhibitedFormRequestParameters;
}
