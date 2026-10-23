<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;



class OrdrePaiement extends Model

{

    protected $table = 'ordres_paiement';



    protected $fillable = [

        'ordonnancement_id', 'numero', 'montant', 'date_ordre', 'statut', 'emis_par', 'emis_le',

    ];



    protected $casts = [

        'montant' => 'decimal:2',

        'date_ordre' => 'date',

        'emis_le' => 'datetime',

    ];



    public function ordonnancement(): BelongsTo

    {

        return $this->belongsTo(Ordonnancement::class);

    }



    public function emetteur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'emis_par');

    }



    public function paiements(): HasMany

    {

        return $this->hasMany(Paiement::class);

    }

}


