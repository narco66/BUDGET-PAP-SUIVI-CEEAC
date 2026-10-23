<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class EmailLog extends Model

{

    protected $fillable = [

        'email_template_id', 'notification_log_id', 'notification_type_id', 'type_code', 'module',

        'destinataire_email', 'destinataire_nom', 'destinataire_type', 'destinataire_id',

        'sujet', 'corps_html', 'statut', 'tentatives', 'max_tentatives', 'erreur', 'metadata', 'envoye_le',

    ];



    protected $casts = [

        'metadata' => 'array',

        'envoye_le' => 'datetime',

        'tentatives' => 'integer',

        'max_tentatives' => 'integer',

    ];



    public function template(): BelongsTo

    {

        return $this->belongsTo(EmailTemplate::class, 'email_template_id');

    }



    public function notificationLog(): BelongsTo

    {

        return $this->belongsTo(NotificationLog::class, 'notification_log_id');

    }



    public function peutRetenter(): bool

    {

        return $this->statut === 'echec' && $this->tentatives < $this->max_tentatives;

    }

}

