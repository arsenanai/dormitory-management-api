<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Services\ConfigurationService;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function __construct(private ConfigurationService $configurationService)
    {
    }

    /**
     * Get all configurations
     */
    public function index()
    {
        $configurations = $this->configurationService->getAllConfigurations();
        return response()->json($configurations);
    }

    /**
     * Update multiple configurations
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'configurations'               => 'required|array',
            'configurations.*.value'       => 'required',
            'configurations.*.type'        => 'sometimes|in:string,number,boolean,json',
            'configurations.*.description' => 'sometimes|string',
        ]);

        $configurations = $this->configurationService->updateConfigurations($validated['configurations']);
        return response()->json($configurations);
    }

    /**
     * Get SMTP settings
     */
    public function getSmtpSettings()
    {
        $settings = $this->configurationService->getSMTPSettings();
        return response()->json($settings);
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtpSettings(Request $request)
    {
        $validated = $request->validate([
            'smtp_host'         => 'required|string|max:255',
            'smtp_port'         => 'required|integer|min:1|max:65535',
            'smtp_username'     => 'required|string|max:255',
            'smtp_password'     => 'required|string|max:255',
            'smtp_encryption'   => 'required|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name'    => 'required|string|max:255',
        ]);

        $settings = $this->configurationService->updateSMTPSettings($validated);
        return response()->json($settings);
    }

    /**
     * Get card reader settings
     */
    public function getCardReaderSettings()
    {
        $settings = $this->configurationService->getCardReaderSettings();
        return response()->json($settings);
    }

    /**
     * Update card reader settings
     */
    public function updateCardReaderSettings(Request $request)
    {
        $validated = $request->validate([
            'card_reader_enabled'     => 'required|boolean',
            'card_reader_host'        => 'required_if:card_reader_enabled,true|nullable|string|max:255',
            'card_reader_port'        => 'required_if:card_reader_enabled,true|nullable|integer|min:1|max:65535',
            'card_reader_timeout'     => 'required_if:card_reader_enabled,true|nullable|integer|min:1|max:300',
            'card_reader_locations'   => 'nullable|array',
            'card_reader_locations.*' => 'string|max:255',
        ]);

        $settings = $this->configurationService->updateCardReaderSettings($validated);
        return response()->json($settings);
    }

    /**
     * Get 1C integration settings
     */
    public function getOneCSettings()
    {
        $settings = $this->configurationService->getOneCSettings();
        return response()->json($settings);
    }

    /**
     * Update 1C integration settings
     */
    public function updateOneCSettings(Request $request)
    {
        $validated = $request->validate([
            'onec_enabled'       => 'required|boolean',
            'onec_host'          => 'required_if:onec_enabled,true|nullable|string|max:255',
            'onec_database'      => 'required_if:onec_enabled,true|nullable|string|max:255',
            'onec_username'      => 'required_if:onec_enabled,true|nullable|string|max:255',
            'onec_password'      => 'required_if:onec_enabled,true|nullable|string|max:255',
            'onec_sync_interval' => 'required_if:onec_enabled,true|nullable|integer|min:60|max:86400',
        ]);

        $settings = $this->configurationService->updateOneCSettings($validated);
        return response()->json($settings);
    }

    /**
     * Get Kaspi integration settings
     */
    public function getKaspiSettings()
    {
        $settings = $this->configurationService->getKaspiSettings();
        return response()->json($settings);
    }

    /**
     * Update Kaspi integration settings
     */
    public function updateKaspiSettings(Request $request)
    {
        $validated = $request->validate([
            'kaspi_enabled'     => 'required|boolean',
            'kaspi_api_key'     => 'required_if:kaspi_enabled,true|nullable|string|max:255|regex:/^[a-zA-Z0-9_-]*$/',
            'kaspi_merchant_id' => 'required_if:kaspi_enabled,true|nullable|string|max:255',
            'kaspi_webhook_url' => 'required_if:kaspi_enabled,true|nullable|url|max:255',
        ]);

        $settings = $this->configurationService->updateKaspiSettings($validated);
        return response()->json($settings);
    }

    /**
     * Upload language file
     */
    public function uploadLanguageFile(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string|max:10',
            'file'     => 'required|file|mimes:json|max:1024',
        ]);

        $result = $this->configurationService->uploadLanguageFile($validated['file'], $validated['language']);
        return response()->json($result);
    }

    /**
     * Get installed languages
     */
    public function getInstalledLanguages()
    {
        $languages = $this->configurationService->getInstalledLanguages();
        return response()->json($languages);
    }

    /**
     * Get system logs
     */
    public function getSystemLogs(Request $request)
    {
        $validated = $request->validate([
            'type'  => 'sometimes|in:all,error,info,warning',
            'limit' => 'sometimes|integer|min:10|max:1000',
        ]);

        $logs = $this->configurationService->getSystemLogs(
            $validated['type'] ?? 'all',
            $validated['limit'] ?? 100
        );

        return response()->json($logs);
    }

    /**
     * Clear system logs
     */
    public function clearSystemLogs()
    {
        $result = $this->configurationService->clearSystemLogs();
        return response()->json($result);
    }

    /**
     * Get dormitory settings
     */
    public function getDormitorySettings()
    {
        $settings = $this->configurationService->getDormitorySettings();
        return response()->json($settings);
    }

    /**
     * Update dormitory settings
     */
    // public function updateDormitorySettings( Request $request ) {
    // 	$validated = $request->validate( [
    // 		'max_students_per_dormitory' => 'required|integer|min:1|max:10000',
    // 		'registration_enabled'       => 'required|boolean',
    // 		'backup_list_enabled'        => 'required|boolean',
    // 		'payment_deadline_days'      => 'required|integer|min:1|max:365',
    // 		'dormitory_rules'            => 'sometimes|nullable|string',
    // 		'bank_requisites'            => 'sometimes|nullable|string',
    // 	] );

    // 	$settings = $this->configurationService->updateDormitorySettings( $validated );
    // 	return response()->json( $settings );
    // }

    /**
     * Get all public settings
     */
    public function getPublicSettings()
    {
        $settings = [
            // Provide a default empty string if the rule is not set
            'dormitory_rules' => $this->configurationService->getConfiguration('dormitory_rules') ?? '',
            'currency_symbol' => $this->configurationService->getConfiguration('currency_symbol') ?? 'USD',
            'bank_requisites' => $this->configurationService->getConfiguration('bank_requisites') ?? 'Bank Name: XYZ Bank\nAccount Number: 1234567890\nIBAN: KZ0000000000000000',
        ];
        return response()->json($settings);
    }
    /**
     * Initialize default configurations
     */
    public function initializeDefaults()
    {
        $configurations = $this->configurationService->initializeDefaults();
        return response()->json($configurations);
    }

    /**
     * Update currency setting
     */
    public function updateCurrencySetting(Request $request)
    {
        $validated = $request->validate([
            'currency_symbol' => 'required|string|max:10',
        ]);

        Configuration::updateOrCreate(
            [ 'key' => 'currency_symbol' ],
            [ 'value' => $validated['currency_symbol'] ]
        );

        return response()->json([ 'message' => 'Currency settings updated successfully.' ]);
    }

    /**
     * Update dormitory rules setting
     */
    public function updateDormitoryRules(Request $request)
    {
        $validated = $request->validate([
            'dormitory_rules' => 'nullable|string',
        ]);

        $this->configurationService->setConfiguration('dormitory_rules', $validated['dormitory_rules'], 'string', 'Dormitory Rules and Regulations');

        return response()->json([ 'message' => 'Dormitory rules updated successfully.' ]);
    }

    /**
     * Update bank requisites setting
     */
    public function updateBankRequisites(Request $request)
    {
        $validated = $request->validate([
            'bank_requisites' => 'nullable|string',
        ]);

        $this->configurationService->setConfiguration('bank_requisites', $validated['bank_requisites'], 'string', 'Bank Requisites');

        return response()->json([ 'message' => 'Bank requisites updated successfully.' ]);
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings(): \Illuminate\Http\JsonResponse
    {
        $settings = $this->configurationService->getPaymentSettings();
        return response()->json($settings);
    }

    /**
     * Update payment settings
     */
    public function updatePaymentSettings(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'semester_config' => 'required|array',
            'semester_config.fall' => 'required|array',
            'semester_config.fall.start_month' => 'required|integer|min:1|max:12',
            'semester_config.fall.start_day' => 'required|integer|min:1|max:31',
            'semester_config.fall.end_month' => 'required|integer|min:1|max:12',
            'semester_config.fall.end_day' => 'required|integer|min:1|max:31',
            'semester_config.fall.payment_deadline_month' => 'required|integer|min:1|max:12',
            'semester_config.fall.payment_deadline_day' => 'required|integer|min:1|max:31',
            'semester_config.spring' => 'required|array',
            'semester_config.spring.start_month' => 'required|integer|min:1|max:12',
            'semester_config.spring.start_day' => 'required|integer|min:1|max:31',
            'semester_config.spring.end_month' => 'required|integer|min:1|max:12',
            'semester_config.spring.end_day' => 'required|integer|min:1|max:31',
            'semester_config.spring.payment_deadline_month' => 'required|integer|min:1|max:12',
            'semester_config.spring.payment_deadline_day' => 'required|integer|min:1|max:31',
            'semester_config.summer' => 'required|array',
            'semester_config.summer.start_month' => 'required|integer|min:1|max:12',
            'semester_config.summer.start_day' => 'required|integer|min:1|max:31',
            'semester_config.summer.end_month' => 'required|integer|min:1|max:12',
            'semester_config.summer.end_day' => 'required|integer|min:1|max:31',
            'semester_config.summer.payment_deadline_month' => 'required|integer|min:1|max:12',
            'semester_config.summer.payment_deadline_day' => 'required|integer|min:1|max:31',
        ]);

        $settings = $this->configurationService->updatePaymentSettings($validated);

        return response()->json($settings);
    }
}
