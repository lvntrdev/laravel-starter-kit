<?php

namespace App\Rules;

use Lvntr\StarterKit\Rules\HttpsOrLocalhostUrl as PackageHttpsOrLocalhostUrl;

/**
 * Backwards-compatible alias for the package validation rule.
 *
 * The implementation now lives in the package
 * (Lvntr\StarterKit\Rules\HttpsOrLocalhostUrl) and runs from vendor. This thin
 * subclass only keeps the App\Rules\HttpsOrLocalhostUrl namespace importable for
 * host-app FormRequests. Publish/override is optional.
 */
class HttpsOrLocalhostUrl extends PackageHttpsOrLocalhostUrl {}
