<?php

namespace App\Models;

use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $fillable = [
        'code',
        'notification_type_id',
        'libelle',
        'sujet',
        'corps_html',
        'corps_texte',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }

    /** @param array<string, scalar|null> $variables */
    public function appliquerSujet(array $variables): string
    {
        return $this->remplacer($this->sujet, $variables);
    }

    /** @param array<string, scalar|null> $variables */
    public function appliquerCorps(array $variables, string $format = 'html'): string
    {
        $source = $format === 'text' && $this->corps_texte
            ? $this->corps_texte
            : $this->corps_html;

        return $this->remplacer($source, $variables);
    }

    /** @param array<string, scalar|null> $variables */
    public function appliquerCorpsSecurise(array $variables, string $format = 'html'): string
    {
        $corps = $this->appliquerCorps($variables, $format);

        return $format === 'text' ? SafeHtml::text($corps) : SafeHtml::clean($corps);
    }

    /** @param array<string, scalar|null> $variables */
    private function remplacer(string $texte, array $variables): string
    {
        foreach ($variables as $cle => $valeur) {
            $texte = str_replace('{{'.$cle.'}}', e((string) ($valeur ?? '')), $texte);
        }

        return $texte;
    }
}
