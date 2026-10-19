<?php

namespace Nyumbani\Daraja\Exceptions;

use RuntimeException;

/**
 * Thrown when Daraja cannot be reached or a call cannot be made at all, as opposed to Daraja refusing a request.
 *
 * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
 */
class DarajaException extends RuntimeException {}
