<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an employee's ID number against the format of the chosen ID type.
 * Values are compared after normalize(): spaces and dashes removed, letters upper-cased.
 */
class IdNumber implements ValidationRule
{
    /** ID type => [pattern for the normalized value, human readable format]. */
    public const TYPES = [
        'Aadhar' => ['/^[2-9][0-9]{11}$/', '12 digits, not starting with 0 or 1 (e.g. 2345 6789 0124)'],
        'PAN' => ['/^[A-Z]{3}[ABCFGHJLPT][A-Z][0-9]{4}[A-Z]$/', '5 letters, 4 digits, 1 letter (e.g. ABCPE1234F)'],
        'Passport' => ['/^[A-PR-WY][1-9][0-9]{5}[1-9]$/', '1 letter followed by 7 digits (e.g. J8369854)'],
        'Driving Licence' => ['/^[A-Z]{2}[0-9]{13}$/', '2-letter state code followed by 13 digits (e.g. KA01 20231234567)'],
        'Voter ID' => ['/^[A-Z]{3}[0-9]{7}$/', '3 letters followed by 7 digits (e.g. ABC1234567)'],
    ];

    public function __construct(private ?string $idType)
    {
    }

    public static function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', (string) $value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!isset(self::TYPES[$this->idType])) {
            return; // the id_type rule reports an unknown type
        }

        [$pattern, $format] = self::TYPES[$this->idType];
        $number = self::normalize($value);

        if (!preg_match($pattern, $number)) {
            $fail("{$this->idType} number must be {$format}.");
            return;
        }

        if ($this->idType === 'Aadhar' && !self::verhoeffValid($number)) {
            $fail('This Aadhar number is not valid (check digit does not match). Please re-check the number.');
        }
    }

    /** Aadhaar numbers carry a Verhoeff check digit as their last digit. */
    public static function verhoeffValid(string $digits): bool
    {
        $d = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
            [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8], [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
            [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
            [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
        ];
        $p = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
            [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0], [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
            [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
        ];

        $c = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $c = $d[$c][$p[$i % 8][(int) $digit]];
        }

        return $c === 0;
    }
}
