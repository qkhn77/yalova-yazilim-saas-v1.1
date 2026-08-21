<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberExportController extends Controller
{
    public function template()
    {
        $directory = storage_path('app/temp');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filePath = $directory.'/abone-aktarma-taslagi-'.now()->format('YmdHis').'.xlsx';
        $writer = WriterFactory::createFromFile($filePath);
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(['E-posta', 'Kaynak', 'Grup', 'Durum']));
        $writer->close();

        return response()->download($filePath, 'abone-aktarma-taslagi.xlsx')->deleteFileAfterSend(true);
    }

    public function csv(): StreamedResponse
    {
        $fileName = 'aboneler-'.now()->format('Y-m-d-H-i').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['E-posta', 'Durum', 'Kaynak', 'Grup', 'Abonelik Tarihi', 'Pasif Tarihi', 'IP']);

            NewsletterSubscriber::query()
                ->orderByDesc('subscribed_at')
                ->chunk(200, function ($records) use ($handle): void {
                    foreach ($records as $record) {
                        fputcsv($handle, [
                            $record->email,
                            $record->is_active ? 'Aktif' : 'Pasif',
                            $record->source,
                            $record->group_name,
                            optional($record->subscribed_at)?->format('d.m.Y H:i'),
                            optional($record->unsubscribed_at)?->format('d.m.Y H:i'),
                            $record->ip_address,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function excel(): StreamedResponse
    {
        $fileName = 'aboneler-'.now()->format('Y-m-d-H-i').'.xls';

        return response()->streamDownload(function (): void {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<tr>';
            echo '<th>E-posta</th>';
            echo '<th>Durum</th>';
            echo '<th>Kaynak</th>';
            echo '<th>Grup</th>';
            echo '<th>Abonelik Tarihi</th>';
            echo '<th>Pasif Tarihi</th>';
            echo '<th>IP</th>';
            echo '</tr>';

            NewsletterSubscriber::query()
                ->orderByDesc('subscribed_at')
                ->chunk(200, function ($records): void {
                    foreach ($records as $record) {
                        echo '<tr>';
                        echo '<td>'.e($record->email).'</td>';
                        echo '<td>'.e($record->is_active ? 'Aktif' : 'Pasif').'</td>';
                        echo '<td>'.e($record->source).'</td>';
                        echo '<td>'.e($record->group_name ?? '').'</td>';
                        echo '<td>'.e(optional($record->subscribed_at)?->format('d.m.Y H:i') ?? '').'</td>';
                        echo '<td>'.e(optional($record->unsubscribed_at)?->format('d.m.Y H:i') ?? '').'</td>';
                        echo '<td>'.e($record->ip_address ?? '').'</td>';
                        echo '</tr>';
                    }
                });

            echo '</table>';
            echo '</body></html>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
