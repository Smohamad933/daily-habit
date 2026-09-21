<?php
/** تولیدکننده سبک فایل اکسل (XLSX) بدون وابستگی خارجی */

class XlsxWriter {
    private array $rows = [];

    public function addRow(array $row): void { $this->rows[] = $row; }

    private static function colRef(int $idx): string {
        $ref = '';
        $idx++;
        while ($idx > 0) { $m = ($idx - 1) % 26; $ref = chr(65 + $m) . $ref; $idx = intdiv($idx - 1, 26); }
        return $ref;
    }

    private static function esc($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function output(string $filename): void {
        $sheet = '';
        foreach ($this->rows as $ri => $row) {
            $cells = '';
            foreach (array_values($row) as $ci => $v) {
                $ref = self::colRef($ci) . ($ri + 1);
                if (is_numeric($v) && !preg_match('/^0\d+/', (string)$v)) {
                    $cells .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                } else {
                    $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc($v) . '</t></is></c>';
                }
            }
            $sheet .= '<row r="' . ($ri + 1) . '">' . $cells . '</row>';
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                '<Default Extension="xml" ContentType="application/xml"/>' .
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                '<sheets><sheet name="کاربران" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheet . '</sheetData></worksheet>',
        ];

        if (class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::OVERWRITE);
            foreach ($files as $name => $content) $zip->addFromString($name, $content);
            $zip->close();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            @unlink($tmp);
        } else {
            //fallback: جدول اکسل‌خوان (فرمت قدیمی)
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.xls', $filename) . '"');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            echo '<html dir="rtl"><head><meta charset="utf-8"></head><body><table border="1">';
            foreach ($this->rows as $row) {
                echo '<tr>';
                foreach ($row as $v) echo '<td>' . self::esc($v) . '</td>';
                echo '</tr>';
            }
            echo '</table></body></html>';
        }
        exit;
    }
}
