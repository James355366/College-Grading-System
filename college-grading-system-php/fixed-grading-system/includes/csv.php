<?php
function write_csv_row($stream, array $row): void {
    // Spreadsheet formulas may follow whitespace/control characters. Preserve numeric cells.
    $safe = array_map(function ($value) {
        if (is_string($value) && preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/u', $value)) return "'" . $value;
        return $value;
    }, $row);
    fputcsv($stream, $safe, ',', '"', '');
}
