<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class LiquidationPiece extends Model

{

    protected $table = 'liquidation_pieces';



    public const TYPES = [

        'facture' => 'Facture',

        'bon_livraison' => 'Bon de livraison',

        'pv_reception' => 'PV de réception',

        'attestation' => 'Attestation',

        'autre' => 'Autre',

    ];



    protected $fillable = [

        'liquidation_id', 'type', 'reference', 'date_piece', 'piece_justificative_id',

    ];



    protected $casts = [

        'date_piece' => 'date',

    ];



    public function liquidation(): BelongsTo

    {

        return $this->belongsTo(Liquidation::class);

    }



    public function pieceJustificative(): BelongsTo

    {

        return $this->belongsTo(PieceJustificative::class, 'piece_justificative_id');

    }

}


