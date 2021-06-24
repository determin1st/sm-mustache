<?php
require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'mustache.php';
# tokenizer {{{
if (0)
{
  $m = new \SM\MustacheEngine([
    'logger' => Closure::fromCallable('logit'),
  ]);
  $a = $m->tokenize(['{{','}}'], '

    {{^block}}{{#puke}}
      is truthy
    {{|}}
      is falsy
    {{/puke}}{{/block}}

  ');
  if ($a && 1)
  {
    echo "========\n";
    foreach($a as $b) {
      $c = $b[0] ?: 'T';
      echo "$c:{$b[2]}:{$b[3]}=".var_export($b[1], true)."\n";
    }
    echo "========\n";
  }
  exit;
}
# }}}
# renderer {{{
if (0)
{
  $m = new \SM\MustacheEngine([
    'logger' => Closure::fromCallable('logit'),
  ]);
  $a = '

    {\\$x} \\ {${x}}
    \\\\$$

    {{^block}}{{#puke}}
      is truthy\n\n\n\n
    {{|}}
TEMPLATE
;
TEMPLATE               ;
      is falsy
    {{/puke}}{{/block}}

  ';
  echo "========\n$a";
  $a = $m->render($a, [
    'block' => 0,
    'puke'  => 1,
  ]);
  echo "========\n$a";
  #var_export($a);
  /***
  foreach($a as $b) {
    $c = $b[0] ?: 'T';
    echo "$c:{$b[2]}:{$b[3]}=".var_export($b[1], true)."\n";
  }
  /***/
  echo "========\n";
  exit;
}
# }}}
# prep {{{
$args = array_slice($argv, 1);
if (($i = count($args)) === 0 || !$args[0])
{
  logit("specify arguments: <file> [<test_num>]\n");
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
# }}}
# filetest {{{
$m = new \SM\MustacheEngine([
  'logger' => ~$test ? Closure::fromCallable('logit') : null,
]);
if (~$test)
{
  $test = $json['tests'][$test];
  logit("running test: {$test['name']}\n");
  logit("description: {$test['desc']}\n");
  logit("template: {$test['template']}\n");
  logit('data: '.var_export($test['data'], true)."\n");
  logit("expected: [{$test['expected']}]\n");
  logit("\n");
  $res = $m->render($test['template'], $test['data']);
  logit("\n");
  logit("result: [$res]\n");
}
else
{
  logit("running all tests:\n\n");
  $i = 0;
  foreach ($json['tests'] as $test)
  {
    logit("#$i: {$test['name']}.. ");
    if ($m->render($test['template'], $test['data']) === $test['expected']) {
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
# }}}
# util {{{
function logit($m, $level=-1)
{
  static $e = null;
  !$e && ($e = fopen('php://stderr', 'w'));
  fwrite($e, (~$level ? ($level.'> '.$m."\n") : $m));
}
# }}}
?>
