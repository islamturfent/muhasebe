<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Minimal dependency-free PDF writer (A4, multi-page, text + horizontal rules).
 *
 * Uses WinAnsiEncoding so ç/ö/ü etc. are preserved; the few characters missing
 * from WinAnsi (ş, ğ, ı, İ) are transliterated to ASCII so output is always valid.
 */
final class Pdf
{
    private array $objects = [];
    private string $content = '';
    private float $y;              // current vertical cursor (from top)
    private float $topMargin = 40;
    private float $bottomLimit = 50;
    private float $lineHeight;
    private float $fontSize;
    private int $page = 0;

    private const W = 595;
    private const H = 842;

    // Approximate Helvetica widths (per 1000 units) for a few sizes — used for centering.
    private const WIDTHS = [
        'H' => 722, 'W' => 944, 'm' => 833, 'w' => 833, 'B' => 667, 'D' => 722, 'O' => 722, 'Q' => 722,
        'M' => 778, 'N' => 722, 'T' => 611, 'b' => 556, 'd' => 556, 'g' => 556, 'h' => 556, 'k' => 556,
    ];

    public function __construct(float $fontSize = 9.5)
    {
        $this->fontSize = $fontSize;
        $this->lineHeight = $fontSize * 1.45;
        $this->y = $this->topMargin;
    }

    private function ascii(string $s): string
    {
        $map = ['ş' => 's', 'Ş' => 'S', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'İ' => 'I'];
        return strtr($s, $map);
    }

    /** Approx width of a string in points at current font size. */
    private function textWidth(string $s): float
    {
        $w = 0;
        foreach (str_split($s) as $ch) {
            $w += self::WIDTHS[$ch] ?? 556;
        }
        return $w / 1000 * $this->fontSize;
    }

    public function title(string $text): void
    {
        $this->ensureSpace(30);
        $this->textCentered($text, 16);
        $this->moveY(2);
        $this->rule();
        $this->moveY(10);
    }

    public function subheading(string $text): void
    {
        $this->ensureSpace(18);
        $this->text($this->left(), $this->y, $text, $this->fontSize + 1);
        $this->moveY($this->lineHeight + 3);
    }

    /** Draw a table row. $widths = [w1, w2, ...]; $cells aligned left except last right-aligned. */
    public function tableRow(array $cells, array $widths, bool $header = false): void
    {
        $this->ensureSpace($this->lineHeight + 6);
        $x = $this->left();
        foreach ($cells as $i => $cell) {
            $w = $widths[$i] ?? 60;
            $colWidth = $w;
            $text = (string) $cell;
            // truncate to fit
            while ($text !== '' && $this->textWidth($text) > $colWidth - 4) {
                $text = mb_substr($text, 0, -1);
            }
            $this->text($x, $this->y, $header ? $text : $text, $this->fontSize);
            $x += $colWidth;
        }
        if ($header) {
            $this->rule();
        }
        $this->moveY($this->lineHeight);
    }

    public function textLine(string $text): void
    {
        $this->ensureSpace($this->lineHeight);
        $this->text($this->left(), $this->y, $text, $this->fontSize);
        $this->moveY($this->lineHeight);
    }

    public function spacer(float $pts = 8): void
    {
        $this->ensureSpace($pts);
        $this->moveY($pts);
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y + $needed > self::H - $this->bottomLimit) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        $this->page++;
        // push a page-break (empty) into current page content then reset cursor
        $this->content .= "BT /F1 {$this->fontSize} Tf 0.2 0.2 0.2 rg\n";
        $this->content .= "ET\n";
        $this->y = $this->topMargin;
    }

    private function left(): float
    {
        return 48;
    }

    private function rule(): void
    {
        $this->content .= sprintf("0.85 0.85 0.85 RG 0.6 w %0.1f %0.1f m %0.1f %0.1f l S\n", $this->left(), $this->y + 1, self::W - $this->left(), $this->y + 1);
    }

    private function textCentered(string $text, float $size): void
    {
        $w = $this->textWidth($this->ascii($text));
        $x = (self::W - $w) / 2;
        $this->text($x, $this->y, $text, $size);
        $this->moveY($size * 1.5);
    }

    private function text(float $x, float $yFromTop, string $text, float $size): void
    {
        $yPdf = self::H - $yFromTop; // PDF origin at bottom
        $enc = mb_convert_encoding($this->ascii($text), 'CP1252', 'UTF-8');
        $enc = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $enc);
        $this->content .= sprintf("BT /F1 %0.2f Tf 0 0 0 rg %0.1f %0.1f Td (%s) Tj ET\n", $size, $x, $yPdf, $enc);
    }

    private function moveY(float $d): void
    {
        $this->y += $d;
    }

    public function output(): string
    {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::W . " " . self::H . "] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";

        $stream = $this->content;
        $objects[5] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $n = count($objects);
        for ($i = 1; $i <= $n; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($n + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $n; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($n + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";
        return $pdf;
    }
}
