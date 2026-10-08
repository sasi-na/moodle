<?php
// Local helper: PHP syntax check of the new/changed plugin files. CLI only.
define('CLI_SCRIPT', true);
$root = dirname(__DIR__);
$files = array_merge(glob("$root/local/coursetutor/*.php"), glob("$root/local/coursetutor/classes/*.php"),
    glob("$root/local/coursetutor/classes/*/*.php"), glob("$root/local/coursetutor/db/*.php"),
    glob("$root/local/aidock/classes/*.php"), glob("$root/local/dttutorgemini/*.php"), glob("$root/local/dttutorgemini/classes/*.php"),
    ["$root/local/dttutorgemini/provider/chat/completions"], glob("$root/local/geniai/controller/gemini/classes/*.php"), ["$root/admin/users_keep10_local.php", "$root/local/coursetutor/qgen.php", "$root/local/coursetutor/lib.php", "$root/local/coursetutor/classes/qgen.php"]);
$bad = 0;
foreach ($files as $f) {
    $out = shell_exec('"' . PHP_BINARY . '" -l "' . $f . '" 2>&1');
    if (strpos($out, 'No syntax errors') === false) { $bad++; echo $out; }
}
echo count($files) . " files checked, $bad with errors\n";
