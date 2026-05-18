<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CustomFormattedExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $title;
    protected $subtitle;
    protected $headings;
    protected $data;

    public function __construct(string $title, string $subtitle, array $headings, array $data)
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->headings = $headings;
        $this->data = collect($data);
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            [$this->title],
            [$this->subtitle],
            $this->headings
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge cells for title and subtitle based on number of columns
        $highestColumn = $sheet->getHighestColumn();
        
        $sheet->mergeCells("A1:{$highestColumn}1");
        $sheet->mergeCells("A2:{$highestColumn}2");

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A']
                ]
            ],
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '333333']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC']
                ]
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '10B981'] // Emerald 500
                ]
            ],
        ];
    }
}
