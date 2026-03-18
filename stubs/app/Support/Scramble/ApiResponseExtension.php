<?php

namespace App\Support\Scramble;

use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Teaches Scramble how to document ApiResponse as an API envelope.
 *
 * All responses are wrapped in:
 * { success, status, message, data, errors?, meta? }
 */
class ApiResponseExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ApiResponse::class);
    }

    public function toResponse(Type $type): ?Response
    {
        $envelope = new OpenApiObjectType;

        $envelope->addProperty('success', new BooleanType);
        $envelope->addProperty('status', new IntegerType);
        $envelope->addProperty('message', new StringType);
        $envelope->addProperty('data', (new OpenApiObjectType)->nullable(true));
        $envelope->addProperty('errors', (new OpenApiObjectType)->nullable(true));
        $envelope->addProperty('meta', (new OpenApiObjectType)->nullable(true));

        $envelope->setRequired(['success', 'status', 'message', 'data']);

        return Response::make(200)
            ->setContent('application/json', Schema::fromType($envelope));
    }
}
