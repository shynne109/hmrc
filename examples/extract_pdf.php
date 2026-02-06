<?php
/**
 * Extract text from PDF files using stream decompression
 */

$files = [
    'd:/Herd/hmrc/src/PAYE/P11D/Scenarions/2025 PAYE Recog Instructions v1-0.pdf',
    'd:/Herd/hmrc/src/PAYE/P11D/Scenarions/P11D & P11D(b) 2024-25 Scenarios v1-0.pdf',
    'd:/Herd/hmrc/src/PAYE/P11D/Scenarions/P46(Car) 2024-25 recognition v1-0.pdf',
];

foreach ($files as $file) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "FILE: " . basename($file) . "\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $content = file_get_contents($file);
    
    // Find all streams
    $offset = 0;
    $texts = [];
    
    while (($streamStart = strpos($content, "stream\n", $offset)) !== false) {
        $streamStart += 7; // skip "stream\n"
        // Also try stream\r\n
        if (substr($content, $streamStart - 8, 8) === "stream\r\n") {
            $streamStart++;
        }
        
        $streamEnd = strpos($content, "\nendstream", $streamStart);
        if ($streamEnd === false) {
            $streamEnd = strpos($content, "\rendstream", $streamStart);
        }
        if ($streamEnd === false) break;
        
        $streamData = substr($content, $streamStart, $streamEnd - $streamStart);
        
        // Try to decompress
        $decoded = @gzuncompress($streamData);
        if ($decoded === false) {
            $decoded = @gzinflate($streamData);
        }
        if ($decoded === false) {
            // Try removing first 2 bytes (zlib header)
            $decoded = @gzinflate(substr($streamData, 2));
        }
        
        if ($decoded !== false && strlen($decoded) > 10) {
            // Extract text using TJ and Tj operators
            $pageText = '';
            
            // Method 1: TJ arrays
            if (preg_match_all('/\[([^\]]*)\]\s*TJ/s', $decoded, $tjMatches)) {
                foreach ($tjMatches[1] as $tj) {
                    if (preg_match_all('/\(([^)]*)\)/', $tj, $innerMatches)) {
                        $pageText .= implode('', $innerMatches[1]);
                    }
                }
            }
            
            // Method 2: Single Tj
            if (preg_match_all('/\(([^)]+)\)\s*Tj/', $decoded, $tjSingle)) {
                foreach ($tjSingle[1] as $t) {
                    $pageText .= $t . ' ';
                }
            }
            
            if (!empty(trim($pageText))) {
                // Clean non-printable characters but keep spaces
                $pageText = preg_replace('/[^\x20-\x7E\n\r]/', '', $pageText);
                $texts[] = $pageText;
            }
        }
        
        $offset = $streamEnd + 10;
    }
    
    if (!empty($texts)) {
        echo implode("\n", $texts) . "\n";
    } else {
        echo "[No text extracted - PDF may use specialized encoding]\n";
    }
}
