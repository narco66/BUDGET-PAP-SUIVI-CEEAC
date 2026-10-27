<?php

namespace App\Services\Workflow;

use RuntimeException;

/** Conflit de version sur une tâche (décision ou action obsolète) : HTTP 409. */
class TacheConflitException extends RuntimeException {}
