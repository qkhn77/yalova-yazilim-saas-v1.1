<?php

namespace App\Muhasebe\Exceptions;

use RuntimeException;

/**
 * Ön muhasebe iş kuralları ihlal edildiğinde fırlatılır.
 */
class IsKuraliIstisnasi extends RuntimeException {}
