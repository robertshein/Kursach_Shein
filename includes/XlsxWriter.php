<?php
/**
 * Минималистичный генератор XLSX (Office Open XML) без сторонних зависимостей.
 * Использует ZipArchive + inline-строки, поддерживает многолистовые книги,
 * стили заголовков, числовые форматы, авто-ширину столбцов.
 *
 * Типы ячеек: 's' = строка, 'n' = число, 'currency' = деньги (# ##0.00),
 *             'date' = дата (DD.MM.YYYY), 'bold' = жирная строка-заголовок.
 */
class XlsxWriter
{
    private array $sheets   = [];   // ['name' => string, 'rows' => array]
    private int   $current  = -1;   // индекс текущего листа

    /* ------------------------------------------------------------------ */
    /*  API                                                                 */
    /* ------------------------------------------------------------------ */

    /** Добавить новый лист и сделать его активным. */
    public function addSheet(string $name): self
    {
        $this->sheets[] = ['name' => $name, 'rows' => [], 'colWidths' => []];
        $this->current  = count($this->sheets) - 1;
        return $this;
    }

    /**
     * Записать строку на текущий лист.
     *
     * Каждая ячейка — либо скалярное значение (строка/число),
     * либо массив ['v' => значение, 't' => тип].
     *
     * Типы: 's' (строка, по умолчанию), 'n' (число), 'currency', 'date', 'h' (заголовок-жирный).
     */
    public function writeRow(array $cells): self
    {
        if ($this->current < 0) {
            $this->addSheet('Sheet1');
        }
        $normalized = [];
        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                $cell = ['v' => $cell, 't' => 's'];
            }
            $cell['t'] = $cell['t'] ?? 's';
            $normalized[] = $cell;
        }
        $this->sheets[$this->current]['rows'][] = $normalized;
        return $this;
    }

    /** Записать строку заголовков (жирный текст, серый фон). */
    public function writeHeader(array $titles): self
    {
        $cells = array_map(fn($t) => ['v' => (string)$t, 't' => 'h'], $titles);
        return $this->writeRow($cells);
    }

    /**
     * Сгенерировать XLSX и записать в $filePath.
     * Если $filePath === null — вернуть содержимое как строку.
     */
    public function save(string $filePath): bool
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sheetCount = count($this->sheets);

        // ---- [Content_Types].xml ----
        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes($sheetCount));

        // ---- _rels/.rels ----
        $zip->addFromString('_rels/.rels', $this->buildRels());

        // ---- xl/workbook.xml ----
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook());

        // ---- xl/_rels/workbook.xml.rels ----
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels($sheetCount));

        // ---- xl/styles.xml ----
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        // ---- xl/worksheets/sheetN.xml ----
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($i + 1) . '.xml',
                $this->buildSheet($sheet)
            );
        }

        $zip->close();

        $ok = rename($tmpFile, $filePath);
        if (!$ok) {
            // fallback: copy + unlink
            $ok = copy($tmpFile, $filePath);
            @unlink($tmpFile);
        }
        return $ok;
    }

    /**
     * Отправить файл в браузер как загрузку.
     */
    public function download(string $fileName): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $this->save($tmpFile);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  Построители XML                                                     */
    /* ------------------------------------------------------------------ */

    private function buildContentTypes(int $sheetCount): string
    {
        $sheets = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $sheets
            . '</Types>';
    }

    private function buildRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
            . 'Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(): string
    {
        $sheetEls = '';
        foreach ($this->sheets as $i => $sheet) {
            $num  = $i + 1;
            $name = htmlspecialchars($sheet['name'], ENT_XML1);
            $sheetEls .= "<sheet name=\"{$name}\" sheetId=\"{$num}\" r:id=\"rId{$num}\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetEls . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(int $sheetCount): string
    {
        $rels = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $stylesId = $sheetCount + 1;
        $rels .= '<Relationship Id="rId' . $stylesId . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * Стили:
     *  idx 0 — обычный текст
     *  idx 1 — заголовок (жирный, серый фон)
     *  idx 2 — число (целое)
     *  idx 3 — валюта (# ##0.00 ₽)
     *  idx 4 — дата
     */
    private function buildStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'

            // numFmts
            . '<numFmts count="2">'
            . '<numFmt numFmtId="164" formatCode="# ##0.00\ &quot;₽&quot;"/>'
            . '<numFmt numFmtId="165" formatCode="DD.MM.YYYY"/>'
            . '</numFmts>'

            // fonts: 0=normal, 1=bold
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'

            // fills: 0=none, 1=gray (required by spec to have 2 before pattern)
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/></patternFill></fill>'
            . '</fills>'

            // borders: 0=none
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '</borders>'

            // cellStyleXfs
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'

            // cellXfs (индексы стилей)
            . '<cellXfs>'
            . '<xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>'              // 0: обычный
            . '<xf numFmtId="0"   fontId="1" fillId="2" borderId="0" xfId="0"/>'              // 1: заголовок
            . '<xf numFmtId="1"   fontId="0" fillId="0" borderId="0" xfId="0"/>'              // 2: целое
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0"/>'              // 3: валюта
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0"/>'              // 4: дата
            . '</cellXfs>'

            . '</styleSheet>';
    }

    private function buildSheet(array $sheet): string
    {
        $rows    = $sheet['rows'];
        $colWidths = $this->calcColumnWidths($rows);

        // colsXml
        $colsXml = '<cols>';
        foreach ($colWidths as $col => $width) {
            $colNum = $col + 1;
            $colsXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $width . '" bestFit="1" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $rowsXml = '';
        foreach ($rows as $rowIdx => $cells) {
            $rowNum  = $rowIdx + 1;
            $cellsXml = '';
            foreach ($cells as $colIdx => $cell) {
                $cellsXml .= $this->buildCell($colIdx, $rowNum, $cell);
            }
            $rowsXml .= '<row r="' . $rowNum . '">' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
    }

    private function buildCell(int $colIdx, int $rowNum, array $cell): string
    {
        $addr = $this->colName($colIdx) . $rowNum;
        $val  = $cell['v'] ?? '';
        $type = $cell['t'] ?? 's';

        switch ($type) {
            case 'n': // целое число
                $num = is_numeric($val) ? (float)$val : 0;
                return '<c r="' . $addr . '" s="2"><v>' . $num . '</v></c>';

            case 'currency': // деньги
                $num = is_numeric($val) ? (float)$val : 0;
                return '<c r="' . $addr . '" s="3"><v>' . $num . '</v></c>';

            case 'date': // дата в формате YYYY-MM-DD → serial
                $serial = $this->dateToSerial((string)$val);
                if ($serial !== null) {
                    return '<c r="' . $addr . '" s="4"><v>' . $serial . '</v></c>';
                }
                // fallback: строка
                $esc = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES);
                return '<c r="' . $addr . '" t="inlineStr"><is><t>' . $esc . '</t></is></c>';

            case 'h': // заголовок
                $esc = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES);
                return '<c r="' . $addr . '" t="inlineStr" s="1"><is><t>' . $esc . '</t></is></c>';

            default: // строка
                $esc = htmlspecialchars((string)$val, ENT_XML1 | ENT_QUOTES);
                return '<c r="' . $addr . '" t="inlineStr"><is><t>' . $esc . '</t></is></c>';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Вспомогательные методы                                              */
    /* ------------------------------------------------------------------ */

    /** Индекс столбца (0-based) → буквенное имя (A, B, ..., Z, AA, ...). */
    private function colName(int $idx): string
    {
        $name = '';
        $idx++;
        while ($idx > 0) {
            $idx--;
            $name = chr(65 + $idx % 26) . $name;
            $idx  = intdiv($idx, 26);
        }
        return $name;
    }

    /**
     * Дата "YYYY-MM-DD" → Excel serial number (days since 1900-01-00, с учётом
     * ошибки Excel: 1900 считается високосным).
     */
    private function dateToSerial(string $date): ?float
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return null;
        }
        $parts = explode('-', substr($date, 0, 10));
        if (count($parts) < 3) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', $parts);
        // Excel serial: days from 1900-01-01 = 1 (plus the leap-year bug offset)
        $jd    = gregoriantojd($m, $d, $y);
        $base  = gregoriantojd(1, 1, 1900);
        $serial = $jd - $base + 1;
        if ($serial >= 60) {
            $serial++; // Excel's 1900 leap-year bug
        }
        return $serial > 0 ? (float)$serial : null;
    }

    /**
     * Авто-ширина столбцов: считаем максимальную длину значения в каждом столбце.
     * Минимум 8, максимум 60 символов.
     */
    private function calcColumnWidths(array $rows): array
    {
        $widths = [];
        foreach ($rows as $cells) {
            foreach ($cells as $colIdx => $cell) {
                $len = mb_strlen((string)($cell['v'] ?? ''));
                $w   = max(8, min(60, $len + 2));
                if (!isset($widths[$colIdx]) || $widths[$colIdx] < $w) {
                    $widths[$colIdx] = $w;
                }
            }
        }
        return $widths;
    }
}
