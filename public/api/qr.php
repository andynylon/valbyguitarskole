<?php
declare(strict_types=1);

/**
 * Byte-mode QR encoder (versions 4–5, error level L) and a tiny PNG writer.
 * No extensions beyond zlib, which PHP includes.
 */

function qr_png(string $text, int $scale = 8, int $quiet = 4): string
{
    $matrix = qr_matrix($text);
    $modules = count($matrix);
    $size = ($modules + $quiet * 2) * $scale;
    $raw = '';
    for ($y = 0; $y < $size; $y++) {
        $raw .= "\x00";
        $moduleY = intdiv($y, $scale) - $quiet;
        for ($x = 0; $x < $size; $x++) {
            $moduleX = intdiv($x, $scale) - $quiet;
            $dark = $moduleY >= 0 && $moduleX >= 0 && $moduleY < $modules && $moduleX < $modules && $matrix[$moduleY][$moduleX] === 1;
            $raw .= $dark ? "\x00" : "\xff";
        }
    }

    return qr_png_pack($size, $size, $raw);
}

function qr_matrix(string $text): array
{
    $bytes = $text === '' ? [] : array_values(unpack('C*', $text) ?: []);
    $version = count($bytes) <= 76 ? 4 : 5;
    if (count($bytes) > 104) {
        throw new RuntimeException('QR payload is too long');
    }

    $spec = [
        4 => ['size' => 33, 'data' => 80, 'ecc' => 20, 'align' => [6, 26]],
        5 => ['size' => 37, 'data' => 108, 'ecc' => 26, 'align' => [6, 30]],
    ][$version];
    $data = qr_data_codewords($bytes, $spec['data']);
    [$exp, $log] = qr_gf();
    $ecc = qr_rs_remainder($data, $spec['ecc'], $exp, $log);
    $codewords = array_merge($data, $ecc);

    $size = $spec['size'];
    $modules = array_fill(0, $size, array_fill(0, $size, 0));
    $function = array_fill(0, $size, array_fill(0, $size, false));
    qr_reserve_patterns($modules, $function, $size, $spec['align']);

    $free = 0;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!$function[$y][$x]) {
                $free++;
            }
        }
    }
    $expected = (count($codewords) * 8) + 7;
    if ($free !== $expected) {
        throw new RuntimeException('QR module count does not match the codewords');
    }

    qr_place_data($modules, $function, $size, $codewords);
    $mask = qr_best_mask($modules, $function, $size);
    qr_apply_mask($modules, $function, $size, $mask);
    qr_draw_format($modules, $function, $size, qr_format_bits(1, $mask));

    return $modules;
}

function qr_data_codewords(array $bytes, int $capacity): array
{
    $bits = '0100' . sprintf('%08b', count($bytes));
    foreach ($bytes as $byte) {
        $bits .= sprintf('%08b', $byte);
    }
    $limit = $capacity * 8;
    $terminator = min(4, max(0, $limit - strlen($bits)));
    $bits .= str_repeat('0', $terminator);
    if (strlen($bits) % 8 !== 0) {
        $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
    }
    $data = [];
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $data[] = bindec(substr($bits, $i, 8));
    }
    $pad = 0xEC;
    while (count($data) < $capacity) {
        $data[] = $pad;
        $pad = $pad === 0xEC ? 0x11 : 0xEC;
    }

    return $data;
}

function qr_gf(): array
{
    static $exp = null;
    static $log = null;
    if ($exp !== null && $log !== null) {
        return [$exp, $log];
    }
    $exp = [];
    $log = array_fill(0, 256, 0);
    $value = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $value;
        $log[$value] = $i;
        $value <<= 1;
        if (($value & 0x100) !== 0) {
            $value ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }

    return [$exp, $log];
}

function qr_mul(int $left, int $right, array $exp, array $log): int
{
    if ($left === 0 || $right === 0) {
        return 0;
    }

    return $exp[$log[$left] + $log[$right]];
}

function qr_rs_remainder(array $data, int $eccLength, array $exp, array $log): array
{
    $generator = [1];
    for ($i = 0; $i < $eccLength; $i++) {
        $next = array_fill(0, count($generator) + 1, 0);
        foreach ($generator as $index => $coefficient) {
            $next[$index] ^= $coefficient;
            $next[$index + 1] ^= qr_mul($coefficient, $exp[$i], $exp, $log);
        }
        $generator = $next;
    }

    $message = array_merge($data, array_fill(0, $eccLength, 0));
    $dataLength = count($data);
    for ($i = 0; $i < $dataLength; $i++) {
        $coefficient = $message[$i];
        if ($coefficient === 0) {
            continue;
        }
        foreach ($generator as $index => $term) {
            $message[$i + $index] ^= qr_mul($term, $coefficient, $exp, $log);
        }
    }

    return array_slice($message, $dataLength);
}

function qr_reserve_patterns(array &$modules, array &$function, int $size, array $align): void
{
    qr_finder($modules, $function, $size, 0, 0);
    qr_finder($modules, $function, $size, 0, $size - 7);
    qr_finder($modules, $function, $size, $size - 7, 0);

    foreach ([0, 1, 2, 3, 4, 5, 7] as $index) {
        $function[$index][8] = true;
        $function[8][$index] = true;
    }
    $function[8][8] = true;
    for ($index = 0; $index < 8; $index++) {
        $function[8][$size - 1 - $index] = true;
        $function[$size - 1 - $index][8] = true;
    }
    $modules[$size - 8][8] = 1;
    $function[$size - 8][8] = true;

    for ($i = 0; $i < $size; $i++) {
        if (!$function[6][$i]) {
            $modules[6][$i] = $i % 2 === 0 ? 1 : 0;
            $function[6][$i] = true;
        }
        if (!$function[$i][6]) {
            $modules[$i][6] = $i % 2 === 0 ? 1 : 0;
            $function[$i][6] = true;
        }
    }

    foreach ($align as $row) {
        foreach ($align as $column) {
            if ($function[$row][$column] ?? false) {
                continue;
            }
            qr_alignment($modules, $function, $row, $column);
        }
    }
}

function qr_finder(array &$modules, array &$function, int $size, int $row, int $column): void
{
    for ($y = -1; $y <= 7; $y++) {
        for ($x = -1; $x <= 7; $x++) {
            $targetY = $row + $y;
            $targetX = $column + $x;
            if ($targetY < 0 || $targetX < 0 || $targetY >= $size || $targetX >= $size) {
                continue;
            }
            $inside = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;
            $edge = $x === 0 || $x === 6 || $y === 0 || $y === 6;
            $core = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
            $modules[$targetY][$targetX] = ($inside && ($edge || $core)) ? 1 : 0;
            $function[$targetY][$targetX] = true;
        }
    }
}

function qr_alignment(array &$modules, array &$function, int $row, int $column): void
{
    for ($y = -2; $y <= 2; $y++) {
        for ($x = -2; $x <= 2; $x++) {
            $modules[$row + $y][$column + $x] = max(abs($x), abs($y)) === 1 ? 0 : 1;
            $function[$row + $y][$column + $x] = true;
        }
    }
}

function qr_place_data(array &$modules, array $function, int $size, array $codewords): void
{
    $bitCount = count($codewords) * 8;
    $index = 0;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($shift = 0; $shift < 2; $shift++) {
                $x = $right - $shift;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if ($function[$y][$x] || $index >= $bitCount) {
                    continue;
                }
                $byte = $codewords[intdiv($index, 8)];
                $modules[$y][$x] = (($byte >> (7 - ($index % 8))) & 1) === 1 ? 1 : 0;
                $index++;
            }
        }
    }
}

function qr_mask_bit(int $mask, int $x, int $y): bool
{
    return match ($mask) {
        0 => ($x + $y) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($x + $y) % 3 === 0,
        4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
        5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
        6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
        default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
    };
}

function qr_apply_mask(array &$modules, array $function, int $size, int $mask): void
{
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!$function[$y][$x] && qr_mask_bit($mask, $x, $y)) {
                $modules[$y][$x] ^= 1;
            }
        }
    }
}

function qr_best_mask(array $modules, array $function, int $size): int
{
    $bestMask = 0;
    $bestScore = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $copy = $modules;
        qr_apply_mask($copy, $function, $size, $mask);
        qr_draw_format($copy, $function, $size, qr_format_bits(1, $mask));
        $score = qr_penalty($copy, $size);
        if ($score < $bestScore) {
            $bestScore = $score;
            $bestMask = $mask;
        }
    }

    return $bestMask;
}

function qr_format_bits(int $level, int $mask): int
{
    $data = ($level << 3) | $mask;
    $remainder = $data << 10;
    for ($bit = 14; $bit >= 10; $bit--) {
        if ((($remainder >> $bit) & 1) === 1) {
            $remainder ^= 0x537 << ($bit - 10);
        }
    }

    return (($data << 10) | ($remainder & 0x3FF)) ^ 0x5412;
}

function qr_draw_format(array &$modules, array $function, int $size, int $bits): void
{
    $bit = static function (int $bits, int $index): int {
        return ($bits >> $index) & 1;
    };
    for ($i = 0; $i <= 5; $i++) {
        $modules[$i][8] = $bit($bits, $i);
    }
    $modules[7][8] = $bit($bits, 6);
    $modules[8][8] = $bit($bits, 7);
    $modules[8][7] = $bit($bits, 8);
    for ($i = 9; $i < 15; $i++) {
        $modules[8][14 - $i] = $bit($bits, $i);
    }
    for ($i = 0; $i < 8; $i++) {
        $modules[8][$size - 1 - $i] = $bit($bits, $i);
    }
    for ($i = 8; $i < 15; $i++) {
        $modules[$size - 15 + $i][8] = $bit($bits, $i);
    }
    $modules[$size - 8][8] = 1;
}

function qr_penalty(array $modules, int $size): int
{
    $score = 0;
    foreach ([false, true] as $vertical) {
        for ($index = 0; $index < $size; $index++) {
            $run = 1;
            $previous = -1;
            for ($step = 0; $step < $size; $step++) {
                $color = $vertical ? $modules[$step][$index] : $modules[$index][$step];
                if ($color === $previous) {
                    $run++;
                    if ($run === 5) {
                        $score += 3;
                    } elseif ($run > 5) {
                        $score++;
                    }
                } else {
                    $run = 1;
                    $previous = $color;
                }
            }
        }
    }

    for ($y = 0; $y < $size - 1; $y++) {
        for ($x = 0; $x < $size - 1; $x++) {
            $color = $modules[$y][$x];
            if ($color === $modules[$y][$x + 1] && $color === $modules[$y + 1][$x] && $color === $modules[$y + 1][$x + 1]) {
                $score += 3;
            }
        }
    }

    $pattern = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
    $reversed = array_reverse($pattern);
    $score += qr_finder_penalty($modules, $size, $pattern);
    $score += qr_finder_penalty($modules, $size, $reversed);

    $dark = 0;
    foreach ($modules as $row) {
        foreach ($row as $color) {
            $dark += $color;
        }
    }
    $percent = (int) (abs((100 * $dark / ($size * $size)) - 50) / 5);
    $score += $percent * 10;

    return $score;
}

function qr_finder_penalty(array $modules, int $size, array $pattern): int
{
    $length = count($pattern);
    $score = 0;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x <= $size - $length; $x++) {
            $rowMatch = true;
            $columnMatch = true;
            for ($i = 0; $i < $length; $i++) {
                if ($modules[$y][$x + $i] !== $pattern[$i]) {
                    $rowMatch = false;
                }
                if ($modules[$x + $i][$y] !== $pattern[$i]) {
                    $columnMatch = false;
                }
            }
            if ($rowMatch) {
                $score += 40;
            }
            if ($columnMatch) {
                $score += 40;
            }
        }
    }

    return $score;
}

function qr_png_pack(int $width, int $height, string $raw): string
{
    $ihdr = pack('NNC5', $width, $height, 8, 0, 0, 0, 0);
    $idat = gzcompress($raw, 9);
    if ($idat === false) {
        throw new RuntimeException('QR image could not be compressed');
    }

    return "\x89PNG\r\n\x1a\n" . qr_png_chunk('IHDR', $ihdr) . qr_png_chunk('IDAT', $idat) . qr_png_chunk('IEND', '');
}

function qr_png_chunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}
