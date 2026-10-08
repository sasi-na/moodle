<?php
require_once(__DIR__ . '/../config.php');
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) { die('Localhost only'); }
require_login(); $PAGE->set_context(context_system::instance()); $PAGE->set_url('/admin/weather_test_local.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/weather/block_weather.php');
$b = new block_weather(); $b->config = (object)['city' => 'Bengaluru'];
$t = microtime(true); $c = $b->get_content(); $t1 = round(microtime(true) - $t, 2);
$b2 = new block_weather(); $b2->config = (object)['city' => 'Bengaluru']; $t = microtime(true); $b2->get_content(); $t2 = round(microtime(true) - $t, 3);
header('Content-Type: text/plain');
echo "first: {$t1}s, cached: {$t2}s\n" . strip_tags(str_replace('<', ' <', $c->text));
