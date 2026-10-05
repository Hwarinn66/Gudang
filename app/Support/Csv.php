<?php

namespace App\Support;

class Csv
{
    public static function download(string $filename, array $headers, iterable $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $headers, ',', '"', '');
            foreach ($rows as $row) {
                $safe = array_map(function ($v) {
                    if (is_string($v) && preg_match('/^[\s]*[=+@\-]/u', $v)) {
                        return "'".$v;
                    }

return $v;
                }, $row);
                fputcsv($f, $safe, ',', '"', '');
            }
            fclose($f);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
