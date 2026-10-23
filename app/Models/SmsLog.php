<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class SmsLog extends Model

{

    protected $fillable = [

        'sms_template_id', 'notification_log_id', 'type_code', 'module',

        'destinataire_telephone', 'destinataire_type', 'destinataire_id',

        'message', 'statut', 'provider', 'tentatives', 'max_tentatives', 'erreur', 'metadata', 'envoye_le',

    ];



    protected $casts = [

        'metadata' => 'array',

        'envoye_le' => 'datetime',

    ];



    public function template(): BelongsTo

    {

        return $this->belongsTo(SmsTemplate::class, 'sms_template_id');

    }

}

