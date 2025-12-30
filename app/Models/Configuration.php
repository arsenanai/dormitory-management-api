<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    protected $table = 'configurations';
    public $timestamps = false;
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get the typed value based on the type field
     */
    public function getTypedValue()
    {
        if (! $this->type || $this->type === 'string') {
            return $this->value;
        }

        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
            case 'integer':
                return is_numeric($this->value) ? (int) $this->value : null;
            case 'json':
                return json_decode($this->value, true);
            default:
                return $this->value;
        }
    }

    /**
     * Set the typed value and automatically determine the type
     */
    public function setTypedValue($value)
    {
        if (is_bool($value)) {
            $this->type = 'boolean';
            $this->value = $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            $this->type = 'number';
            $this->value = (string) $value;
        } elseif (is_array($value)) {
            $this->type = 'json';
            $this->value = json_encode($value);
        } else {
            $this->type = 'string';
            $this->value = (string) $value;
        }
    }
}
