<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\ShipmentSyncService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ShipmentsController extends Controller
{
    public function index(Request $request, ShipmentSyncService $service)
    {
        $status = $request->input('status');
        $provinsi = $request->input('provinsi');
        $stagging = $request->input('stagging');
        $vendor = $request->input('vendor');
        $sla = $request->input('sla');
        $resi = $request->input('resi');
        $search = trim((string) $request->input('search'));

        $shipments = $this->filteredQuery($request)
            ->orderByDesc('tanggal_efektif')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $statuses = Shipment::query()
            ->select('status_akhir')
            ->distinct()
            ->whereNotNull('status_akhir')
            ->orderBy('status_akhir')
            ->pluck('status_akhir');
        $provinces = Shipment::query()
            ->select('provinsi')
            ->distinct()
            ->whereNotNull('provinsi')
            ->orderBy('provinsi')
            ->pluck('provinsi');
        $staggingList = Shipment::query()
            ->select('stagging')
            ->distinct()
            ->whereNotNull('stagging')
            ->orderBy('stagging')
            ->pluck('stagging');
        $vendors = Shipment::query()
            ->select('vendor_lm')
            ->distinct()
            ->whereNotNull('vendor_lm')
            ->where('vendor_lm', '<>', '')
            ->orderBy('vendor_lm')
            ->pluck('vendor_lm');

        return view('shipments', compact(
            'status',
            'provinsi',
            'stagging',
            'vendor',
            'sla',
            'resi',
            'search',
            'shipments',
            'statuses',
            'provinces',
            'staggingList',
            'vendors',
        ));
    }

    public function export(Request $request)
    {
        $query = $this->filteredQuery($request)
            ->orderByDesc('tanggal_efektif')
            ->orderByDesc('id');

        $columns = [
            'no_resi' => 'No Resi',
            'resi_catatan' => 'Catatan Resi',
            'nomor_redock' => 'Nomor Redock',
            'delivery_order' => 'Delivery Order',
            'nama_sekolah' => 'Nama Sekolah',
            'nama_penerima' => 'Nama Penerima',
            'provinsi' => 'Provinsi',
            'daerah' => 'Daerah',
            'kota_kabupaten' => 'Kota / Kabupaten',
            'kecamatan' => 'Kecamatan',
            'vendor_mm' => 'Vendor MM',
            'kode_funder' => 'Kode Funder',
            'nama_funder' => 'Nama Funder',
            'vendor_lm' => 'Vendor LM',
            'tanggal_manifest' => 'Tanggal Manifest',
            'tanggal_efektif' => 'Tanggal Acuan',
            'tanggal_sumber' => 'Sumber Tanggal Acuan',
            'completed_date' => 'Tanggal Selesai',
            'tgl_sampai_kota_tujuan' => 'Tgl Sampai Kota Tujuan',
            'aging' => 'Aging (hari)',
            'status_akhir' => 'Status Akhir',
            'status_instalasi' => 'Status Instalasi',
            'harga_per_shipment' => 'Harga / Shipment',
            'status_invoice' => 'Status Invoice',
            'stagging' => 'Stagging',
            'sla' => 'SLA (hari)',
            'sla_result' => 'SLA Result',
            'bast_tgl_balik' => 'BAST Tgl Balik',
            'bast_tgl_ke_finance' => 'BAST Tgl ke Finance',
        ];

        $dateFields = [
            'tanggal_manifest',
            'tanggal_efektif',
            'completed_date',
            'tgl_sampai_kota_tujuan',
            'bast_tgl_balik',
            'bast_tgl_ke_finance',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pengiriman');

        $maxLengths = [];
        foreach (array_values($columns) as $i => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $label);
            $maxLengths[$i] = mb_strlen($label);
        }

        $rowIndex = 2;

        $query->chunk(1000, function ($shipments) use ($sheet, $columns, $dateFields, &$rowIndex, &$maxLengths) {
            foreach ($shipments as $ship) {
                foreach (array_keys($columns) as $i => $field) {
                    $value = $ship->{$field};
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1).$rowIndex);

                    if (in_array($field, $dateFields, true) && $value instanceof \DateTimeInterface) {
                        $cell->setValue($value);
                        $length = mb_strlen($value->format('Y-m-d'));
                    } elseif ($value === null || $value === '') {
                        $cell->setValue(null);
                        $length = 0;
                    } elseif ($field === 'harga_per_shipment') {
                        $cell->setValue((float) $value);
                        $length = mb_strlen((string) $value);
                    } else {
                        $cell->setValue($value);
                        $length = mb_strlen((string) $value);
                    }

                    $maxLengths[$i] = max($maxLengths[$i], $length);
                }

                $rowIndex++;
            }
        });

        $lastDataRow = $rowIndex - 1;
        $totalColumns = count($columns);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($totalColumns);

        foreach ($maxLengths as $i => $len) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth(min($len + 3, 50));
        }

        $headerRange = 'A1:'.$lastColumnLetter.'1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB(Color::COLOR_WHITE);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0891B2');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->freezePane('A2');

        if ($lastDataRow >= 1) {
            $tableRange = 'A1:'.$lastColumnLetter.$lastDataRow;
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFCBD5E1');

            if ($lastDataRow > 1) {
                for ($r = 2; $r <= $lastDataRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle('A'.$r.':'.$lastColumnLetter.$r)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF8FAFC');
                    }
                }

                $aggColumns = ['aging', 'sla', 'harga_per_shipment'];
                foreach ($aggColumns as $field) {
                    $col = Coordinate::stringFromColumnIndex(array_search($field, array_keys($columns), true) + 1);
                    $sheet->getStyle($col.'2:'.$col.$lastDataRow)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $hargaCol = Coordinate::stringFromColumnIndex(array_search('harga_per_shipment', array_keys($columns), true) + 1);
                $sheet->getStyle($hargaCol.'2:'.$hargaCol.$lastDataRow)
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                foreach ($dateFields as $field) {
                    $col = Coordinate::stringFromColumnIndex(array_search($field, array_keys($columns), true) + 1);
                    $sheet->getStyle($col.'2:'.$col.$lastDataRow)
                        ->getNumberFormat()->setFormatCode('YYYY-MM-DD');
                }

                $sheet->setAutoFilter($tableRange);
            }
        }

        $metaRow = $lastDataRow + 2;
        $filterParts = [];

        if ($status = $request->input('status')) {
            $filterParts[] = 'status: '.$status;
        }
        if ($provinsi = $request->input('provinsi')) {
            $filterParts[] = 'provinsi: '.$provinsi;
        }
        if ($stagging = $request->input('stagging')) {
            $filterParts[] = 'stagging: '.$stagging;
        }
        if ($vendor = $request->input('vendor')) {
            $filterParts[] = 'Vendor LM: '.$vendor;
        }
        if ($request->input('sla') === 'out') {
            $filterParts[] = 'SLA: Out SLA';
        } elseif ($request->input('sla') === 'meet') {
            $filterParts[] = 'SLA: Meet SLA';
        }

        if ($resi = $request->input('resi')) {
            $filterParts[] = match ($resi) {
                'invalid' => 'Resi: tidak valid',
                'duplicate' => 'Resi: duplikat',
                default => 'Resi: '.$resi,
            };
        }

        if ($search = trim((string) $request->input('search'))) {
            $filterParts[] = 'cari: "'.$search.'"';
        }

        $meta = 'Dibuat '.now()->format('d M Y H:i').' | '.number_format($lastDataRow, 0, ',', '.').' baris';
        if ($filterParts) {
            $meta .= ' | '.implode(' | ', $filterParts);
        }

        $sheet->setCellValue('A'.$metaRow, $meta);
        $sheet->mergeCells('A'.$metaRow.':'.$lastColumnLetter.$metaRow);
        $sheet->getStyle('A'.$metaRow)->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF64748B');

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $temp = tempnam(sys_get_temp_dir(), 'anl_export_');
        $writer->save($temp);

        $filename = 'pengiriman_'.now()->format('Ymd_His').'.xlsx';

        return response()->download($temp, $filename)->deleteFileAfterSend(true);
    }

    private function filteredQuery(Request $request)
    {
        $query = Shipment::query();

        if ($status = $request->input('status')) {
            $query->where('status_akhir', $status);
        }

        if ($provinsi = $request->input('provinsi')) {
            $query->where('provinsi', $provinsi);
        }

        if ($stagging = $request->input('stagging')) {
            $query->where('stagging', $stagging);
        }

        $query->forVendor($request->input('vendor'));

        $sla = $request->input('sla');
        if ($sla === 'out') {
            $query->where('sla_result', 'Out SLA');
        } elseif ($sla === 'meet') {
            $query->where('sla_result', 'Meet SLA');
        }

        $resi = $request->input('resi');
        if ($resi === 'invalid') {
            $query->where('resi_valid', false);
        } elseif ($resi === 'duplicate') {
            $query->where('resi_catatan', ShipmentSyncService::RESI_CATATAN_DUPLIKAT);
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

            $query->where(function ($q) use ($escaped) {
                $q->where('no_resi', 'like', "%{$escaped}%")
                    ->orWhere('nama_sekolah', 'like', "%{$escaped}%")
                    ->orWhere('nama_penerima', 'like', "%{$escaped}%");
            });
        }

        return $query;
    }
}
