<?php

namespace App\Services\Stock;

use ZipArchive;

final class StockItemExcel
{
    /** Convert the first worksheet into the existing staged CSV validation path. */
    public function toCsv(string $xlsxPath): string
    {
        if (!class_exists(ZipArchive::class)) throw new \RuntimeException('The PHP zip extension is required for Excel import.');
        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) throw new \InvalidArgumentException('Invalid Excel file.');
        try {
            foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $entryName) {
                $entry = $zip->statName($entryName);
                if ($entry && $entry['size'] > 12 * 1024 * 1024) throw new \InvalidArgumentException('Excel worksheet is too large.');
            }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false || strlen($sheetXml) > 12 * 1024 * 1024) throw new \InvalidArgumentException('Excel worksheet is missing or too large.');
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            $shared = [];
            if ($sharedXml !== false) {
                if (strlen($sharedXml) > 12 * 1024 * 1024) throw new \InvalidArgumentException('Excel shared strings are too large.');
                $strings = $this->xml($sharedXml);
                $strings->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($strings->xpath('//s:si') as $string) {
                    $string->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $shared[] = implode('', array_map(fn ($part) => (string) $part, $string->xpath('.//s:t')));
                }
            }
            $sheet = $this->xml($sheetXml);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = $sheet->xpath('//s:sheetData/s:row');
            if (count($rows) > 10001) throw new \InvalidArgumentException('A single import can contain at most 10,000 data rows.');
            $path = tempnam(sys_get_temp_dir(), 'stock-import-');
            if ($path === false) throw new \RuntimeException('Unable to prepare the Excel import.');
            $out = fopen($path, 'wb');
            if (!$out) { @unlink($path); throw new \RuntimeException('Unable to prepare the Excel import.'); }
            try {
                foreach ($rows as $row) {
                    $row->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $values = array_fill(0, 4, '');
                    foreach ($row->xpath('./s:c') as $cell) {
                        if (!preg_match('/^([A-D])\d+$/D', (string) $cell['r'], $match)) continue;
                        $index = ord($match[1]) - 65;
                        $cell->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                        $parts = $cell->xpath('.//s:t');
                        $value = $parts ? implode('', array_map(fn ($part) => (string) $part, $parts)) : (string) ($cell->xpath('./s:v')[0] ?? '');
                        $values[$index] = (string) $cell['t'] === 's' ? ($shared[(int) $value] ?? '') : $value;
                    }
                    fputcsv($out, $values);
                }
            } finally {
                fclose($out);
            }
            return $path;
        } finally {
            $zip->close();
        }
    }

    private function xml(string $content): \SimpleXMLElement
    {
        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new \InvalidArgumentException('Invalid Excel XML.');
        }
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) throw new \InvalidArgumentException('Invalid Excel XML.');
        return $xml;
    }

    /** @param iterable<object> $items */
    public function export(iterable $items): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required for Excel export.');
        }
        $path = tempnam(sys_get_temp_dir(), 'stock-items-');
        if ($path === false) throw new \RuntimeException('Unable to create the Excel export.');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new \RuntimeException('Unable to create the Excel export.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Stock Items" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = new \XMLWriter();
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('worksheet');
            $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetData');
            $this->row($xml, 1, ['item_id', 'name', 'unit', 'default_rate_minor'], false);
            $number = 1;
            foreach ($items as $item) {
                $number++;
                $this->row($xml, $number, [(int) $item->id, (string) $item->name, (string) $item->unit, (int) $item->default_rate_minor], true);
            }
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml->outputMemory());
        } finally {
            $zip->close();
        }
        return $path;
    }

    private function row(\XMLWriter $xml, int $number, array $values, bool $numeric): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        foreach ($values as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', 'ABCD'[$index].$number);
            if ($numeric && ($index === 0 || $index === 3)) {
                $xml->writeElement('v', (string) $value);
            } else {
                // Inline strings are literal data; spreadsheet formulas never execute.
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->writeElement('t', (string) $value);
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }
}
