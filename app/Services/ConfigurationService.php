<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ConfigurationService {
	/**
	 * Get all configurations
	 */
	public function getAllConfigurations() {
		return Configuration::all()->keyBy( 'key' );
	}

	/**
	 * Get configuration by key
	 */
	public function getConfiguration( $key ) {
		$config = Configuration::where( 'key', $key )->first();
		return $config ? $config->getTypedValue() : null;
	}

	/**
	 * Set configuration value
	 */
	public function setConfiguration( $key, $value, $type = 'string', $description = null ) {
		$config = Configuration::updateOrCreate(
			[ 'key' => $key ],
			[ 
				'type'        => $type,
				'description' => $description,
			]
		);

		$config->setTypedValue( $value );
		$config->save();

		return $config;
	}

	/**
	 * Update multiple configurations
	 */
	public function updateConfigurations( array $configurations ) {
		foreach ( $configurations as $key => $data ) {
			$this->setConfiguration(
				$key,
				$data['value'],
				$data['type'] ?? 'string',
				$data['description'] ?? null
			);
		}

		return $this->getAllConfigurations();
	}

	/**
	 * Get SMTP settings
	 */
	public function getSMTPSettings() {
		return [ 
			'smtp_host'         => $this->getConfiguration( 'smtp_host' ),
			'smtp_port'         => $this->getConfiguration( 'smtp_port' ),
			'smtp_username'     => $this->getConfiguration( 'smtp_username' ),
			'smtp_password'     => $this->getConfiguration( 'smtp_password' ),
			'smtp_encryption'   => $this->getConfiguration( 'smtp_encryption' ),
			'mail_from_address' => $this->getConfiguration( 'mail_from_address' ),
			'mail_from_name'    => $this->getConfiguration( 'mail_from_name' ),
		];
	}

	/**
	 * Update SMTP settings
	 */
	public function updateSMTPSettings( array $settings ) {
		foreach ( $settings as $key => $value ) {
			$this->setConfiguration( $key, $value, 'string', 'SMTP Configuration' );
		}

		return $this->getSMTPSettings();
	}

	/**
	 * Get card reader settings
	 */
	public function getCardReaderSettings() {
		return [ 
			'card_reader_enabled'   => $this->getConfiguration( 'card_reader_enabled' ),
			'card_reader_host'      => $this->getConfiguration( 'card_reader_host' ),
			'card_reader_port'      => $this->getConfiguration( 'card_reader_port' ),
			'card_reader_timeout'   => $this->getConfiguration( 'card_reader_timeout' ),
			'card_reader_locations' => $this->getConfiguration( 'card_reader_locations' ),
		];
	}

	/**
	 * Update card reader settings
	 */
	public function updateCardReaderSettings( array $settings ) {
		foreach ( $settings as $key => $value ) {
			$type = $key === 'card_reader_enabled' ? 'boolean' :
				( $key === 'card_reader_locations' ? 'json' : 'string' );
			$this->setConfiguration( $key, $value, $type, 'Card Reader Configuration' );
		}

		return $this->getCardReaderSettings();
	}

	/**
	 * Get 1C integration settings
	 */
	public function getOneCSettings() {
		return [ 
			'onec_enabled'       => $this->getConfiguration( 'onec_enabled' ),
			'onec_host'          => $this->getConfiguration( 'onec_host' ),
			'onec_database'      => $this->getConfiguration( 'onec_database' ),
			'onec_username'      => $this->getConfiguration( 'onec_username' ),
			'onec_password'      => $this->getConfiguration( 'onec_password' ),
			'onec_sync_interval' => $this->getConfiguration( 'onec_sync_interval' ),
		];
	}

	/**
	 * Update 1C integration settings
	 */
	public function updateOneCSettings( array $settings ) {
		foreach ( $settings as $key => $value ) {
			$type = $key === 'onec_enabled' ? 'boolean' :
				( $key === 'onec_sync_interval' ? 'number' : 'string' );
			$this->setConfiguration( $key, $value, $type, '1C Integration Configuration' );
		}

		return $this->getOneCSettings();
	}

	/**
	 * Upload and install language file
	 */
	public function uploadLanguageFile( $file, $language ) {
		$fileName = $language . '.json';
		$filePath = 'languages/' . $fileName;

		// Store the file
		Storage::disk( 'public' )->put( $filePath, $file->getContent() );

		// Update configuration
		$this->setConfiguration( 'installed_languages', $this->getInstalledLanguages(), 'json', 'Installed Languages' );

		return [ 
			'message'   => 'Language file uploaded successfully',
			'language'  => $language,
			'file_path' => $filePath
		];
	}

	/**
	 * Get installed languages
	 */
	public function getInstalledLanguages() {
		$languages = $this->getConfiguration( 'installed_languages' ) ?? [];

		// Add default languages if not set
		if ( empty( $languages ) ) {
			$languages = [ 'en', 'ru', 'kk' ];
		}

		return $languages;
	}

	/**
	 * Get system logs
	 */
	public function getSystemLogs( $type = 'all', $limit = 100 ) {
		$logFiles = Storage::disk( 'local' )->files( 'logs' );
		$logs = [];

		foreach ( $logFiles as $logFile ) {
			if ( strpos( $logFile, '.log' ) !== false ) {
				$content = Storage::disk( 'local' )->get( $logFile );
				$lines = explode( "\n", $content );

				foreach ( array_slice( $lines, -$limit ) as $line ) {
					if ( empty( $line ) )
						continue;

					$logs[] = [ 
						'file'      => basename( $logFile ),
						'content'   => $line,
						'timestamp' => now()->toDateTimeString(),
					];
				}
			}
		}

		return array_slice( $logs, -$limit );
	}

	/**
	 * Clear system logs
	 */
	public function clearSystemLogs() {
		$logFiles = Storage::disk( 'local' )->files( 'logs' );

		foreach ( $logFiles as $logFile ) {
			if ( strpos( $logFile, '.log' ) !== false ) {
				Storage::disk( 'local' )->delete( $logFile );
			}
		}

		return [ 'message' => 'System logs cleared successfully' ];
	}

	/**
	 * Get dormitory settings
	 */
	public function getDormitorySettings() {
		return [ 
			'max_students_per_dormitory' => $this->getConfiguration( 'max_students_per_dormitory' ),
			'registration_enabled'       => $this->getConfiguration( 'registration_enabled' ),
			'backup_list_enabled'        => $this->getConfiguration( 'backup_list_enabled' ),
			'payment_deadline_days'      => $this->getConfiguration( 'payment_deadline_days' ),
			'default_room_price'         => $this->getConfiguration( 'default_room_price' ),
		];
	}

	/**
	 * Update dormitory settings
	 */
	public function updateDormitorySettings( array $settings ) {
		foreach ( $settings as $key => $value ) {
			$type = in_array( $key, [ 'registration_enabled', 'backup_list_enabled' ] ) ? 'boolean' : 'number';
			$this->setConfiguration( $key, $value, $type, 'Dormitory Configuration' );
		}

		return $this->getDormitorySettings();
	}

	/**
	 * Initialize default configurations
	 */
	public function initializeDefaults() {
		$defaults = [ 
			'max_students_per_dormitory' => [ 'value' => 500, 'type' => 'number' ],
			'registration_enabled'       => [ 'value' => true, 'type' => 'boolean' ],
			'backup_list_enabled'        => [ 'value' => true, 'type' => 'boolean' ],
			'payment_deadline_days'      => [ 'value' => 30, 'type' => 'number' ],
			'default_room_price'         => [ 'value' => 50000, 'type' => 'number' ],
			'smtp_host'                  => [ 'value' => 'smtp.gmail.com', 'type' => 'string' ],
			'smtp_port'                  => [ 'value' => 587, 'type' => 'number' ],
			'smtp_encryption'            => [ 'value' => 'tls', 'type' => 'string' ],
			'card_reader_enabled'        => [ 'value' => false, 'type' => 'boolean' ],
			'card_reader_timeout'        => [ 'value' => 30, 'type' => 'number' ],
			'onec_enabled'               => [ 'value' => false, 'type' => 'boolean' ],
			'onec_sync_interval'         => [ 'value' => 3600, 'type' => 'number' ],
			'installed_languages'        => [ 'value' => [ 'en', 'ru', 'kk' ], 'type' => 'json' ],
		];

		foreach ( $defaults as $key => $data ) {
			$existing = Configuration::where( 'key', $key )->first();
			if ( ! $existing ) {
				$this->setConfiguration( $key, $data['value'], $data['type'], 'Default Configuration' );
			}
		}

		return $this->getAllConfigurations();
	}
}
