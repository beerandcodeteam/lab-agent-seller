<?php

namespace App\Services\Memory;

use RuntimeException;

/**
 * Thrown when a mem0 Platform call fails (transport error or non-2xx response).
 * The memory tools catch it and degrade to a generic marker so no technical
 * detail ever reaches the client-facing agent.
 */
class Mem0Exception extends RuntimeException {}
