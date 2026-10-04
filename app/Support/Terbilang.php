<?php

namespace App\Support;

class Terbilang
{
    private static array $units = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'
    ];

    /**
     * Konversi angka nominal rupiah ke teks terbilang Bahasa Indonesia
     */
    public static function convert(float|int|string $number): string
    {
        $number = (float) $number;
        if ($number == 0) {
            return 'Nol Rupiah';
        }

        $formatted = self::spellOut(abs($number));
        $prefix = $number < 0 ? 'Minus ' : '';

        return trim($prefix . $formatted) . ' Rupiah';
    }

    private static function spellOut(float $number): string
    {
        $number = floor($number);

        if ($number < 12) {
            return self::$units[(int) $number];
        }

        if ($number < 20) {
            return self::spellOut($number - 10) . ' Belas';
        }

        if ($number < 100) {
            $ten = (int) floor($number / 10);
            $mod = $number % 10;
            return self::$units[$ten] . ' Puluh' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
        }

        if ($number < 200) {
            return 'Seratus' . ($number - 100 > 0 ? ' ' . self::spellOut($number - 100) : '');
        }

        if ($number < 1000) {
            $hundred = (int) floor($number / 100);
            $mod = $number % 100;
            return self::$units[$hundred] . ' Ratus' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
        }

        if ($number < 2000) {
            return 'Seribu' . ($number - 1000 > 0 ? ' ' . self::spellOut($number - 1000) : '');
        }

        if ($number < 1000000) {
            $thousand = (int) floor($number / 1000);
            $mod = $number % 1000;
            return self::spellOut($thousand) . ' Ribu' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
        }

        if ($number < 1000000000) {
            $million = (int) floor($number / 1000000);
            $mod = fmod($number, 1000000);
            return self::spellOut($million) . ' Juta' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
        }

        if ($number < 1000000000000) {
            $billion = (int) floor($number / 1000000000);
            $mod = fmod($number, 1000000000);
            return self::spellOut($billion) . ' Milyar' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
        }

        $trillion = (int) floor($number / 1000000000000);
        $mod = fmod($number, 1000000000000);
        return self::spellOut($trillion) . ' Triliun' . ($mod > 0 ? ' ' . self::spellOut($mod) : '');
    }
}
