<?php

namespace App\Support\Qr;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

final class QrSvgUretici
{
    public static function uret(string $data, int $boyut = 140, int $kenar = 8): ?string
    {
        $data = trim($data);
        if ($data === '') {
            return null;
        }

        try {
            $writer = new SvgWriter();
            $qr = new QrCode(
                data: $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: max(80, $boyut),
                margin: max(0, $kenar),
                roundBlockSizeMode: RoundBlockSizeMode::None
            );

            return $writer->write($qr)->getString();
        } catch (\Throwable) {
            return null;
        }
    }
}

