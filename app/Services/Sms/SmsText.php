<?php
declare(strict_types=1);
namespace App\Services\Sms;

/**
 * SMS length maths. GSM-7 text fits 160 characters in one SMS (153 per part when split);
 * anything outside GSM-7, such as Urdu, is sent as UCS-2 at 70 (67 per part).
 * public/assets/js/sms-center.js mirrors this for the live counter.
 */
final class SmsText
{
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private const GSM_EXTENDED = "^{}\\[~]|€\f";
    public const MAX_LENGTH = 1000;

    /** @return array{characters:int,units:int,segments:int,unicode:bool} */
    public static function analyse(string $text): array
    {
        $chars = mb_str_split($text);
        $unicode = false;
        $units = 0;
        foreach ($chars as $c) {
            if (mb_strpos(self::GSM, $c) !== false) {
                $units++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $c) !== false) {
                $units += 2;
            } else {
                $unicode = true;
                break;
            }
        }
        if ($unicode) {
            // UCS-2 counts UTF-16 code units, so emoji take two.
            $units = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);
        }
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];
        $segments = $units === 0 ? 0 : ($units <= $single ? 1 : (int) ceil($units / $multi));
        return ['characters' => count($chars), 'units' => $units, 'segments' => $segments, 'unicode' => $unicode];
    }
}
