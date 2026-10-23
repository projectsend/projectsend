<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth\Clients;

use RuntimeException;

/** The document a client identifies itself by could not be read, and why. */
final class ClientMetadataUnavailable extends RuntimeException {}
