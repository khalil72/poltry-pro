<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public static function stream(string $html, string $filename, string $orientation = 'portrait'): never
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', BASE_PATH . '/storage/cache');
        $options->set('chroot', BASE_PATH . '/public');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', $orientation);
        $pdf->render();
        $pdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}
