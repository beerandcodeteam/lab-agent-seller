<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Raised when a vector store operation against the OpenAI provider fails
 * (creation, upload, file removal or store deletion). The message is written
 * in PT-BR to be shown to the company in the management panel without leaking
 * internal/technical details.
 */
class VectorStoreOperationException extends RuntimeException {}
