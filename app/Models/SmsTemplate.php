<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class SmsTemplate extends Model

{

    protected $fillable = ['code', 'notification_type_id', 'libelle', 'corps', 'actif'];



    protected $casts = ['actif' => 'boolean'];



    public function notificationType(): BelongsTo

    {

        return $this->belongsTo(NotificationType::class, 'notification_type_id');

    }



    /** @param array<string, scalar|null> $variables */

    public function appliquer(array $variables): string

    {

        $texte = $this->corps;

        foreach ($variables as $cle => $valeur) {

            $texte = str_replace('{{'.$cle.'}}', (string) ($valeur ?? ''), $texte);

        }



        return mb_substr($texte, 0, 320);

    }

}

