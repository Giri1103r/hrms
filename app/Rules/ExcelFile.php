<?php

namespace App\Rules;

use Closure;
use Shuchkin\SimpleXLSX;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts an uploaded .xlsx file by checking that it can actually be read as
 * a spreadsheet, instead of trusting PHP's MIME guess.
 *
 * Laravel's `mimes:xlsx` relies on fileinfo, which labels many valid .xlsx
 * files (LibreOffice, Google Sheets, WPS, some Excel builds) as
 * application/zip or application/octet-stream and rejects them.
 */
class ExcelFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('The file could not be uploaded. Please try again.');
            return;
        }

        if (strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
            $fail('Please upload an Excel file (.xlsx). Save the file as "Excel Workbook (.xlsx)" and try again.');
            return;
        }

        if (!SimpleXLSX::parse($value->getRealPath())) {
            $fail('The file could not be read as an Excel workbook. Open it in Excel or LibreOffice, save it as "Excel Workbook (.xlsx)" and try again.');
        }
    }
}
