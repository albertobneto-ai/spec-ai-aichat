<?php
// ============================================================
//  download.php  —  Gera .docx formatado
//  Suporta modo normal e modo HF (História Funcional)
// ============================================================
require_once __DIR__ . '/auth.php';

iniciarSessao();
if (empty($_SESSION['usuario_id'])) { http_response_code(401); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$body     = json_decode(file_get_contents('php://input'), true);
$conteudo = $body['conteudo'] ?? '';
$titulo   = $body['titulo']   ?? 'Resposta AI Chat';
$tipo     = $body['tipo']     ?? 'normal';

if (empty($conteudo)) { http_response_code(400); exit; }

// ── Funções de conversão ───────────────────────────────────────────────

function safeXml(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function processarInline(string $safe): string {
    // Negrito: **texto**
    $partes = preg_split('/\*\*(.*?)\*\*/', $safe, -1, PREG_SPLIT_DELIM_CAPTURE);
    $runs = '';
    foreach ($partes as $i => $parte) {
        if (empty($parte)) continue;
        if ($i % 2 === 1) {
            $runs .= '<w:r><w:rPr><w:b/><w:bCs/><w:color w:val="111827"/></w:rPr>'
                   . '<w:t xml:space="preserve">' . $parte . '</w:t></w:r>';
        } else {
            $codeParts = preg_split('/`([^`]+)`/', $parte, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($codeParts as $j => $cp) {
                if (empty($cp)) continue;
                if ($j % 2 === 1) {
                    $runs .= '<w:r><w:rPr>'
                           . '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>'
                           . '<w:sz w:val="20"/><w:color w:val="6B21A8"/>'
                           . '<w:shd w:val="clear" w:color="auto" w:fill="F3F4F6"/>'
                           . '</w:rPr><w:t xml:space="preserve">' . $cp . '</w:t></w:r>';
                } else {
                    $runs .= '<w:r><w:rPr><w:color w:val="374151"/></w:rPr>'
                           . '<w:t xml:space="preserve">' . $cp . '</w:t></w:r>';
                }
            }
        }
    }
    return $runs;
}

function tabelaParaXml(array $linhasTabela): string {
    if (count($linhasTabela) < 2) return '';

    $xml = '<w:tbl><w:tblPr>'
         . '<w:tblStyle w:val="TableGrid"/>'
         . '<w:tblW w:w="9360" w:type="dxa"/>'
         . '<w:tblBorders>'
         . '<w:top w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '<w:left w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '<w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '<w:right w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '<w:insideH w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '<w:insideV w:val="single" w:sz="4" w:color="D1D5DB"/>'
         . '</w:tblBorders>'
         . '</w:tblPr>';

    foreach ($linhasTabela as $idx => $linha) {
        if (preg_match('/^\|[\s\-:]+\|/', $linha)) continue; // pula separador

        $colunas = array_filter(array_map('trim', explode('|', $linha)), fn($c) => $c !== '');
        if (empty($colunas)) continue;

        $isHeader = $idx === 0;
        $isAlt    = !$isHeader && ($idx % 2 === 0);

        $xml .= '<w:tr>';
        foreach ($colunas as $col) {
            $headerFill = defined('TABLE_HEADER_COLOR') ? TABLE_HEADER_COLOR : '0A0A0A';
            $fill = $isHeader ? $headerFill : ($isAlt ? 'F7F7F7' : 'FFFFFF');
            $cor  = $isHeader ? 'FFFFFF' : '374151';
            $bold = $isHeader ? '<w:b/><w:bCs/>' : '';
            $sz   = $isHeader ? '18' : '20';
            $safe = safeXml($col);
            if ($isHeader) $safe = mb_strtoupper($safe);

            $xml .= '<w:tc><w:tcPr>'
                  . '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>'
                  . '<w:tcMar><w:top w:w="60" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/>'
                  . '<w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tcMar>'
                  . '</w:tcPr>'
                  . '<w:p><w:r><w:rPr>' . $bold
                  . '<w:sz w:val="' . $sz . '"/><w:color w:val="' . $cor . '"/>'
                  . '</w:rPr><w:t xml:space="preserve">' . $safe . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';
    }

    $xml .= '</w:tbl><w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
    return $xml;
}

function textoParaXml(string $texto): string {
    $texto = strip_tags($texto);
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $linhas = explode("\n", $texto);
    $xml = '';
    $tabelaBuffer = [];
    $dentroTabela = false;

    foreach ($linhas as $linha) {
        $linhaRaw = $linha;
        $linha = trim($linha);

        // Detecta tabela markdown
        if (preg_match('/^\|.*\|$/', $linha)) {
            $dentroTabela = true;
            $tabelaBuffer[] = $linha;
            continue;
        } elseif ($dentroTabela) {
            $xml .= tabelaParaXml($tabelaBuffer);
            $tabelaBuffer = [];
            $dentroTabela = false;
        }

        if (empty($linha)) continue;
        if ($linha === '---') continue;

        $safe = safeXml($linha);

        // Heading 1: # Título
        if (preg_match('/^#\s+(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr>'
                  . '<w:r><w:t xml:space="preserve">' . safeXml($m[1]) . '</w:t></w:r></w:p>';
            continue;
        }

        // Heading 2: ## 01. Título
        if (preg_match('/^##\s+(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr>'
                  . '<w:r><w:t xml:space="preserve">' . safeXml($m[1]) . '</w:t></w:r></w:p>';
            continue;
        }

        // Heading 3: ### Subtítulo
        if (preg_match('/^###\s+(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:pStyle w:val="Heading3"/></w:pPr>'
                  . '<w:r><w:t xml:space="preserve">' . safeXml($m[1]) . '</w:t></w:r></w:p>';
            continue;
        }

        // Checkbox: - [ ] ou - [x]
        if (preg_match('/^-\s*\[([ x])\]\s*(.+)/', $linha, $m)) {
            $check = $m[1] === 'x' ? '☑' : '☐';
            $xml .= '<w:p><w:pPr><w:ind w:left="360"/><w:spacing w:after="60"/></w:pPr>'
                  . '<w:r><w:rPr><w:sz w:val="22"/></w:rPr>'
                  . '<w:t xml:space="preserve">' . $check . ' ' . safeXml($m[2]) . '</w:t></w:r></w:p>';
            continue;
        }

        // Bullet: - item
        if (preg_match('/^[\-•\*]\s+(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'
                  . '<w:spacing w:after="60"/></w:pPr>'
                  . processarInline(safeXml($m[1])) . '</w:p>';
            continue;
        }

        // Numbered: 1. item
        if (preg_match('/^\d+[\.\)]\s+(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr>'
                  . '<w:spacing w:after="60"/></w:pPr>'
                  . processarInline(safeXml($m[1])) . '</w:p>';
            continue;
        }

        // Blockquote: > texto
        if (preg_match('/^>\s*(.+)/', $linha, $m)) {
            $xml .= '<w:p><w:pPr><w:ind w:left="480"/><w:spacing w:after="80"/>'
                  . '<w:pBdr><w:left w:val="single" w:sz="12" w:space="8" w:color="3B82F6"/></w:pBdr>'
                  . '</w:pPr><w:r><w:rPr><w:i/><w:color w:val="4B5563"/></w:rPr>'
                  . '<w:t xml:space="preserve">' . safeXml($m[1]) . '</w:t></w:r></w:p>';
            continue;
        }

        // Parágrafo normal
        $xml .= '<w:p><w:pPr><w:spacing w:after="140" w:line="300" w:lineRule="auto"/></w:pPr>'
              . processarInline($safe) . '</w:p>';
    }

    // Fecha tabela pendente
    if ($dentroTabela && !empty($tabelaBuffer)) {
        $xml .= tabelaParaXml($tabelaBuffer);
    }

    return $xml;
}

// ── Monta o .docx ──────────────────────────────────────────────────────

$tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500); exit;
}

$isHF  = $tipo === 'hf';
$isAta = $tipo === 'ata';
$isDoc = $isHF || $isAta;

// [Content_Types].xml
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
</Types>');

$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

$zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
</Relationships>');

// Fonte e cores por tipo
$fonte   = $isDoc ? 'Arial' : 'Aptos';
$h1Size  = $isDoc ? '28' : '48';

if ($isAta) {
    $titleColor  = '0F2B46';
    $h1Color     = '0F2B46';
    $h2Color     = '1E3A5F';
    $h3Color     = '2563EB';
    $accentLine  = '1D4ED8';
    $tableHeader = '0F2B46';
} elseif ($isHF) {
    $titleColor  = '0A0A0A';
    $h1Color     = '0A0A0A';
    $h2Color     = '1F2937';
    $h3Color     = '444444';
    $accentLine  = '0A0A0A';
    $tableHeader = '0A0A0A';
} else {
    $titleColor  = '111827';
    $h1Color     = '111827';
    $h2Color     = '1F2937';
    $h3Color     = '444444';
    $accentLine  = 'E5E7EB';
    $tableHeader = '0A0A0A';
}

define('TABLE_HEADER_COLOR', $tableHeader);

$zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr>
      <w:rFonts w:ascii="' . $fonte . '" w:hAnsi="' . $fonte . '" w:cs="' . $fonte . '"/>
      <w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="374151"/>
    </w:rPr></w:rPrDefault>
    <w:pPrDefault><w:pPr>
      <w:spacing w:after="140" w:line="288" w:lineRule="auto"/>
    </w:pPr></w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/>
    <w:pPr><w:spacing w:after="0"/></w:pPr>
    <w:rPr><w:sz w:val="' . $h1Size . '"/><w:szCs w:val="' . $h1Size . '"/><w:color w:val="' . $titleColor . '"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/>
    <w:pPr><w:spacing w:before="360" w:after="160"/><w:outlineLvl w:val="0"/>
      <w:pBdr><w:bottom w:val="single" w:sz="6" w:space="6" w:color="' . $accentLine . '"/></w:pBdr>
    </w:pPr>
    <w:rPr><w:b/><w:bCs/><w:sz w:val="28"/><w:szCs w:val="28"/><w:color w:val="' . $h1Color . '"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/>
    <w:pPr><w:spacing w:before="280" w:after="120"/><w:outlineLvl w:val="1"/></w:pPr>
    <w:rPr><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:color w:val="' . $h2Color . '"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/>
    <w:pPr><w:spacing w:before="200" w:after="80"/><w:outlineLvl w:val="2"/></w:pPr>
    <w:rPr><w:b/><w:bCs/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="' . $h3Color . '"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/>
    <w:pPr><w:spacing w:after="300"/></w:pPr>
    <w:rPr><w:sz w:val="20"/><w:color w:val="9CA3AF"/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Footer"><w:name w:val="footer"/>
    <w:pPr><w:spacing w:after="0"/><w:jc w:val="center"/></w:pPr>
    <w:rPr><w:sz w:val="16"/><w:color w:val="9CA3AF"/></w:rPr>
  </w:style>
  <w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/>
    <w:tblPr><w:tblBorders>
      <w:top w:val="single" w:sz="4" w:color="D1D5DB"/>
      <w:left w:val="single" w:sz="4" w:color="D1D5DB"/>
      <w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/>
      <w:right w:val="single" w:sz="4" w:color="D1D5DB"/>
      <w:insideH w:val="single" w:sz="4" w:color="D1D5DB"/>
      <w:insideV w:val="single" w:sz="4" w:color="D1D5DB"/>
    </w:tblBorders></w:tblPr>
  </w:style>
</w:styles>');

$zip->addFromString('word/numbering.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0">
    <w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="&#x2022;"/>
    <w:lvlJc w:val="left"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>
    <w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:hint="default"/><w:color w:val="6B7280"/></w:rPr>
  </w:lvl></w:abstractNum>
  <w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0">
    <w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/>
    <w:lvlJc w:val="left"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>
    <w:rPr><w:color w:val="6B7280"/></w:rPr>
  </w:lvl></w:abstractNum>
  <w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>
  <w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>');

$footerLabel = $isHF ? 'Hist&#xF3;ria Funcional Salesforce' : ($isAta ? 'Ata de Reuni&#xe3;o' : 'AI Chat');
$zip->addFromString('word/footer1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:pPr><w:pStyle w:val="Footer"/><w:jc w:val="center"/></w:pPr>
    <w:r><w:rPr><w:color w:val="D1D5DB"/></w:rPr>
      <w:t xml:space="preserve">' . $footerLabel . '  &#x2022;  P&#xe1;gina </w:t></w:r>
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText> PAGE </w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
  </w:p>
</w:ftr>');

// ── document.xml ───────────────────────────────────────────────────────

$tituloSafe = safeXml($titulo);
$data       = date('d/m/Y H:i');
$user       = usuarioLogado();
$nomeUser   = safeXml($user['nome']);
$paragrafos = textoParaXml($conteudo);

$capa = '';
if ($isHF) {
    $capa = '
    <w:p><w:pPr><w:spacing w:before="3000" w:after="0"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="20"/><w:color w:val="9CA3AF"/><w:caps/></w:rPr>
      <w:t xml:space="preserve">Salesforce Platform</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="120"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:b/><w:sz w:val="52"/><w:szCs w:val="52"/><w:color w:val="0A0A0A"/></w:rPr>
      <w:t xml:space="preserve">HIST&#xD3;RIA FUNCIONAL</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="600"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="22"/><w:color w:val="6B7280"/></w:rPr>
      <w:t xml:space="preserve">' . $tituloSafe . '</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="60"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="18"/><w:color w:val="9CA3AF"/></w:rPr>
      <w:t xml:space="preserve">Autor: ' . $nomeUser . '  &#x2022;  Data: ' . $data . '</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="0"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="18"/><w:color w:val="9CA3AF"/></w:rPr>
      <w:t xml:space="preserve">Status: Rascunho</w:t></w:r>
    </w:p>
    <w:p><w:r><w:br w:type="page"/></w:r></w:p>';

} elseif ($isAta) {
    $capa = '
    <w:p><w:pPr><w:spacing w:before="2400" w:after="0"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="20"/><w:color w:val="9CA3AF"/><w:caps/></w:rPr>
      <w:t xml:space="preserve">Documento Interno</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="120"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:b/><w:sz w:val="48"/><w:szCs w:val="48"/><w:color w:val="0F2B46"/></w:rPr>
      <w:t xml:space="preserve">ATA DE REUNI&#xC3;O</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="400"/><w:jc w:val="center"/>
      <w:pBdr><w:bottom w:val="single" w:sz="6" w:space="12" w:color="1D4ED8"/></w:pBdr>
    </w:pPr>
      <w:r><w:rPr><w:sz w:val="22"/><w:color w:val="6B7280"/></w:rPr>
      <w:t xml:space="preserve">' . $tituloSafe . '</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="60"/><w:jc w:val="center"/></w:pPr>
      <w:r><w:rPr><w:sz w:val="18"/><w:color w:val="9CA3AF"/></w:rPr>
      <w:t xml:space="preserve">Elaborado por: ' . $nomeUser . '  &#x2022;  Data: ' . $data . '</w:t></w:r>
    </w:p>
    <w:p><w:r><w:br w:type="page"/></w:r></w:p>';

} else {
    $capa = '
    <w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr>
      <w:r><w:t xml:space="preserve">' . $tituloSafe . '</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:pStyle w:val="Subtitle"/></w:pPr>
      <w:r><w:t xml:space="preserve">Gerado em ' . $data . '</w:t></w:r>
    </w:p>
    <w:p><w:pPr><w:spacing w:after="280"/><w:pBdr>
      <w:bottom w:val="single" w:sz="4" w:space="8" w:color="E5E7EB"/>
    </w:pBdr></w:pPr></w:p>';
}

$zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    ' . $capa . '
    ' . $paragrafos . '
    <w:sectPr>
      <w:footerReference w:type="default" r:id="rId3"/>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720"/>
    </w:sectPr>
  </w:body>
</w:document>');

$zip->close();

// ── Download ───────────────────────────────────────────────────────────

if ($isHF) {
    $nomeArquivo = 'HF_' . preg_replace('/[^a-zA-Z0-9]/', '_', mb_substr($titulo, 0, 40)) . '.docx';
} elseif ($isAta) {
    $nomeArquivo = 'ATA_' . date('Y-m-d') . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', mb_substr($titulo, 0, 30)) . '.docx';
} else {
    $nomeArquivo = preg_replace('/[^a-zA-Z0-9_\-]/', '_', mb_substr($titulo, 0, 50)) . '.docx';
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
unlink($tmpFile);
exit;
