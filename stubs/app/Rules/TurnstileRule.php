<?php

namespace App\Rules;

use Lvntr\StarterKit\Rules\TurnstileRule as PackageTurnstileRule;

/**
 * Backwards-compatible alias for the package Turnstile validation rule.
 *
 * The implementation now lives in the package
 * (Lvntr\StarterKit\Rules\TurnstileRule) and runs from vendor. This thin
 * subclass only keeps the App\Rules\TurnstileRule namespace importable for
 * host-app code (Fortify actions, requests). Publish/override is optional.
 */
class TurnstileRule extends PackageTurnstileRule {}
