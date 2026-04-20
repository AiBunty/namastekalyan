<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');

$xlsx = __DIR__ . '/Namaste Kalyan Menu.xlsx';
if (!file_exists($xlsx)) { echo "Excel not found\n"; exit(1); }

class SimpleXlsxReader {
    private ZipArchive $zip;
    private array $ss = [];
    private ?array $sheetMap = null;
    public function open(string $p): void {
        $this->zip = new ZipArchive();
        $this->zip->open($p);
        $this->ss = $this->loadSS();
    }
    public function close(): void { $this->zip->close(); }
    private function loadSS(): array {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if (!$xml) return [];
        $d = @simplexml_load_string($xml);
        $out = [];
        foreach ($d->si as $si) {
            if (isset($si->t)) $out[] = (string)$si->t;
            else { $t=''; foreach ($si->r??[] as $r) $t .= (string)($r->t??''); $out[]=$t; }
        }
        return $out;
    }
    private function getSheetMap(): array {
        if ($this->sheetMap !== null) return $this->sheetMap;
        $wb = simplexml_load_string($this->zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($this->zip->getFromName('xl/_rels/workbook.xml.rels'));
        $id2f = [];
        foreach ($rels->Relationship as $r) { $a=$r->attributes(); $id2f[(string)$a['Id']] = (string)$a['Target']; }
        $rNs='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $this->sheetMap = [];
        foreach ($wb->sheets->sheet as $s) {
            $n=(string)$s->attributes()['name'];
            $rid=(string)$s->attributes($rNs)['id'];
            if (isset($id2f[$rid])) {
                $t = ltrim($id2f[$rid],'/');
                $this->sheetMap[$n] = strpos($t,'xl/')===0 ? $t : 'xl/'.$t;
            }
        }
        return $this->sheetMap;
    }
    public function getSheetNames(): array { return array_keys($this->getSheetMap()); }
    private function col2idx(string $ref): int {
        preg_match('/^([A-Z]+)/',$ref,$m); $l=$m[1]??'A'; $i=0;
        for($c=0,$len=strlen($l);$c<$len;$c++) $i=$i*26+(ord($l[$c])-64);
        return $i-1;
    }
    private function cellVal(\SimpleXMLElement $c): string {
        $t=(string)($c->attributes()['t']??''); $v=(string)($c->v??'');
        return match($t){'s'=>$this->ss[(int)$v]??'','inlineStr'=>(string)($c->is->t??''),'b'=>($v==='1')?'1':'0',default=>$v};
    }
    public function readRows(string $name, int $maxRows=3): array {
        $map = $this->getSheetMap();
        if (!isset($map[$name])) return [];
        $xml = $this->zip->getFromName($map[$name]);
        $d = simplexml_load_string($xml);
        $rows=[]; $rowCount=0;
        foreach ($d->sheetData->row as $row) {
            $rowCount++;
            if ($rowCount > $maxRows+1) break; // header + maxRows data
            $rIdx=(int)$row->attributes()['r']-1;
            $cells=[];
            foreach ($row->c as $c) { $ci=$this->col2idx((string)$c->attributes()['r']); $cells[$ci]=$this->cellVal($c); }
            if (!empty($cells)) { $max=max(array_keys($cells)); for($i=0;$i<=$max;$i++) $cells[$i]=$cells[$i]??''; ksort($cells); $rows[$rIdx]=$cells; }
        }
        return $rows;
    }
    public function countDataRows(string $name): int {
        $map=$this->getSheetMap();
        if (!isset($map[$name])) return 0;
        $xml=$this->zip->getFromName($map[$name]);
        $d=simplexml_load_string($xml);
        $count=0; $headerFound=false;
        foreach ($d->sheetData->row as $row) {
            // Count all rows after first non-empty row
            $count++;
        }
        return max(0, $count-1); // subtract header row
    }
}

$r = new SimpleXlsxReader();
$r->open($xlsx);
$sheets = $r->getSheetNames();

echo "=== EXCEL WORKBOOK AUDIT ===\n";
echo "Total sheets: ".count($sheets)."\n\n";

foreach ($sheets as $sheet) {
    $rows = $r->readRows($sheet, 2);
    $dataCount = $r->countDataRows($sheet);
    $headers = array_values($rows[0] ?? []);
    $headers = array_filter($headers, fn($h) => trim($h) !== '');
    echo "[$sheet]\n";
    echo "  Data rows (approx): $dataCount\n";
    echo "  Columns: " . implode(', ', $headers) . "\n\n";
}
$r->close();
