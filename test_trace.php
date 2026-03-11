<?php
$log = file_get_contents('storage/logs/laravel.log');
$lines = explode("\n", $log);
$lines = array_slice($lines, -3000);
$found = false;
$out = '';
foreach($lines as $i => $line) {
    if(strpos($line, "Xdebug has detected a possible infinite loop") !== false) {
        $found = true;
        for($j=0; $j<50; $j++) {
            if(isset($lines[$i+$j])) $out .= $lines[$i+$j] . "\n";
        }
        break;
    }
}
if(!$found) $out = "Not found in last 3000 lines.\n";
file_put_contents('trace.txt', $out);
