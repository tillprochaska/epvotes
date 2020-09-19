<?php

namespace App;

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'term_id',
        'number',
        'year',
        'title',
    ];

    protected $casts = [
        'type' => DocumentTypeEnum::class,
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }
}
