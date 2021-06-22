<?php
# prep {{{
$args = array_slice($argv, 1);
if (($i = count($args)) === 0)
{
  logit("specify arguments: <file> [<test_no>]\n");
  exit;
}
$test = ($i > 1) ? intval($args[1]) : -1;
$file = __DIR__.DIRECTORY_SEPARATOR.$args[0];
if (!file_exists($file) ||
    !($json = json_decode(file_get_contents($file), true)) ||
    !isset($json['overview']) ||
    !isset($json['tests']))
{
  logit("incorrect testfile: $file", 1);
  exit;
}
logit("testfile: $file \n");
require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'mustache.php';
# }}}
###
$TE = new \SM\MustacheEngine([
  'logger' => ~$test ? Closure::fromCallable('logit') : null,
]);
if (~$test)
{
  # ONE
  $test = $json['tests'][$test];
  logit("running test: {$test['name']}\n");
  logit("description: {$test['desc']}\n");
  logit("template: {$test['template']}\n");
  logit('data: '.var_export($test['data'], true)."\n");
  logit("expected: [{$test['expected']}]\n");
  logit("\n");
  $res = $TE->render($test['template'], $test['data']);
  logit("\n");
  logit("result: [$res]\n");
}
else
{
  # ALL
  logit("running all tests:\n\n");
  $i = 0;
  foreach ($json['tests'] as $test)
  {
    logit("#$i: {$test['name']}.. ");
    if ($TE->render($test['template'], $test['data']) === $test['expected']) {
      logit("ok\n");
    }
    else
    {
      logit("failed\n");
      break;
    }
    ++$i;
  }
}
###
# util {{{
function logit($m, $level=-1)
{
  static $e = null;
  !$e && ($e = fopen('php://stderr', 'w'));
  fwrite($e, (~$level ? ($level.'> '.$m."\n") : $m));
}
# }}}
?>
