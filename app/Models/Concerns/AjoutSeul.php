<?php

namespace App\Models\Concerns;

use LogicException;

/** Enregistrement en ajout seul : ni modification ni suppression par l'application (JNL-006). */
trait AjoutSeul
{
    public static function bootAjoutSeul(): void
    {
        static::updating(fn ($m) => throw new LogicException(class_basename($m).' est en ajout seul : modification interdite.'));
        static::deleting(fn ($m) => throw new LogicException(class_basename($m).' est en ajout seul : suppression interdite.'));
    }
}
