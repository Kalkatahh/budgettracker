<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'renamed_filename',
        'google_drive_link',
        'date', // Add new columns for OCR data
        'store',
        'payment_method',
        'cost',
    ];

    /**
     * Get the user that owns the receipt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}