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
use ZipArchive;

class FileProcessingController extends Controller
{
    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'file' => 'required|file',
        ]);

        $project = Project::findOrFail($validated['project_id']);
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

        $unknownMaterials = $this->collectUnknownMaterials(Storage::path($path));

        return response()->json([
            'upload_token' => $token,
            'unknown_materials' => $unknownMaterials,
            'material_types' => MaterialType::orderBy('name')->get(),
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
        ]);

        $session = UploadSession::where('token', $validated['upload_token'])->firstOrFail();
        $project = Project::findOrFail($validated['project_id']);

        if (!Storage::exists($session->file_path)) {
            return response()->json(['message' => 'Uploaded file not found. Please start over.'], 404);
        }

        if ($session->project_id !== $project->id) {
            return response()->json(['message' => 'Upload token does not match project.'], 422);
        }

        // create materials based on user assignments for previously unknown names
        $assignments = collect($validated['assignments'] ?? []);
        $assignments->each(function (array $assignment) {
            Material::firstOrCreate(
                ['name' => $assignment['name']],
                ['material_type_id' => $assignment['material_type_id']]
            );
        });

        $result = $this->processSpreadsheet($session, $project);

        $session->update([
            'result_path' => $result['zip_path'],
            'status' => 'processed',
        ]);

        return response()->json([
            'download_url' => url('/api/downloads/' . $session->token),
            'files' => $result['files'],
        ]);
    }

    public function download(string $token)
    {
        $session = UploadSession::where('token', $token)->firstOrFail();

        if (!$session->result_path || !Storage::exists($session->result_path)) {
            return response()->json(['message' => 'Plik nie jest dostępny.'], 404);
        }

        return response()->download(Storage::path($session->result_path));
    }

    /**
     * @return array<int, string>
     */
    private function collectUnknownMaterials(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $lastColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $names = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $value = trim((string) $sheet->getCellByColumnAndRow($lastColumnIndex, $row)->getCalculatedValue());
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
                $rowData[] = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
            }

            $materialName = trim((string) $rowData[$lastColumnIndex - 1]);
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
            $active->setCellValue('A1', 'Client: ' . $project->client_name);
            $active->setCellValue('B1', 'Project: ' . $project->name);

            $rowIndex = 2;
            foreach ($rows as $rowData) {
                foreach ($rowData as $colIndex => $value) {
                    $active->setCellValueByColumnAndRow($colIndex + 1, $rowIndex, $value);
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
        ];
    }
}
