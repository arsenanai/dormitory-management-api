<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property int $region_id
 *
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @extends \Illuminate\Database\Eloquent\Model<TFactory>
 */
class City extends Model
{
    /** @extends HasFactory<City> */
    use HasFactory;

    protected $fillable = [
        'name',
        'region_id',
    ];

    protected $casts = [
        'id' => 'int',
        'region_id' => 'int',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'region_id')
            ->join('regions', 'regions.country_id', '=', 'countries.id');
    }
}
