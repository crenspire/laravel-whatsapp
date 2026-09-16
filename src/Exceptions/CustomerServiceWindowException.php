<?php

namespace Crenspire\Whatsapp\Exceptions;

/**
 * A free-form message was sent more than 24 hours after the customer last messaged you; send a template instead
 */
class CustomerServiceWindowException extends WhatsappException {}
