<?php

declare(strict_types=1);

namespace Stackin\Errors;

/**
 * A 402: the account cannot pay for this operation, typically because
 * the plan's allowance is spent and the credit balance does not cover
 * the next document. $detail carries the API's explanation.
 *
 * Never retried: the same call fails the same way until the account
 * is topped up or the plan changes. usage() shows where it stands.
 */
final class PaymentRequiredError extends ApiError
{
}
