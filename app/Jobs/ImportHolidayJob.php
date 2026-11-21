<?php

namespace App\Jobs;

use Shuchkin\SimpleXLSX;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use App\Models\Holiday\Holiday;
use App\Models\Administration\UploadLog;
use App\Models\Administration\UploadLogError;

/**
 * Holiday import (Holiday Management → Holiday → Import).
 *
 * File columns: SNo | Day | Month | Year
 *  - Day:   day of the month, 1 - 31 (must exist in that month).
 *  - Month: 1 - 12, or a month name ("January" / "Jan").
 *  - Year:  four digits.
 * A date that already exists (or repeats in the file) is skipped.
 * Rows with problems are skipped and written to the upload log errors;
 * the valid rows are still imported.
 */
class ImportHolidayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Header row of the sample file, in order. */
    public const HEADERS = ['SNo', 'Day', 'Month', 'Year'];

    public const MIN_YEAR = 1900;
    public const MAX_YEAR = 2100;

    protected $details;

    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * @return array{imported:int, errors:array<int,array{line_no:int,error:string}>}
     */
    public function handle(): array
    {
        UploadLog::where('id', $this->details['log_id'])->update(['upload_status' => 1]);

        $errors = [];
        $imported = 0;
        $xlsx = SimpleXLSX::parse($this->details['path']);

        if (!$xlsx) {
            $errors[] = $this->error(0, 'The file could not be read as an Excel (.xlsx) file');
            return $this->finish($errors, $imported);
        }

        // Existing dates, so repeats are skipped instead of duplicated.
        $existing = Holiday::whereNotNull('day')->select('day', 'month', 'year')->get()
            ->mapWithKeys(fn ($h) => [$h->year . '-' . $h->month . '-' . $h->day => true])
            ->all();

        $seenInFile = [];
        $line = 0;

        foreach ($xlsx->rows() as $row) {
            $line++;
            $row = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $row);

            if ($line === 1) {
                $header = array_map('strval', array_slice(array_pad($row, count(self::HEADERS), ''), 0, count(self::HEADERS)));
                if (array_map('strtolower', $header) !== array_map('strtolower', self::HEADERS)) {
                    $errors[] = $this->error($line, 'Header columns do not match the sample file. Download the sample and keep its first row (SNo, Day, Month, Year) unchanged.');
                    break;
                }
                continue;
            }

            [, $dayCell, $monthCell, $yearCell] = array_map('strval', array_pad($row, count(self::HEADERS), ''));
            if ($dayCell === '' && $monthCell === '' && $yearCell === '') {
                continue; // blank line
            }

            $problems = [];

            $day = preg_match('/^\d{1,2}(\.0+)?$/', $dayCell) ? (int) $dayCell : null;
            if ($dayCell === '') {
                $problems[] = 'Day is required';
            } elseif ($day === null || $day < 1 || $day > 31) {
                $problems[] = "Day '{$dayCell}' is not valid (use 1 - 31)";
                $day = null;
            }

            $month = $this->parseMonth($monthCell);
            if ($monthCell === '') {
                $problems[] = 'Month is required';
            } elseif ($month === null) {
                $problems[] = "Month '{$monthCell}' is not valid (use 1 - 12 or a month name)";
            }

            $year = $this->parseYear($yearCell);
            if ($yearCell === '') {
                $problems[] = 'Year is required';
            } elseif ($year === null) {
                $problems[] = "Year '{$yearCell}' is not valid (use a four digit year between " . self::MIN_YEAR . ' and ' . self::MAX_YEAR . ')';
            }

            if ($day !== null && $month !== null && $year !== null) {
                $key = $year . '-' . $month . '-' . $day;
                $label = $day . ' ' . Holiday::monthName($month) . ' ' . $year;
                if (!checkdate($month, $day, $year)) {
                    $problems[] = "{$label} is not a real date (" . Holiday::monthName($month) . ' ' . $year . ' has ' . date('t', mktime(0, 0, 0, $month, 1, $year)) . ' days)';
                } elseif (isset($seenInFile[$key])) {
                    $problems[] = "{$label} is repeated in this file (line {$seenInFile[$key]})";
                } elseif (isset($existing[$key])) {
                    $problems[] = "{$label} already exists";
                }
            }

            if ($problems) {
                $errors[] = $this->error($line, implode('; ', $problems));
                continue;
            }

            Holiday::create([
                'day' => $day,
                'month' => $month,
                'year' => $year,
                'created_by' => $this->details['user_id'],
            ]);

            $seenInFile[$key] = $line;
            $existing[$key] = true;
            $imported++;
        }

        if ($line <= 1 && !$errors) {
            $errors[] = $this->error(0, 'The file has no data rows');
        }

        return $this->finish($errors, $imported);
    }

    /** 1 - 12, "01", "January", "jan" → 1 - 12; anything else → null. */
    private function parseMonth(string $value): ?int
    {
        if (preg_match('/^\d{1,2}(\.0+)?$/', $value)) {
            $number = (int) $value;
            return $number >= 1 && $number <= 12 ? $number : null;
        }

        $value = strtolower($value);
        foreach (Holiday::monthNames() as $number => $name) {
            $name = strtolower($name);
            if ($value === $name || $value === substr($name, 0, 3)) {
                return $number;
            }
        }
        return null;
    }

    private function parseYear(string $value): ?int
    {
        if (!preg_match('/^\d{4}(\.0+)?$/', $value)) {
            return null;
        }
        $year = (int) $value;
        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $year : null;
    }

    private function error(int $line, string $message): array
    {
        return [
            'upload_id' => $this->details['log_id'],
            'line_no' => $line,
            'error' => mb_substr($message, 0, 1000),
        ];
    }

    private function finish(array $errors, int $imported): array
    {
        if ($errors) {
            UploadLogError::insert($errors);
        }

        UploadLog::where('id', $this->details['log_id'])->update([
            'upload_status' => $errors ? 3 : 2,
        ]);

        return [
            'imported' => $imported,
            'errors' => array_map(fn ($e) => ['line_no' => $e['line_no'], 'error' => $e['error']], $errors),
        ];
    }
}
