<?php
/**
 * Extract text from PDF file
 */

$file = $argv[1] ?? 'd:/Herd/hmrc/src/PAYE/P11D/Scenarions/2025 PAYE Recog Instructions v1-0.pdf';
$content = file_get_contents($file);
echo "File size: " . strlen($content) . " bytes\n";
echo "stream count: " . substr_count($content, 'stream') . "\n";

$pos = 0;
$streamNum = 0;
$allText = [];

while (($pos = strpos($content, 'stream', $pos)) !== false) {
    // Skip 'endstream'
    if ($pos > 0 && substr($content, $pos - 3, 3) === 'end') {
        $pos += 6;
        continue;
    }
    $streamNum++;
    
    $dataStart = $pos + 6;
    // Skip CR/LF after 'stream'
    if (isset($content[$dataStart]) && $content[$dataStart] === "\r") $dataStart++;
    if (isset($content[$dataStart]) && $content[$dataStart] === "\n") $dataStart++;
    
    $endPos = strpos($content, 'endstream', $dataStart);
    if ($endPos === false) break;
    
    $dataEnd = $endPos;
    while ($dataEnd > $dataStart && (ord($content[$dataEnd - 1]) === 10 || ord($content[$dataEnd - 1]) === 13)) {
        $dataEnd--;
    }
    
    $data = substr($content, $dataStart, $dataEnd - $dataStart);
    
    // Try various decompression
    $decoded = @gzinflate($data);
    if ($decoded === false) $decoded = @gzuncompress($data);
    if ($decoded === false) $decoded = @gzdecode($data);
    
    if ($decoded !== false && strlen($decoded) > 20) {
        // Extract text using BT/ET
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $decoded, $btMatches)) {
            foreach ($btMatches[1] as $block) {
                $lineText = '';
                // TJ arrays
                if (preg_match_all('/\[([^\]]*)\]\s*TJ/s', $block, $tjArr)) {
                    foreach ($tjArr[1] as $arr) {
                        if (preg_match_all('/\(([^)]*)\)/', $arr, $paren)) {
                            $lineText .= implode('', $paren[1]);
                        }
                    }
                }
                // Tj singles
                if (preg_match_all('/\(([^)]+)\)\s*Tj/', $block, $tjSingle)) {
                    foreach ($tjSingle[1] as $t) {
                        $lineText .= $t;
                    }
                }
                
                $lineText = preg_replace('/[^\x20-\x7E]/', '', $lineText);
                if (strlen(trim($lineText)) > 1) {
                    $allText[] = $lineText;
                }
            }
        }
    }
    
    $pos = $endPos + 9;
}

echo "Streams found: $streamNum\n";
echo "Text blocks extracted: " . count($allText) . "\n\n";
echo implode("\n", $allText) . "\n";
