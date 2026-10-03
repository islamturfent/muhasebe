<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Pdf;
use Muh\Core\Response;

/**
 * Report export helpers: CSV, Excel (SpreadsheetML XML) and PDF.
 * All dependency-free (no external libraries).
 */
final class ReportExportService
{
    public static function csv(array $headers, array $rows, string $filename): Response
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public static function excel(string $sheetTitle, array $headers, array $rows, string $filename): Response
    {
        $xml = new \XmlWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('Workbook');
        $xml->writeAttribute('xmlns', 'urn:schemas-microsoft-com:office:spreadsheet');
        $xml->writeAttribute('xmlns:o', 'urn:schemas-microsoft-com:office:office');
        $xml->writeAttribute('xmlns:x', 'urn:schemas-microsoft-com:office:excel');
        $xml->writeAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');

        $xml->startElement('Worksheet');
        $xml->writeAttribute('ss:Name', $sheetTitle);
        $xml->startElement('Table');

        // Header row
        $xml->startElement('Row');
        foreach ($headers as $h) {
            $xml->startElement('Cell');
            $xml->startElement('Data');
            $xml->writeAttribute('ss:Type', 'String');
            $xml->text((string) $h);
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();

        // Data rows
        foreach ($rows as $row) {
            $xml->startElement('Row');
            foreach ($row as $cell) {
                $xml->startElement('Cell');
                $xml->startElement('Data');
                $xml->writeAttribute('ss:Type', is_numeric($cell) && !is_string($cell) ? 'Number' : 'String');
                $xml->text((string) $cell);
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
        }

        $xml->endElement(); // Table
        $xml->endElement(); // Worksheet
        $xml->endElement(); // Workbook
        $content = $xml->outputMemory();

        return Response::make($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public static function pdf(string $title, string $subtitle, array $headers, array $rows, string $filename): Response
    {
        $pdf = new Pdf();
        $pdf->title($title);
        if ($subtitle) {
            $pdf->subheading($subtitle);
        }

        $pageW = 595 - 96; // usable width
        $widths = array_fill(0, count($headers), $pageW / max(1, count($headers)));

        $pdf->tableRow($headers, $widths, true);
        foreach ($rows as $row) {
            $pdf->tableRow(array_values($row), $widths, false);
        }

        $content = $pdf->output();
        return Response::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
