<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$outputPath = __DIR__ . '/database-table-summary-formatted.docx';

$tablePurposes = [
    'users' => 'Luu tai khoan dang nhap, vai tro va trang thai hoat dong cua nguoi dung he thong.',
    'password_reset_tokens' => 'Luu token phuc vu quy trinh dat lai mat khau.',
    'sessions' => 'Luu phien dang nhap khi ung dung dung session driver database.',
    'cache' => 'Luu du lieu cache cua Laravel.',
    'cache_locks' => 'Luu thong tin lock cho cac tac vu can dong bo.',
    'jobs' => 'Hang doi tac vu nen.',
    'job_batches' => 'Thong tin gom nhom cac job chay theo batch.',
    'failed_jobs' => 'Luu lich su job that bai de theo doi va retry.',
    'employees' => 'Ho so nhan su noi bo cua cong ty.',
    'contracts' => 'Hop dong lao dong hoac hop dong lam viec voi nhan su.',
    'customers' => 'Thong tin khach hang thue do hoac su dung dich vu.',
    'warehouses' => 'Danh muc kho quan ly vat tu, trang phuc va dao cu.',
    'item_categories' => 'Danh muc nhom vat pham de phan loai trang phuc va dao cu.',
    'equipment_props' => 'Danh muc mat hang goc duoc cho thue, muon hoac quan ly trong kho.',
    'gallery_images' => 'Kho anh dung cho danh muc vat pham.',
    'equipment_prop_gallery_image' => 'Bang lien ket nhieu nhieu giua vat pham va hinh anh.',
    'inventory_conditions' => 'Danh muc tinh trang vat ly cua tung mon hang trong kho.',
    'inventory' => 'Ton kho theo tung don vi thuc te, moi ban ghi dai dien cho mot SKU cu the.',
    'inventory_transactions' => 'Lich su bien dong kho de doi soat nhap, xuat, tra, mat, hong va dieu chinh.',
    'internal_borrow_slips' => 'Phieu muon noi bo cho nhan vien hoac bo phan muon vat pham.',
    'internal_borrow_details' => 'Chi tiet tong hop theo mat hang trong tung phieu muon noi bo.',
    'internal_borrow_detail_items' => 'Chi tiet theo tung mon inventory duoc gan vao phieu muon noi bo.',
    'internal_incidents' => 'Su co phat sinh trong qua trinh muon noi bo nhu hong, mat hoac van de khac.',
    'rental_slips' => 'Phieu thue cua khach hang, gom thong tin thanh toan va trang thai don.',
    'rental_details' => 'Chi tiet tong hop theo mat hang trong tung phieu thue.',
    'rental_detail_items' => 'Chi tiet theo tung mon inventory duoc gan vao phieu thue.',
    'rental_payments' => 'Cac lan thanh toan lien quan den phieu thue.',
    'rental_incidents' => 'Su co phat sinh trong qua trinh khach thue su dung va tra hang.',
    'maintenance_tickets' => 'Phieu bao tri, sua chua, giat, thay the cho vat pham hong hoac xuong cap.',
    'migrations' => 'Lich su migration da chay tren database.',
];

$tables = array_map(
    static fn ($row) => $row->table_name,
    DB::select("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ")
);

$sections = [];

foreach ($tables as $table) {
    $columns = DB::select("
        SELECT column_name, column_type
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
        ORDER BY ordinal_position
    ", [$table]);

    $sampleRow = (array) DB::table($table)->first();
    $rows = [];

    foreach ($columns as $column) {
        $name = $column->column_name;
        $value = $sampleRow[$name] ?? null;
        $rows[] = [
            'name' => $name,
            'type' => $column->column_type,
            'example' => format_example($value, $name, $column->column_type),
        ];
    }

    $sections[] = [
        'table' => $table,
        'purpose' => $tablePurposes[$table] ?? 'Bang nghiep vu ho tro van hanh va luu tru du lieu lien quan trong he thong.',
        'rows' => $rows,
    ];
}

$documentXml = build_document_xml($sections);

$zip = new ZipArchive();
if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Khong the tao file DOCX tai: {$outputPath}" . PHP_EOL);
    exit(1);
}

$zip->addFromString('[Content_Types].xml', content_types_xml());
$zip->addEmptyDir('_rels');
$zip->addFromString('_rels/.rels', rels_xml());
$zip->addEmptyDir('word');
$zip->addFromString('word/document.xml', $documentXml);
$zip->addFromString('word/styles.xml', styles_xml());
$zip->addEmptyDir('word/_rels');
$zip->addFromString('word/_rels/document.xml.rels', document_rels_xml());
$zip->close();

echo $outputPath . PHP_EOL;

function format_example(mixed $value, string $columnName, string $columnType): string
{
    if ($value === null || $value === '') {
        $type = strtolower($columnType);

        return match (true) {
            str_contains($type, 'bigint'), str_contains($type, 'int') => $columnName === 'id' ? '1' : '0',
            str_contains($type, 'decimal') => '0.00',
            str_contains($type, 'date') && !str_contains($type, 'time') => '2026-05-19',
            str_contains($type, 'datetime'), str_contains($type, 'timestamp') => '2026-05-19 10:00:00',
            str_contains($type, 'tinyint(1)') => '1',
            str_contains($type, 'enum') => first_enum_value($columnType),
            default => sample_text_for($columnName),
        };
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
        $string = (string) $value;

        return mb_strlen($string) > 40 ? mb_substr($string, 0, 37) . '...' : $string;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
}

function first_enum_value(string $columnType): string
{
    if (preg_match("/^enum\\((.+)\\)$/i", $columnType, $matches) === 1) {
        $parts = array_map(
            static fn ($part) => trim($part, " '"),
            explode(',', $matches[1])
        );

        return $parts[0] ?? '-';
    }

    return '-';
}

function sample_text_for(string $columnName): string
{
    return match ($columnName) {
        'code' => 'P001',
        'sku' => 'SKU-0001',
        'name', 'full_name', 'customer_name', 'employee_name' => 'Vi du',
        'username' => 'admin',
        'email' => 'demo@example.com',
        'phone', 'customer_phone' => '0900000000',
        'remarks', 'note', 'description', 'incident_description', 'resolution' => 'Ghi chu',
        'status' => 'ACTIVE',
        'type', 'item_type', 'payment_type', 'transaction_type', 'maintenance_type', 'incident_type' => 'TYPE_A',
        default => '-',
    };
}

function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function paragraph(string $text, string $style = 'Normal', bool $bold = false): string
{
    $styleXml = $style !== 'Normal' ? "<w:pPr><w:pStyle w:val=\"{$style}\"/></w:pPr>" : '<w:pPr><w:spacing w:after="120"/></w:pPr>';
    $boldXml = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
    $text = xml_escape($text);

    return "<w:p>{$styleXml}<w:r>{$boldXml}<w:t xml:space=\"preserve\">{$text}</w:t></w:r></w:p>";
}

function cell(string $text, int $width, bool $bold = false, bool $header = false, string $align = 'left'): string
{
    $boldXml = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
    $text = xml_escape($text);
    $shadingXml = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="D9EAF7"/>' : '';
    $jc = match ($align) {
        'center' => '<w:jc w:val="center"/>',
        'right' => '<w:jc w:val="right"/>',
        default => '<w:jc w:val="left"/>',
    };

    return <<<XML
<w:tc>
  <w:tcPr>
    <w:tcW w:w="{$width}" w:type="dxa"/>
    {$shadingXml}
    <w:tcMar>
      <w:top w:w="80" w:type="dxa"/>
      <w:left w:w="100" w:type="dxa"/>
      <w:bottom w:w="80" w:type="dxa"/>
      <w:right w:w="100" w:type="dxa"/>
    </w:tcMar>
  </w:tcPr>
  <w:p>
    <w:pPr>
      {$jc}
      <w:spacing w:after="0"/>
    </w:pPr>
    <w:r>
      {$boldXml}
      <w:t xml:space="preserve">{$text}</w:t>
    </w:r>
  </w:p>
</w:tc>
XML;
}

function table_xml(array $rows): string
{
    $header = '<w:tr>' .
        cell('Tên', 2500, true, true, 'center') .
        cell('Type', 3500, true, true, 'center') .
        cell('Ví dụ', 3000, true, true, 'center') .
        '</w:tr>';

    $body = '';
    foreach ($rows as $row) {
        $body .= '<w:tr>'
            . cell((string) $row['name'], 2500)
            . cell((string) $row['type'], 3500)
            . cell((string) $row['example'], 3000)
            . '</w:tr>';
    }

    return <<<XML
<w:tbl>
  <w:tblPr>
    <w:tblStyle w:val="TableGrid"/>
    <w:tblW w:w="9000" w:type="dxa"/>
    <w:tblInd w:w="0" w:type="dxa"/>
    <w:tblCellMar>
      <w:top w:w="0" w:type="dxa"/>
      <w:left w:w="0" w:type="dxa"/>
      <w:bottom w:w="0" w:type="dxa"/>
      <w:right w:w="0" w:type="dxa"/>
    </w:tblCellMar>
    <w:tblBorders>
      <w:top w:val="single" w:sz="12" w:space="0" w:color="8FAADC"/>
      <w:left w:val="single" w:sz="12" w:space="0" w:color="8FAADC"/>
      <w:bottom w:val="single" w:sz="12" w:space="0" w:color="8FAADC"/>
      <w:right w:val="single" w:sz="12" w:space="0" w:color="8FAADC"/>
      <w:insideH w:val="single" w:sz="8" w:space="0" w:color="B4C7E7"/>
      <w:insideV w:val="single" w:sz="8" w:space="0" w:color="B4C7E7"/>
    </w:tblBorders>
    <w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>
  </w:tblPr>
  <w:tblGrid>
    <w:gridCol w:w="2500"/>
    <w:gridCol w:w="3500"/>
    <w:gridCol w:w="3000"/>
  </w:tblGrid>
  {$header}
  {$body}
</w:tbl>
XML;
}

function build_document_xml(array $sections): string
{
    $body = '';
    $body .= paragraph('Tom tat cac table trong database', 'Title', true);
    $body .= paragraph('Moi table gom bang cau truc 3 cot: Ten | Type | Vi du, kem mo ta tac dung trong he thong.');
    $body .= paragraph('Tai lieu duoc sinh tu schema MySQL hien tai cua project.', 'Subtitle');

    foreach ($sections as $section) {
        $body .= paragraph('Table: ' . $section['table'], 'Heading1', true);
        $body .= paragraph('Tac dung: ' . $section['purpose']);
        $body .= table_xml($section['rows']);
        $body .= paragraph('', 'Spacer');
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"
 xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
 xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
 xmlns:v="urn:schemas-microsoft-com:vml"
 xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing"
 xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
 xmlns:w10="urn:schemas-microsoft-com:office:word"
 xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
 xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"
 xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml"
 xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup"
 xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk"
 xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"
 xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
 mc:Ignorable="w14 w15 wp14">
  <w:body>
    {$body}
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/>
    </w:sectPr>
  </w:body>
</w:document>
XML;
}

function content_types_xml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;
}

function rels_xml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
}

function document_rels_xml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
}

function styles_xml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:spacing w:after="120" w:line="276" w:lineRule="auto"/>
    </w:pPr>
    <w:rPr>
      <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>
      <w:sz w:val="22"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Title">
    <w:name w:val="Title"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:jc w:val="center"/>
      <w:spacing w:after="180"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:color w:val="1F4E79"/>
      <w:sz w:val="34"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:spacing w:before="200" w:after="80"/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:color w:val="1F1F1F"/>
      <w:sz w:val="26"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle">
    <w:name w:val="Subtitle"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr>
      <w:jc w:val="center"/>
      <w:spacing w:after="220"/>
    </w:pPr>
    <w:rPr>
      <w:color w:val="666666"/>
      <w:sz w:val="20"/>
    </w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Spacer">
    <w:name w:val="Spacer"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr>
      <w:spacing w:after="220"/>
    </w:pPr>
  </w:style>
</w:styles>
XML;
}
