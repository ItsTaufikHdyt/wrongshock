<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteItem extends Model
{
    protected $table = 'waste_items';

    protected $fillable = [
        'category',
        'output',
        'unit',
        'price',
        // Tambahkan atribut lain sesuai kebutuhan
    ];

    // Definisikan relasi jika ada, misalnya:
    public function wasteDepositItems()
    {
        return $this->hasMany(WasteDepositItem::class, 'waste_item_id');
    }

}
