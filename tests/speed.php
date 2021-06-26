<?php
require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'mustache.php';
# {{{
if (0) # tokenizer
{
  $m = new \SM\MustacheEngine([
    'logger' => Closure::fromCallable('logit'),
  ]);
  $a = '

    {{^block}} {{#puke}}
      is truthy
    {|}
      is falsy
    {{/puke}}{{/block}}

  ';
  $b = $m->tokenize(['{{','}}',' '], $a);
  $b = $m->parse($a, $b);
  var_export($a);
  var_export($b);
  if ($b && 0)
  {
    echo "========\n";
    foreach($b as $c) {
      $d = $c[0] ?: 'T';
      echo "$d:{$c[2]}:{$c[3]}=".var_export($c[1], true)."\n";
    }
    echo "========\n";
  }
  exit;
}
if (0) # renderer
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
# check arguments
$args = array_slice($argv, 1);
if (!($i = count($args)) || !$args[0])
{
  # all
  if (!($file = glob(__DIR__.DIRECTORY_SEPARATOR.'*.json')))
  {
    logit("glob() failed\n");
    exit;
  }
  $test = -1;
}
else
{
  # single
  $file = [__DIR__.DIRECTORY_SEPARATOR.$args[0]];
  $test = ($i === 1) ? -1 : intval($args[1]);
}
$json = [];
foreach ($file as $i)
{
  if (!file_exists($i) ||
      !($j = json_decode(file_get_contents($i), true)) ||
      !isset($j['overview']) ||
      !isset($j['tests']))
  {
    logit("incorrect testfile: $i");
    exit;
  }
  $i = explode('.', basename($i))[0];
  if ($i === 'lambdas')
  {
    # create functions
    foreach ($j['tests'] as &$k)
    {
      $e = $k['data']['lambda'];
      $f = 'return (function($text){'.$e.'});';
      $k['data']['lambda_e'] = $e;
      $k['data']['lambda'] = Closure::fromCallable(eval($f));
    }
    unset($k);
  }
  $json[$i] = $j;
}
logit("selected: ".implode('/', array_keys($json))."\n");
# }}}
# run {{{
$m = new \SM\MustacheEngine([
  'logger' => ~$test ? Closure::fromCallable('logit') : null,
  #'logger' => Closure::fromCallable('logit'),
  'recur'  => true,
  'escape' => true,
]);
if (~$test)
{
  # single
  $json = array_pop($json);
  $test = $json['tests'][$test];
  logit("running test: {$test['name']}\n");
  logit("description: {$test['desc']}\n");
  logit("template: [".str_bg_color($test['template'], 'cyan')."]\n");
  logit('data: '.var_export($test['data'], true)."\n");
  logit("expected: [".str_bg_color($test['expected'], 'magenta')."]\n");
  logit("\n");
  $res = $m->render($test['template'], $test['data']);
  logit("\n");
  logit("result: [".str_bg_color($res, 'magenta')."]\n");
  if ($res === $test['expected']) {
    logit(str_fg_color('ok', 'green', 1)."\n");
  }
  else {
    logit(str_fg_color('fail', 'red', 1)."\n");
  }
}
else
{
  # multiple
  $noSkip = (count($json) > 1);
  foreach ($json as $k => $j)
  {
    logit("> testing: ".str_fg_color($k, 'cyan', 1)."\n");
    $i = 0;
    foreach ($j['tests'] as $test)
    {
      logit(" #".str_fg_color($i++, 'cyan', 1).": {$test['name']}.. ");
      if (!$noSkip && isset($test['skip']) && $test['skip']) {
        logit(str_fg_color('skip', 'blue', 0)."\n");
        continue;
      }
      if ($m->render($test['template'], $test['data']) === $test['expected']) {
        logit(str_fg_color('ok', 'green', 1)."\n");
      }
      else
      {
        logit(str_fg_color('fail', 'red', 1)."\n");
        if (!$noSkip) {break 2;}
      }
    }
  }
}
# }}}
# util {{{
function logit($m, $level=-1)
{
  static $e = null;
  !$e && ($e = fopen('php://stderr', 'w'));
  if (~$level) {
    $m = "sm: ".str_bg_color($m, ($level ? 'red' : 'cyan'))."\n";
  }
  fwrite($e, $m);
}
function str_bg_color($m, $name, $strong=0)
{
  static $color = [
    'black'   => [40,100],
    'red'     => [41,101],
    'green'   => [42,102],
    'yellow'  => [43,103],
    'blue'    => [44,104],
    'magenta' => [45,105],
    'cyan'    => [46,106],
    'white'   => [47,107],
  ];
  $c = $color[$name][$strong];
  return (strpos($m, "\n") === false)
    ? "[{$c}m{$m}[0m"
    : "[{$c}m".str_replace("\n", "[0m\n[{$c}m", $m).'[0m';
}
function str_fg_color($m, $name, $strong=0)
{
  static $color = [
    'black'   => [30,90],
    'red'     => [31,91],
    'green'   => [32,92],
    'yellow'  => [33,93],
    'blue'    => [34,94],
    'magenta' => [35,95],
    'cyan'    => [36,96],
    'white'   => [37,97],
  ];
  $c = $color[$name][$strong];
  return "[{$c}m{$m}[0m";
}
# }}}
?>
