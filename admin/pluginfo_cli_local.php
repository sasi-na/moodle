<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');
$api = \core\update\api::client();
foreach (['aiprovider_datacurso', 'local_dttutor'] as $p) {
    foreach ([405, 500, 501] as $br) {
        $i = $api->find_plugin($p, ANY_VERSION, $br);
        if ($i && !empty($i->version)) {
            $v = $i->version;
            echo "$p for branch $br: release {$v->release} version {$v->version} supports " . implode(',', array_map(fn($m) => $m->release, (array)($v->supportedmoodles ?? []))) . "\n   zip: {$v->downloadurl}\n";
        } else { echo "$p for branch $br: none\n"; }
    }
}
