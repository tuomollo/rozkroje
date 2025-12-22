<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Project;
use App\Models\UploadSession;
use App\Services\MaterialProcessingHooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;
use Illuminate\Support\Facades\Log;
use App\Services\ProgramConfig;

class FileProcessingController extends Controller
{
    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'file' => 'required|file',
        ]);

        $project = Project::with('client')->findOrFail($validated['project_id']);
        $file = $validated['file'];
        $token = (string) Str::uuid();
        $dir = "uploads/{$token}";
        Storage::makeDirectory($dir);
        $storedName = 'source.' . $file->getClientOriginalExtension();
        $path = Storage::putFileAs($dir, $file, $storedName);

        UploadSession::create([
            'project_id' => $project->id,
            'token' => $token,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
            'created_by' => optional($request->user())->id,
        ]);
        $spreadsheet = IOFactory::load(Storage::path($path));
        $unknownMaterials = $this->collectUnknownMaterials($spreadsheet);

        return response()->json([
            'upload_token' => $token,
            'unknown_materials' => $unknownMaterials,
            'material_types' => MaterialType::orderBy('name')->get(),
            'remarks' => $this->addRemarks($spreadsheet),
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_token' => 'required|string|exists:upload_sessions,token',
            'project_id' => 'required|exists:projects,id',
            'assignments' => 'array',
            'assignments.*.name' => 'required|string',
            'assignments.*.material_type_id' => 'required|exists:material_types,id',
            'assignments.*.has_grain' => 'boolean',
        ]);

        $session = UploadSession::where('token', $validated['upload_token'])->firstOrFail();
        $project = Project::with('client')->findOrFail($validated['project_id']);

        if (!Storage::exists($session->file_path)) {
            return response()->json(['message' => 'Uploaded file not found. Please start over.'], 404);
        }

        if ($session->project_id !== $project->id) {
            return response()->json(['message' => 'Upload token does not match project.'], 422);
        }

        $assignments = collect($validated['assignments'] ?? []);
        $assignments->each(function (array $assignment) {
            $typeId = $assignment['material_type_id'];
            $hasGrain = (bool) ($assignment['has_grain'] ?? false);

            Material::updateOrCreate(
                ['name' => $assignment['name']],
                [
                    'material_type_id' => $typeId,
                    'has_grain' => $hasGrain,
                ]
            );
        });

        $sourceSpreadsheet = IOFactory::load(Storage::path($session->file_path));
        $remarks = $this->addRemarks($sourceSpreadsheet);
        $author = optional($request->user())->name ?? 'Nieznany';

        $result = $this->processSpreadsheet($session, $project, $remarks, $author);

        $session->update([
            'result_path' => $result['zip_path'],
            'status' => 'processed',
        ]);

        return response()->json([
            'download_url' => url('/api/downloads/' . $session->token),
            'files' => $result['files'],
            'file_urls' => $result['file_urls'],
            'remarks' => $remarks
        ]);
    }

    public function download(string $token, ?string $filename = null)
    {
        $session = UploadSession::where('token', $token)->firstOrFail();

        if ($filename) {
            $exportPath = "exports/{$session->token}/{$filename}";
            if (!Storage::exists($exportPath)) {
                return response()->json(['message' => 'Plik nie jest dostępny.'], 404);
            }

            return Storage::download($exportPath);
        }

        if (!$session->result_path || !Storage::exists($session->result_path)) {
            return response()->json(['message' => 'Plik nie jest dostępny.'], 404);
        }

        return response()->download(Storage::path($session->result_path));
    }

    private function addRemarks(Spreadsheet $spreadsheet): array
    {

        $lengthColumnIndex = ProgramConfig::getConfig('length_column_index',2);
        $widthColumIndex = ProgramConfig::getConfig('width_column_index',3);
        $absLengthColumnIndex = ProgramConfig::getConfig('abs_length_column_index',6);
        $absWidthColumnIndex = ProgramConfig::getConfig('abs_width_column_index',7);
        $thicknessColumnIndex = ProgramConfig::getConfig('thickness_column_index',5);
        $maxHDFThickness = ProgramConfig::getConfig('max_hdf_thickness',5);
        $nameColumnIndex = ProgramConfig::getConfig('name_column_index',2);
        $grainContinuationColumnIndex = ProgramConfig::getConfig('grain_continuation_column_index',11);
        $materialColumnIndex = ProgramConfig::getConfig('material_column_index',10);


        $maxLength = ProgramConfig::getConfig('max_length',2800);
        $maxWidth = ProgramConfig::getConfig('max_width',2070);

        $remarks = [];

        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestDataRow();
        for ($i = 2; $i < $highestRow; $i++) {
            $test = $sheet->getCell([$lengthColumnIndex, $i])->getCalculatedValue();
            if (is_numeric($test)) {
                if ($sheet->getCell([$lengthColumnIndex, $i])->getCalculatedValue() > $maxLength) {
                    $remarks[] = "Wiersz {$i}: Długość większa niż {$maxLength} mm.";
                }
                if ($sheet->getCell([$widthColumIndex, $i])->getCalculatedValue() > $maxWidth) {
                    $remarks[] = "Wiersz {$i}: Szerokość większa niż {$maxWidth} mm.";
                }
                if (
                    ($sheet->getCell([$absLengthColumnIndex, $i])->getCalculatedValue() == 0)&&
                    ($sheet->getCell([$absWidthColumnIndex, $i])->getCalculatedValue() == 0)&&
                    ($sheet->getCell([$thicknessColumnIndex, $i])->getCalculatedValue() > $maxHDFThickness)
                   ) {
                    $remarks[] = "Wiersz {$i}: Element nie jest oklejony.";
                   }

                $name = strtoupper(trim($sheet->getCell([$nameColumnIndex, $i])->getCalculatedValue()));
                $materialname = $sheet->getCell([$materialColumnIndex, $i])->getCalculatedValue();
                $material = Material::where('name', $materialname)->first();
                $grainContinuation = trim($sheet->getCell([$grainContinuationColumnIndex, $i])->getCalculatedValue());

                if ($name == 'FRONT' && $material && $material->has_grain) {
                    if ($grainContinuation == '') {
                        $remarks[] = "Wiersz {$i}: Brak kontynuacji słoja.";
                    }
                }

                $width = $sheet->getCell([$widthColumIndex, $i])->getCalculatedValue();
                $length = $sheet->getCell([$lengthColumnIndex, $i])->getCalculatedValue();

                $widthHasDecimal = preg_match('/[\\.,]/', (string) $width) === 1;
                $lengthHasDecimal = preg_match('/[\\.,]/', (string) $length) === 1;

                if ($widthHasDecimal || $lengthHasDecimal) {
                    $remarks[] = "Wiersz {$i}: Wymiary muszą być liczbami całkowitymi.";
                }
            }
        }

        return $remarks;

    }
    /**
     * @return array<int, string>
     */
    private function collectUnknownMaterials($spreadsheet): array
    {
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $lastColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $materialColumnIndex = ProgramConfig::getConfig('material_column_index',10);
//$materialColumnIndex = $this->getMaterialColumnIndex($sheet);

        $names = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $value = trim((string) $sheet->getCell([$materialColumnIndex, $row])->getCalculatedValue());

            if ($value !== '') {
                $names[] = $value;
            }
        }        
        $names = array_values(array_unique($names));
        $existing = Material::whereIn('name', $names)->get()->map(fn ($item) => Str::lower($item->name))->all();
        $existingLookup = array_flip($existing);

        return array_values(array_filter($names, function (string $name) use ($existingLookup) {
            return !isset($existingLookup[Str::lower($name)]);
        }));
    }

    /**
     * @return array{zip_path: string, files: array<int, string>}
     */
    private function processSpreadsheet(UploadSession $session, Project $project, array $remarks = [], string $author = ''): array
    {
        $path = Storage::path($session->file_path);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $lastColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        //$materialColumnIndex = $this->getMaterialColumnIndex($sheet);
        $materialColumnIndex = ProgramConfig::getConfig('material_column_index',10);

        $objectNameColumnIndex = ProgramConfig::getConfig('object_name_column_index',1);

        $materials = Material::with('type')->get();
        $materialLookup = [];
        foreach ($materials as $material) {
            $materialLookup[Str::lower($material->name)] = $material;
        }

        MaterialProcessingHooks::beforeAnalysis($spreadsheet, $project);

        $rowsByType = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $lastColumnIndex; $col++) {
                $rowData[] = $sheet->getCell([$col, $row])->getCalculatedValue();
            }

            $materialName = trim((string) $rowData[$materialColumnIndex - 1]);
            if ($materialName === '') {
                continue;
            }

            $materialKey = Str::lower($materialName);
            $material = $materialLookup[$materialKey] ?? null;
            if (!$material || !$material->type) {
                continue;
            }

            MaterialProcessingHooks::onRow($rowData, $materialName);

            $rowsByType[$material->type->id]['type'] = $material->type;
            $rowsByType[$material->type->id]['rows'][] = [
                'data' => $rowData,
                'source_row' => $row,
            ];
        }

        $exportDir = "exports/{$session->token}";
        Storage::makeDirectory($exportDir);
        $files = [];
        $summaryFilePath = $exportDir . '/podsumowanie.txt';

        foreach ($rowsByType as $typeId => $payload) {
            /** @var MaterialType $type */
            $type = $payload['type'];
            $rows = $payload['rows'] ?? [];
            // sortujemy wiersze po nazwie obiektu
            usort($rows, function ($a, $b) use ($objectNameColumnIndex) {
                $aVal = $a['data'][$objectNameColumnIndex - 1] ?? '';
                $bVal = $b['data'][$objectNameColumnIndex - 1] ?? '';
                return strcmp((string) $aVal, (string) $bVal);
            });

            $resultSheet = new Spreadsheet();
            $active = $resultSheet->getActiveSheet();
            $clientName = $project->client?->full_name ?? '';
            $active->setCellValue('A1', 'Klient: ' . $clientName);
            $active->setCellValue('B1', 'Projekt: ' . $project->name);

            $styleArray = [
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                    'rotation' => 90,
                    'startColor' => [
                        'argb' => 'FFA0A0A0',
                    ],
                    'endColor' => [
                        'argb' => 'FFFFFFFF',
                    ],
                ],
            ];

            $active->getStyle('A1:B1')->applyFromArray($styleArray);

            // Przekopiuj wiersz nagłówka
            $active->getColumnDimension('A')->setWidth(30);
            $active->getColumnDimension('B')->setWidth(20);
            for ($col = 1; $col <= $lastColumnIndex; $col++) {
                $sourceCell = $sheet->getCell([$col, 1]);
                $active->setCellValue([$col, 2], $sourceCell->getValue());
                $active->duplicateStyle(
                    $sheet->getStyle([$col, 1]),
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '2'
                );
            }

            $rowIndex = 3;
            $previousGroup = null;
            $insertedOtherSection = false;
            $groupStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFCCCCCC'],
                ],
            ];

            $rowMap = [1 => 2];

            foreach ($rows as $rowPayload) {
                $rowData = $rowPayload['data'];
                $sourceRowIndex = $rowPayload['source_row'];
                $currentGroup = trim((string) ($rowData[0] ?? ''));
                $startsWithNumber = $currentGroup !== '' && preg_match('/^\d/', $currentGroup);
                if ($startsWithNumber && $currentGroup !== $previousGroup) {
                    // pusty wiersz jako odstęp
                    if ($rowIndex > 3) {
                        $rowIndex++;
                    }
                    // wiersz nagłówkowy sekcji
                    $active->setCellValue([$objectNameColumnIndex, $rowIndex], $currentGroup);
                    $active->getStyle([$objectNameColumnIndex, $rowIndex])->applyFromArray($groupStyle);
                    $rowIndex++;
                }
                if (!$startsWithNumber && !$insertedOtherSection) {
                    $rowIndex++;
                    $active->setCellValue([$objectNameColumnIndex, $rowIndex], 'Inne elementy');
                    $active->getStyle([$objectNameColumnIndex, $rowIndex])->applyFromArray($groupStyle);
                    $rowIndex++;
                    $insertedOtherSection = true;
                }

                foreach ($rowData as $colIndex => $value) {
                    $active->setCellValue([$colIndex + 1, $rowIndex], $value);
                }

                $rowMap[$sourceRowIndex] = $rowIndex;
                $previousGroup = $startsWithNumber ? $currentGroup : $previousGroup;
                $rowIndex++;
            }

            $this->copyRowDrawings($sheet, $active, $rowMap);

            $fileName = Str::slug($clientName.'-'.$project->name.'-'.$type->name) . '.xlsx';
            $fullPath = Storage::path($exportDir . '/' . $fileName);
            $writer = IOFactory::createWriter($resultSheet, 'Xlsx');
            $writer->save($fullPath);

            $files[] = $fullPath;
        }

        $summaryLines = [
            'Nazwa klienta: ' . ($project->client?->full_name ?? ''),
            'Nazwa projektu: ' . $project->name,
            'Nazwa pliku źródłowego: ' . ($session->original_name ?? basename($session->file_path)),
            'Data generacji pliku: ' . now()->toDateTimeString(),
            'Autor: ' . $author,
            '',
            'Uwagi:',
        ];

        if (!empty($remarks)) {
            foreach ($remarks as $remark) {
                $summaryLines[] = '- ' . $remark;
            }
        } else {
            $summaryLines[] = '- Brak uwag';
        }

        Storage::put($summaryFilePath, implode(PHP_EOL, $summaryLines));
        $files[] = Storage::path($summaryFilePath);

        $zipPath = "public/downloads/{$session->token}.zip";
        Storage::makeDirectory('public/downloads');
        $zip = new ZipArchive();
        $zipFullPath = Storage::path($zipPath);
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create zip archive.');
        }

        foreach ($files as $filePath) {
            $zip->addFile($filePath, basename($filePath));
        }

        $zip->close();

        return [
            'zip_path' => $zipPath,
            'files' => array_map('basename', $files),
            'file_urls' => array_map(function (string $filePath) use ($session) {
                $filename = basename($filePath);
                return [
                    'name' => $filename,
                    'url' => url('/api/downloads/' . $session->token . '/' . $filename),
                ];
            }, $files),
        ];
    }

    /**
     * @param array<int, int> $rowMap mapuje numer wiersza z pliku źródłowego na numer w pliku wynikowym
     */
    private function copyRowDrawings(Worksheet $sourceSheet, Worksheet $targetSheet, array $rowMap): void
    {
        foreach ($sourceSheet->getDrawingCollection() as $drawing) {
            $coordinate = $drawing->getCoordinates();
            [$column, $row] = Coordinate::coordinateFromString($coordinate);

            if (!isset($rowMap[$row])) {
                continue;
            }

            $targetCoordinate = $column . $rowMap[$row];

            if ($drawing instanceof MemoryDrawing) {
                $clone = new MemoryDrawing();
                $clone->setName($drawing->getName());
                $clone->setDescription($drawing->getDescription());
                $clone->setImageResource($drawing->getImageResource());
                $clone->setRenderingFunction($drawing->getRenderingFunction());
                $clone->setMimeType($drawing->getMimeType());
                $clone->setHeight($drawing->getHeight());
                $clone->setWidth($drawing->getWidth());
                $clone->setOffsetX($drawing->getOffsetX());
                $clone->setOffsetY($drawing->getOffsetY());
                $clone->setCoordinates($targetCoordinate);
                $clone->setWorksheet($targetSheet);
                continue;
            }

            if ($drawing instanceof Drawing) {
                $clone = new Drawing();
                $clone->setName($drawing->getName());
                $clone->setDescription($drawing->getDescription());
                $clone->setPath($drawing->getPath());
                $clone->setHeight($drawing->getHeight());
                $clone->setWidth($drawing->getWidth());
                $clone->setOffsetX($drawing->getOffsetX());
                $clone->setOffsetY($drawing->getOffsetY());
                $clone->setResizeProportional($drawing->getResizeProportional());
                $clone->setCoordinates($targetCoordinate);
                $clone->setWorksheet($targetSheet);
            }
        }
    }
}
