<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class ServiceFait extends Model

{

    protected $table = 'services_faits';



    public const TYPES = [

        'reception' => 'Réception',

        'pv' => 'Procès-verbal',

        'attestation' => 'Attestation',

        'constat' => 'Constat',

    ];



    protected $fillable = [

        'liquidation_id', 'type', 'reference', 'date_constat', 'constatation', 'responsable_id',

    ];



    protected $casts = [

        'date_constat' => 'date',

    ];



    public function liquidation(): BelongsTo

    {

        return $this->belongsTo(Liquidation::class);

    }



    public function responsable(): BelongsTo

    {

        return $this->belongsTo(User::class, 'responsable_id');

    }

}


