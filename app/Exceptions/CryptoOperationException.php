<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Exceptions;

use RuntimeException;

/** Deliberately not a CryptosikException - describes the envelope, not something a user reads. */
class CryptoOperationException extends RuntimeException {}
