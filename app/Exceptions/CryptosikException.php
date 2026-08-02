<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Exceptions;

use RuntimeException;

/** Domain failures whose message is safe to show to a user. */
abstract class CryptosikException extends RuntimeException {}
