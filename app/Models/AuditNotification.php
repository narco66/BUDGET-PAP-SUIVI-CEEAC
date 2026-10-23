<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class AuditNotification extends Model

{

    protected $fillable = [

        'action', 'notification_log_id', 'alerte_id', 'type_code', 'module', 'canal',

        'utilisateur_id', 'destinataire_type', 'destinataire_id', 'escalade_niveau', 'payload', 'ip', 'occurred_at',

    ];



    protected $casts = [

        'payload' => 'array',

        'occurred_at' => 'datetime',

    ];



    public function utilisateur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'utilisateur_id');

    }



    public function notificationLog(): BelongsTo

    {

        return $this->belongsTo(NotificationLog::class, 'notification_log_id');

    }



    public function alerte(): BelongsTo

    {

        return $this->belongsTo(Alerte::class);

    }

}

