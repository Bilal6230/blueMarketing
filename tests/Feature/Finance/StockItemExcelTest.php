<?php

namespace Tests\Feature\Finance;

use App\Services\Stock\StockItemExcel;
use Tests\TestCase;
use ZipArchive;

class StockItemExcelTest extends TestCase
{
    public function test_export_contains_stable_ids_and_never_writes_formulas(): void
    {
        $path = (new StockItemExcel())->export([
            (object) ['id' => 42, 'name' => '=HYPERLINK("https://invalid.example")', 'unit' => '+Bag', 'default_rate_minor' => 1250],
        ]);
        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertNotFalse($sheet);
            $xml = new \SimpleXMLElement($sheet);
            $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = $xml->xpath('//s:row[@r="2"]/s:c');
            $this->assertCount(4, $cells);
            $this->assertSame('42', (string) $cells[0]->v);
            $this->assertSame('inlineStr', (string) $cells[1]['t']);
            $this->assertSame('=HYPERLINK("https://invalid.example")', (string) $cells[1]->is->t);
            $this->assertSame('inlineStr', (string) $cells[2]['t']);
            $this->assertSame('1250', (string) $cells[3]->v);
        } finally {
            @unlink($path);
        }
    }

    public function test_exported_workbook_can_be_staged_as_csv_without_changing_values(): void
    {
        $path = (new StockItemExcel())->export([
            (object) ['id' => 42, 'name' => 'Concrete', 'unit' => 'Bag', 'default_rate_minor' => 1250],
        ]);
        try {
            $csvPath = (new StockItemExcel())->toCsv($path);
            try {
                $rows = array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES));
                $this->assertSame(['item_id', 'name', 'unit', 'default_rate_minor'], $rows[0]);
                $this->assertSame(['42', 'Concrete', 'Bag', '1250'], $rows[1]);
            } finally {
                @unlink($csvPath);
            }
        } finally {
            @unlink($path);
        }
    }
}
