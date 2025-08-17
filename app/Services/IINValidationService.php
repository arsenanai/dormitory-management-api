<?php

namespace App\Services;

/**
 * Kazakhstan IIN (Individual Identification Number) Validation Service
 * 
 * Implements the official Kazakhstan IIN validation algorithm
 * IIN format: YYMMDDXXXXXX (12 digits)
 * - YY: Year of birth (last 2 digits)
 * - MM: Month of birth
 * - DD: Day of birth  
 * - XXXXXX: Sequential number + check digit
 * 
 * @package App\Services
 */
class IINValidationService
{
    /**
     * Validate Kazakhstan IIN using official algorithm
     * 
     * @param string $iin 12-digit IIN to validate
     * @return bool True if IIN is valid, false otherwise
     */
    public function validate(string $iin): bool
    {
        // Basic format validation
        if (!preg_match('/^\d{12}$/', $iin)) {
            return false;
        }

        // Extract components
        $year = (int)substr($iin, 0, 2);
        $month = (int)substr($iin, 2, 2);
        $day = (int)substr($iin, 4, 2);
        $sequence = substr($iin, 6, 5);
        $checkDigit = (int)substr($iin, 11, 1);

        // Validate date components
        if (!$this->isValidDate($year, $month, $day)) {
            return false;
        }

        // Validate check digit using official algorithm
        return $this->validateCheckDigit($iin, $checkDigit);
    }

    /**
     * Validate date components of IIN
     * 
     * @param int $year 2-digit year
     * @param int $month Month (1-12)
     * @param int $day Day (1-31)
     * @return bool True if date is valid
     */
    private function isValidDate(int $year, int $month, int $day): bool
    {
        // Month validation
        if ($month < 1 || $month > 12) {
            return false;
        }

        // Day validation
        if ($day < 1 || $day > 31) {
            return false;
        }

        // Specific month day limits
        $daysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        // Handle leap years for February
        if ($month === 2) {
            $fullYear = 2000 + $year; // Assume 2000s for validation
            $isLeap = ($fullYear % 4 === 0 && $fullYear % 100 !== 0) || ($fullYear % 400 === 0);
            $daysInMonth[1] = $isLeap ? 29 : 28;
        }

        return $day <= $daysInMonth[$month - 1];
    }

    /**
     * Validate check digit using Kazakhstan IIN algorithm
     * 
     * @param string $iin Full 12-digit IIN
     * @param int $checkDigit Last digit (check digit)
     * @return bool True if check digit is valid
     */
    private function validateCheckDigit(string $iin, int $checkDigit): bool
    {
        // Weight coefficients for positions 1-11
        $weights = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        
        $sum = 0;
        
        // Calculate weighted sum for first 11 digits
        for ($i = 0; $i < 11; $i++) {
            $sum += (int)$iin[$i] * $weights[$i];
        }
        
        // Calculate check digit
        $calculatedCheckDigit = $sum % 11;
        
        // If remainder is 10, check digit should be 0
        if ($calculatedCheckDigit === 10) {
            $calculatedCheckDigit = 0;
        }
        
        return $calculatedCheckDigit === $checkDigit;
    }

    /**
     * Extract birth date from IIN
     * 
     * @param string $iin Valid 12-digit IIN
     * @return array|null Array with year, month, day or null if invalid
     */
    public function extractBirthDate(string $iin): ?array
    {
        if (!$this->validate($iin)) {
            return null;
        }

        $year = (int)substr($iin, 0, 2);
        $month = (int)substr($iin, 2, 2);
        $day = (int)substr($iin, 4, 2);

        // Determine century (assume 2000s for recent years)
        $fullYear = 2000 + $year;

        return [
            'year' => $fullYear,
            'month' => $month,
            'day' => $day
        ];
    }

    /**
     * Extract gender from IIN
     * 
     * @param string $iin Valid 12-digit IIN
     * @return string|null 'male', 'female', or null if invalid
     */
    public function extractGender(string $iin): ?string
    {
        if (!$this->validate($iin)) {
            return null;
        }

        $sequence = (int)substr($iin, 6, 5);
        
        // Even sequence numbers are typically female, odd are male
        // This is a general rule, but may vary by region
        return $sequence % 2 === 0 ? 'female' : 'male';
    }
}
