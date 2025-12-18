<?php

namespace App\Services;

use App\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class MaterialProcessingHooks
{
    /**
     * Hook executed before rows are processed.
     */
    public static function beforeAnalysis(Spreadsheet $spreadsheet, Project $project): void
    {
        // Custom hook placeholder
    }

    /**
     * Hook executed for each processed row.
     *
     * @param array<int, mixed> $rowData
     */
    public static function onRow(array $rowData, string $materialName): void
    {
        // Custom hook placeholder
    }
}
