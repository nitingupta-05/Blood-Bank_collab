<?php
/**
 * CSV report exporter.
 * Streams a CSV with hospital name + title header to the browser as a download.
 *
 * Used to be misnamed "PdfHelper" but always produced CSV.
 */
class CsvExporter {

    public static function stream(array $rows, string $title, string $filename, string $orgName = ''): void {
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename) ?: 'report';
        $filename .= '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 detection
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [$orgName ?: APP_NAME]);
        fputcsv($out, [$title]);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []); // blank row

        if (empty($rows)) {
            fputcsv($out, ['No records found.']);
            fclose($out);
            exit;
        }

        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, array_map(
                fn($v) => is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) ($v ?? ''),
                $row
            ));
        }
        fclose($out);
        exit;
    }
}
