<?php

namespace App\Http\Contracts;

/**
 * Consumer application BulkAction interface alias.
 *
 * Re-exports the package interface so host app code can use App namespace
 * imports. Domain bulk action classes should implement this interface
 * (which resolves to the package contract).
 *
 * @see \Lvntr\StarterKit\Http\Bulk\BulkAction
 */
interface BulkAction extends \Lvntr\StarterKit\Http\Bulk\BulkAction {}
