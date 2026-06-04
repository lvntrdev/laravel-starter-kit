<?php

// Makes App\Http\Responses\ApiResponse identical to the package class.
// Using class_alias instead of extends so that DatatableQueryBuilder::response()
// (which returns the package type) satisfies return-type checks in query classes.
class_alias(
    \Lvntr\StarterKit\Http\Responses\ApiResponse::class,
    \App\Http\Responses\ApiResponse::class
);
