<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\WasteDeposit;
use App\Models\WasteItem;

class WasteDepositItem extends Model
{
    protected $table = 'waste_deposit_items';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_snapshot' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function wasteDeposit()
    {
        return $this->belongsTo(WasteDeposit::class, 'waste_deposit_id');
    }

    public function wasteItem()
    {
        return $this->belongsTo(WasteItem::class);
    }
}
