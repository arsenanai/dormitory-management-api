<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model {
	/** @use HasFactory<\Database\Factories\ConfigurationFactory> */
	use HasFactory;

	protected $fillable = [ 
		'key',
		'value',
		'type',
		'description',
	];

	protected $casts = [ 
		'value' => 'string',
	];

	/**
	 * Get the typed value of the configuration
	 */
	public function getTypedValue() {
		return match ( $this->type ) {
			'number'  => is_numeric( $this->value ) ? (float) $this->value : 0,
			'boolean' => filter_var( $this->value, FILTER_VALIDATE_BOOLEAN ),
			'json'    => json_decode( $this->value, true ),
			default   => $this->value,
		};
	}

	/**
	 * Set the typed value of the configuration
	 */
	public function setTypedValue( $value ) {
		$this->value = match ( $this->type ) {
			'number'  => (string) $value,
			'boolean' => $value ? 'true' : 'false',
			'json'    => json_encode( $value ),
			default   => (string) $value,
		};
	}
}
