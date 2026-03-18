<?php

namespace Lvntr\StarterKit\Enums\Attributes;

use Attribute;

/**
 * Mark a HasDefinition enum to be shared via Inertia shared props.
 * Enums without this attribute are only available via the API endpoint.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class InertiaShared {}
