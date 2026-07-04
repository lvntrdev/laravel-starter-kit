<?php

namespace Lvntr\StarterKit\Exceptions;

use LogicException;

/**
 * Domain rule / business-guard violation.
 *
 * Thrown by domain Actions when a request is well-formed but violates a
 * business invariant (duplicate folder, item out of context, move cycle,
 * invalid trash-item type, …). Extends {@see LogicException} so existing
 * `catch (LogicException)` sites and `@throws LogicException` contracts keep
 * working unchanged, while giving the central {@see ApiExceptionHandler} a
 * precise type to map to HTTP 422.
 *
 * The narrow subclass matters: mapping the broad LogicException to 422 would
 * also demote unrelated framework LogicExceptions (BadMethodCallException,
 * InvalidArgumentException, …) — which signal real bugs — from a 500 server
 * error to a 422 with a leaked internal message. Only this domain-owned type
 * is mapped, so framework LogicExceptions still fall through to the 500 default.
 */
class DomainRuleException extends LogicException {}
