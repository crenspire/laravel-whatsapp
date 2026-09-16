<?php

namespace Crenspire\Whatsapp\Exceptions;

/**
 * A rate limit was hit, either the package's own limit or one enforced by Meta
 */
class RateLimitException extends WhatsappException {}
