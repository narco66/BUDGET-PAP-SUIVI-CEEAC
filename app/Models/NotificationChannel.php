<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class NotificationChannel extends Model

{

    protected $fillable = ['notification_type_id', 'canal', 'actif', 'ordre', 'config'];



    protected $casts = [

        'actif' => 'boolean',

        'config' => 'array',

    ];



    public function type(): BelongsTo

    {

        return $this->belongsTo(NotificationType::class, 'notification_type_id');

    }

}

