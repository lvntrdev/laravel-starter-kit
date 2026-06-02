<?php

namespace App\Exceptions;

use Lvntr\StarterKit\Exceptions\ApiException as PackageApiException;

/**
 * Namespace alias for the package ApiException.
 *
 * The implementation lives in the package
 * (Lvntr\StarterKit\Exceptions\ApiException) and runs from vendor.
 * This thin subclass exists so host-app code can import the familiar
 * App\Exceptions\ApiException namespace and IDEs can resolve the type.
 *
 * Publish/override is optional: delete this file and import the package
 * class directly if you prefer.
 */
class ApiException extends PackageApiException {}
