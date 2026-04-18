<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 *
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @extends \Illuminate\Database\Eloquent\Model<TFactory>
 */
class BloodType extends Model
{
    /** @extends HasFactory<BloodType> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];
}
