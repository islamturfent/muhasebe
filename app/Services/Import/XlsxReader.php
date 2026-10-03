<?php

declare(strict_types=1);

namespace Muh\Services\Import;

/**
 * Minimal pure-PHP .xlsx reader using ZipArchive + SimpleXML.
 * Reads the first worksheet into a 2D array of rows (each row is an array of
 * cell values in column order). Shared strings and inline strings are resolved.
 */
final class XlsxReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path): array
    {
        $zip = new \ZipArchive();
        if (@$zip->open($path) !== true) {
            throw new \RuntimeException('open failed');
        }

        // Shared strings.
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $shared = static::parseSharedStrings($ss);
        }

        // Locate the first worksheet.
        $sheetFile = static::firstSheetFile($zip) ?? 'xl/worksheets/sheet1.xml';
        $sheetXml = $zip->getFromName($sheetFile);
        $zip->close();
        if ($sheetXml === false) {
            throw new \RuntimeException('worksheet not found');
        }

        return static::parseSheet($sheetXml, $shared);
    }

    private static function parseSharedStrings(string $xml): array
    {
        $out = [];
        $doc = @simplexml_load_string($xml);
        if (!$doc) {
            return $out;
        }
        $ns = $doc->getNamespaces(true);
        $sst = $doc;
        foreach ($sst->si as $si) {
            $text = '';
            foreach ($si->t as $t) {
                $text .= (string) $t;
            }
            // Some editors split strings across <r> elements.
            foreach ($si->r as $r) {
                $text .= (string) $r->t;
            }
            $out[] = $text;
        }
        return $out;
    }

    private static function firstSheetFile(\ZipArchive $zip): ?string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        if ($wb === false) {
            return null;
        }
        $doc = @simplexml_load_string($wb);
        if (!$doc) {
            return null;
        }
        $ns = $doc->getNamespaces(true);
        $rel = $doc->children($ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = null;
        $sheets = $doc->sheets ?? [];
        foreach ($doc->sheets->sheet as $sh) {
            $rid = (string) ($sh['id'] ?? $sh->attributes($ns['r'] ?? '')['id'] ?? '');
            if ($rid !== '') {
                break;
            }
        }
        if (!$rid) {
            return null;
        }
        // Resolve rId -> target via workbook rels.
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }
        $rdoc = @simplexml_load_string($rels);
        if (!$rdoc) {
            return 'xl/worksheets/sheet1.xml';
        }
        foreach ($rdoc->Relationship as $relE) {
            if ((string) $relE['Id'] === $rid) {
                $target = (string) $relE['Target'];
                if (strpos($target, 'xl/') === 0) {
                    return $target;
                }
                return 'xl/' . ltrim($target, '/');
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @param array<int, string> $shared */
    private static function parseSheet(string $xml, array $shared): array
    {
        $doc = @simplexml_load_string($xml);
        if (!$doc) {
            return [];
        }
        $rows = [];
        $ns = $doc->getNamespaces(true);
        $sheetData = $doc->sheetData ?? $doc->children()->sheetData;
        foreach ($sheetData->row as $row) {
            $cells = [];
            $maxCol = -1;
            $byRef = [];
            foreach ($row->c as $c) {
                $ref = (string) ($c['r']);
                $colIdx = static::colIndex($ref);
                $type = (string) ($c['t'] ?? 'n');
                $value = '';
                if ($type === 'inlineStr') {
                    $value = (string) ($c->is->t ?? '');
                } elseif ($type === 's') {
                    $value = $shared[(int) trim((string) ($c->v ?? '0'))] ?? '';
                } else {
                    $value = trim((string) ($c->v ?? ''));
                }
                if ($colIdx >= 0) {
                    $byRef[$colIdx] = $value;
                    $maxCol = max($maxCol, $colIdx);
                }
            }
            for ($i = 0; $i <= $maxCol; $i++) {
                $cells[] = $byRef[$i] ?? '';
            }
            // Skip fully-empty rows.
            if (implode('', $cells) === '') {
                continue;
            }
            $rows[] = $cells;
        }
        return $rows;
    }

    private static function colIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)\d+$/', $ref, $m)) {
            return -1;
        }
        $letters = $m[1];
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }
}
