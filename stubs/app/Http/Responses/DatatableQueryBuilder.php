<?php

namespace App\Http\Responses;

use Lvntr\StarterKit\Http\Responses\DatatableQueryBuilder as PackageDatatableQueryBuilder;

/**
 * Backwards-compatible alias for the package DataTable query builder.
 *
 * The implementation now lives in the package
 * (Lvntr\StarterKit\Http\Responses\DatatableQueryBuilder) and runs from
 * vendor — there is nothing to maintain here. This thin subclass only exists
 * so host-app code (and the generated domain Query classes) can keep importing
 * the familiar App\Http\Responses\DatatableQueryBuilder namespace.
 *
 * Publish/override is optional: delete this file and import the package class
 * directly if you prefer.
 */
class DatatableQueryBuilder extends PackageDatatableQueryBuilder {}
