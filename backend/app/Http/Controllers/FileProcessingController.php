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

        $result = $this->processSpreadsheet($session, $project);

        $session->update([
            'result_path' => $result['zip_path'],
            'status' => 'processed',
        ]);

        $spreadsheet = IOFactory::load(Storage::path($session->file_path));

        return response()->json([
            'download_url' => url('/api/downloads/' . $session->token),
            'files' => $result['files'],
            'file_urls' => $result['file_urls'],
            'remarks' => $this->addRemarks($spreadsheet)
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

    private function getMaterialColumnIndex($sheet) {
        $lastColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $materialColumnIndex = $lastColumnIndex;
        for ($i=1; $i<=$lastColumnIndex; $i++) {
            $value = $sheet->getCell([$i, 1]);
            if (strToUpper($value) == 'MATERIAŁY') {
                $materialColumnIndex = $i;
                break;
            }
        }
        return $materialColumnIndex;
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


        $maxLength = ProgramConfig::getConfig('max_length',2800);
        $maxWidth = ProgramConfig::getConfig('max_width',2070);
        
        $remarks = [

        ];

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
                $material = Material::where('name', $name)->first();
                $grainContinuation = trim($sheet->getCell([$grainContinuationColumnIndex, $i])->getCalculatedValue());
                if ($name == 'FRONT' && $material) {
                    if ($grainContinuation == '') {
                        $remarks[] = "Wiersz {$i}: Brak kontynuacji słoja.";
                    }
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
        $materialColumnIndex = $this->getMaterialColumnIndex($sheet);

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
    private function processSpreadsheet(UploadSession $session, Project $project): array
    {
        $path = Storage::path($session->file_path);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $lastColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $materialColumnIndex = $this->getMaterialColumnIndex($sheet);

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
                // skip rows with unresolved materials
                continue;
            }

            MaterialProcessingHooks::onRow($rowData, $materialName);

            $rowsByType[$material->type->id]['type'] = $material->type;
            $rowsByType[$material->type->id]['rows'][] = $rowData;
        }

        $exportDir = "exports/{$session->token}";
        Storage::makeDirectory($exportDir);
        $files = [];

        foreach ($rowsByType as $typeId => $payload) {
            /** @var MaterialType $type */
            $type = $payload['type'];
            $rows = $payload['rows'] ?? [];

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

            $rowIndex = 2;
            foreach ($rows as $rowData) {
                foreach ($rowData as $colIndex => $value) {
                    $active->setCellValue([$colIndex + 1, $rowIndex], $value);
                }
                $rowIndex++;
            }

            $fileName = Str::slug($type->name) . '.xlsx';
            $fullPath = Storage::path($exportDir . '/' . $fileName);
            $writer = IOFactory::createWriter($resultSheet, 'Xlsx');
            $writer->save($fullPath);

            $files[] = $fullPath;
        }

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
}
