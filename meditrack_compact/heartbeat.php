<?php
require_once 'core.php';

$l = location_cols();
$readerid = getv('readerid');
$apikey = getv('apikey');

if (!$readerid || !$apikey) {
    exit('ERROR');
}

$sql = "UPDATE locations
        SET `{$l['lastheartbeat']}`=NOW()
        WHERE `{$l['readerid']}`=? AND `{$l['apikey']}`=? AND `{$l['isactive']}`=1";
$st = $db->prepare($sql);
$st->bind_param('ss', $readerid, $apikey);
$st->execute();

echo $st->affected_rows > 0 ? 'OK' : 'ERROR';