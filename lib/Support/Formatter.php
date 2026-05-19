<?php

declare(strict_types=1);

namespace DigitalSac\MpContratos\Support;

/**
 * Brazilian formatting helpers: CPF, CNPJ, money, dates, phone, address.
 *
 * Every method is null-safe and returns the original value (or empty string)
 * when the input cannot be formatted.
 */
final class Formatter
{
    private const MONTHS_PT = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio',    6 => 'junho',    7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    private const CYCLES_PT = [
        'free'          => 'Gratuito',
        'onetime'       => 'Pagamento Único',
        'monthly'       => 'Mensal',
        'quarterly'     => 'Trimestral',
        'semiannually'  => 'Semestral',
        'annually'      => 'Anual',
        'biennially'    => 'Bienal',
        'triennially'   => 'Trienal',
    ];

    public static function digits(?string $value): string
    {
        return $value === null ? '' : (string) preg_replace('/\D+/', '', $value);
    }

    public static function document(?string $value): string
    {
        $d = self::digits($value);
        return match (strlen($d)) {
            11 => self::cpf($d),
            14 => self::cnpj($d),
            default => (string) ($value ?? ''),
        };
    }

    public static function cpf(?string $value): string
    {
        $d = self::digits($value);
        if (strlen($d) !== 11) {
            return (string) ($value ?? '');
        }
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.'
             . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public static function cnpj(?string $value): string
    {
        $d = self::digits($value);
        if (strlen($d) !== 14) {
            return (string) ($value ?? '');
        }
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
             . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }

    public static function cep(?string $value): string
    {
        $d = self::digits($value);
        if (strlen($d) !== 8) {
            return (string) ($value ?? '');
        }
        return substr($d, 0, 5) . '-' . substr($d, 5, 3);
    }

    public static function phone(?string $value): string
    {
        $d = self::digits($value);
        // Brazilian numbers may include country code 55
        if (strlen($d) > 11 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7, 4)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6, 4)),
            default => (string) ($value ?? ''),
        };
    }

    /**
     * E.164 with default country code 55 (Brazil). For WhatsApp APIs.
     */
    public static function phoneE164(?string $value, string $defaultCountry = '55'): string
    {
        $d = self::digits($value);
        if ($d === '') {
            return '';
        }
        if (!str_starts_with($d, $defaultCountry) && strlen($d) <= 11) {
            $d = $defaultCountry . $d;
        }
        return '+' . $d;
    }

    public static function money(float|int|string|null $value, string $currency = 'R$'): string
    {
        if ($value === null || $value === '') {
            return $currency . ' 0,00';
        }
        if (is_string($value)) {
            // Accept "1.234,56" or "1234.56"
            $clean = preg_replace('/[^\d,.\-]/', '', $value);
            if (substr_count($clean, ',') === 1 && substr_count($clean, '.') >= 0) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            }
            $value = (float) $clean;
        }
        return $currency . ' ' . number_format((float) $value, 2, ',', '.');
    }

    public static function moneyExtenso(float|int|string|null $value): string
    {
        $n = is_string($value) ? (float) str_replace([',', '.'], ['.', ''], $value) : (float) ($value ?? 0);
        $reais = (int) floor($n);
        $centavos = (int) round(($n - $reais) * 100);
        $parts = [];
        if ($reais > 0) {
            $parts[] = self::numberExtenso($reais) . ' ' . ($reais === 1 ? 'real' : 'reais');
        }
        if ($centavos > 0) {
            $parts[] = self::numberExtenso($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
        }
        return empty($parts) ? 'zero reais' : implode(' e ', $parts);
    }

    /**
     * Integer to extenso (Portuguese). Supports up to billions; adequate
     * for any realistic contract value.
     */
    public static function numberExtenso(int $n): string
    {
        if ($n === 0) return 'zero';
        if ($n < 0) return 'menos ' . self::numberExtenso(-$n);

        static $units = ['','um','dois','três','quatro','cinco','seis','sete','oito','nove'];
        static $tens10_19 = ['dez','onze','doze','treze','quatorze','quinze','dezesseis','dezessete','dezoito','dezenove'];
        static $tens = ['','','vinte','trinta','quarenta','cinquenta','sessenta','setenta','oitenta','noventa'];
        static $hundreds = ['','cento','duzentos','trezentos','quatrocentos','quinhentos','seiscentos','setecentos','oitocentos','novecentos'];

        $say = static function (int $x) use (&$say, $units, $tens10_19, $tens, $hundreds): string {
            if ($x === 100) return 'cem';
            $out = [];
            $h = intdiv($x, 100);
            $rem = $x % 100;
            if ($h > 0) $out[] = $hundreds[$h];
            if ($rem >= 10 && $rem < 20) {
                $out[] = $tens10_19[$rem - 10];
            } else {
                $t = intdiv($rem, 10);
                $u = $rem % 10;
                if ($t > 0) $out[] = $tens[$t];
                if ($u > 0) {
                    if ($t > 0) $out[] = 'e ' . $units[$u];
                    else $out[] = $units[$u];
                }
            }
            return implode(' e ', $out);
        };

        if ($n < 1000) return $say($n);

        $parts = [];
        $bilhoes  = intdiv($n, 1_000_000_000); $n %= 1_000_000_000;
        $milhoes  = intdiv($n, 1_000_000);     $n %= 1_000_000;
        $milhares = intdiv($n, 1_000);          $n %= 1_000;
        $resto    = $n;

        if ($bilhoes > 0)  $parts[] = $say($bilhoes) . ($bilhoes === 1 ? ' bilhão' : ' bilhões');
        if ($milhoes > 0)  $parts[] = $say($milhoes) . ($milhoes === 1 ? ' milhão' : ' milhões');
        if ($milhares > 0) $parts[] = ($milhares === 1 ? 'mil' : $say($milhares) . ' mil');
        if ($resto > 0)    $parts[] = $say($resto);

        return implode(' e ', $parts);
    }

    public static function date(?string $value, string $format = 'd/m/Y'): string
    {
        if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($value))->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function dateExtenso(\DateTimeInterface|string|null $value = null, ?string $cidade = null): string
    {
        try {
            $dt = $value instanceof \DateTimeInterface
                ? $value
                : ($value ? new \DateTimeImmutable($value) : new \DateTimeImmutable());
        } catch (\Throwable) {
            $dt = new \DateTimeImmutable();
        }
        $extenso = sprintf(
            '%d de %s de %d',
            (int) $dt->format('d'),
            self::MONTHS_PT[(int) $dt->format('n')],
            (int) $dt->format('Y'),
        );
        return $cidade ? "{$cidade}, {$extenso}" : $extenso;
    }

    public static function billingCycle(?string $cycle): string
    {
        $k = strtolower((string) $cycle);
        return self::CYCLES_PT[$k] ?? (string) ($cycle ?? '');
    }

    /**
     * Build a full address line from a WHMCS client row (array).
     */
    public static function fullAddress(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['address1'] ?? '')),
            trim((string) ($row['address2'] ?? '')),
            trim((string) ($row['city']     ?? '')),
            trim((string) ($row['state']    ?? '')),
            self::cep((string) ($row['postcode'] ?? '')),
            trim((string) ($row['country']  ?? '')),
        ], static fn ($p) => $p !== '');
        return implode(', ', $parts);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
