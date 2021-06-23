<?php
namespace SM;
class MustacheEngine {
  # BASE {{{
  const SPEC   = '1.1.2';
  const DELIMS = '{}[]()<>:%=~-_?*@!|';# valid delimiter characters
  const TAG_SZ = 40;# maximal {{tag}} size (without delimiters)
  public
    $templates = [],
    $funcs     = [],
    $delims    = ['{{','}}'],
    $helpers   = null,
    $logger    = null;
  ###
  ###
  const T_SECTION     = '#';
  const T_INVERTED    = '^';
  const T_ELSE        = '|';
  const T_SECTION_END = '/';
  const T_VAR         = '_v';
  const T_TEXT        = '_t';
  private static $TAGS = [
    '#' => '#',# if
    '|' => '|',# else
    '!' => '!',# if not
    '^' => '!',# if not (alias, for compatibility)
    '/' => '/',# end if
  ];
  private static $tagTypes = [
    self::T_SECTION     => true,
    self::T_INVERTED    => true,
    self::T_SECTION_END => true,
    self::T_VAR         => true,
  ];
  ###
  ###
  private static $TE = [
    # evaluated chunks of code
    'FUNC' =>
    '

function($x) { #%s,depth=%s
  return <<<TEMPLATE
%s
TEMPLATE;
}

    ',
    'SECTION' => '{$x->f(%s,%s,1)}',
    'INVERTED_SECTION' => '{$x->f(%s,%s,0)}',
    'VARIABLE' => '{$x(%s)}',
  ];
  public function __construct($o = [])
  {
    isset($o['delims'])  && ($this->delims  = $o['delims']);
    isset($o['helpers']) && ($this->helpers = $o['helpers']);
    isset($o['logger'])  && ($this->logger  = $o['logger']);
    self::$TE['FUNC'] = str_replace("\r", "", trim(self::$TE['FUNC']));
  }
  private function log($text, $level = 0) {
    ($log = $this->logger) && $log($text, $level);
  }
  # }}}
  public function render($text, $context = [], $delims = null) # {{{
  {
    if ($text)# tempate text specified
    {
      if ($delims)# custom delimiters specified
      {
        # check correctness, extract and set
        $i = preg_quote(self::DELIMS);
        if (!is_string($delims) ||
            !preg_match('/^(['.$i.']{2})\s*(['.$i.']{2})$/', $delims, $i))
        {
          $this->log('incorrect delimiters: '.var_export($delims, true));
        }
        $this->delims = [$i[1], $i[2]];
      }
      # render template function and execute it within given context
      $text = ~($i = $this->renderFunc($text))
        ? $this->funcs[$i](new MustacheContext($this, $context))
        : '';
    }
    return $text;
  }
  # }}}
  private function renderFunc($text, &$tree = null, $depth = -1) # {{{
  {
    # check
    if (!$text) {# recursion
      $text = $k = $this->compose($tree, ++$depth);
    }
    else {# first call
      $k = implode('', $this->delims).$text;
    }
    # check cache
    if (isset($this->templates[$k])) {
      $i = $this->templates[$k];
    }
    else
    {
      # create parse tree
      if (!$tree)
      {
        $tree = $this->tokenize($text);
        var_export($tree);
        return -1;
        $this->clearStandaloneSections($tree);
        $tree = $this->parse($tree);
      }
      # create renderer function code
      $text = $this->compose($tree, ++$depth);
      $i    = count($this->funcs);
      $text = sprintf(self::$TE['FUNC'], $i, $depth, $text);
      # evaluate and store
      $this->templates[$k] = $i;
      $this->funcs[$i] = eval("return ($text);");
      $this->log($text);
    }
    # complete
    return $i;
  }
  # }}}
  public function tokenize($text) # TODO {{{
  {
    # prepare
    $tokens = [];
    $tOpen  = $this->delims[0];
    $tClose = $this->delims[1];
    $tSize  = strlen($tOpen);
    # iterate
    $i = $n = $line = 0;
    $k = strlen($text);
    while ($i < $k)
    {
      # search both newline and tag opening
      $a = strpos($text, "\n", $i);
      $b = strpos($text, $tOpen, $i);
      # check no more tags left
      if ($b === false)
      {
        # to be able to catch standalone tag later,
        # add next text chunk spearately
        if ($a !== false)
        {
          $tokens[$n++] = [
            'TYPE' => '',
            'LINE' => $line++,
            'TEXT' => substr($text, $i, ($j = $a - $i + 1)),
          ];
          $i = $a + 1;# move to the next char after newline
        }
        # add last text chunk as a whole
        $tokens[$n++] = [
          'TYPE' => '',
          'LINE' => $line,
          'TEXT' => substr($text, $i),
        ];
        # complete
        break;
      }
      # accumulate text tokens
      while ($a !== false && $a < $b)
      {
        $tokens[$n++] = [
          'TYPE' => '',
          'LINE' => $line++,
          'TEXT' => substr($text, $i, ($j = $a - $i + 1)),
        ];
        $i = $a + 1;# move to the next char after newline
        $a = strpos($text, "\n", $i);
      }
      # the tag must not be empty, oversized or unknown, so,
      # find closing delimiter, check for false opening and
      # validate tag type (first character)
      $b += $tSize;# shift to the tag name
      if (($c = strpos($text, $tClose, $b)) === false ||
          ($j = $c - $b) === 0 || $j > self::TAG_SZ ||
          !($tag = trim(substr($text, $b, $j), ' ')) ||
          !isset(self::$TAGS[$tag[0]]))
      {
        echo("i=$i,a=$a,b=$b,c=$c,tag=$tag,n=$n\n");
        echo "FALSE TAG\n";
        # report as problematic but acceptable
        $this->log("false tag skipped: '$tag'", 0);
        # add delimiter token
        $tokens[$n++] = [
          'TYPE' => '',
          'LINE' => $line++,
          'TEXT' => substr($text, $i, ($j = $a - $i + 1)),
        ];
        # skip to the next character after opening delimiter
        $i = $b;
        break;
        continue;
        # ...
        # ...
        # check previous token was a text without newline
        if ($n && !$tokens[$n - 1]['TYPE'] &&
            substr($tokens[$n - 1]['TEXT'], -1) !== "\n")
        {
          # append
          $tokens[$n - 1]['TEXT'] .= ($a === false)
            ? substr($text, $b)# all
            : substr($text);# chunk
        }
        else
        {
          # create new
        }
        # ...
        # ...
        # ...
        break;
      }
      # add tag token
      $tokens[$n++] = [
        'TYPE' => self::$TAGS[$tag[0]],
        'LINE' => $line,
        'TEXT' => substr($tag, 1),
      ];
      # continue (from the closing delimiter)
      $i = $c + $tSize + 1;
    }
    return $tokens;
  }
  # }}}
  private function clearStandaloneSections(&$tokens) # {{{
  {
    # prepare
    $line = $count = $sects = 0;
    # iterate
    for ($a = 0, $b = count($tokens); $a <= $b; ++$a)
    {
      # check on the same line
      if ($a < $b && $line === $tokens[$a]['LINE']) {
        ++$count;# total tokens in a line
      }
      else
      {
        # check if any sections in this line could be standalone
        if ($sects && ($c = $count - $sects) && $c <= 2)
        {
          $i = $a - $count;# first
          $j = $a - 1;# last
          if ($c === 1)
          {
            # check last token is whitespace OR
            # it's the very last node with the first token whitespaced
            if ($tokens[$j]['TYPE'] === self::T_TEXT &&
                ctype_space($tokens[$j]['TEXT']))
            {
              $tokens[$j]['TEXT'] = '';
            }
            elseif ($a === $b && $tokens[$i]['TYPE'] === self::T_TEXT &&
                    ctype_space($tokens[$i]['TEXT']))
            {
              $tokens[$i]['TEXT'] = '';
            }
          }
          else
          {
            # check first and last are whitespace
            if ($tokens[$i]['TYPE'] === self::T_TEXT &&
                $tokens[$j]['TYPE'] === self::T_TEXT &&
                ctype_space($tokens[$i]['TEXT']) &&
                ctype_space($tokens[$j]['TEXT']))
            {
              $tokens[$i]['TEXT'] = $tokens[$j]['TEXT'] = '';
            }
          }
        }
        # check the end
        if ($a === $b) {
          break;
        }
        # line changed, reset
        $line  = $tokens[$a]['LINE'];
        $count = 1;
        $sects = 0;
      }
      # count section tokens
      switch ($tokens[$a]['TYPE']) {
      case self::T_SECTION:
      case self::T_INVERTED:
      case self::T_SECTION_END:
        ++$sects;
      }
    }
  }
  # }}}
  private function parse(&$tokens, $p = null) # {{{
  {
    # construct syntax tree
    $tree = [];
    while ($tokens)
    {
      # extract next token
      $t = array_shift($tokens);
      # check
      switch ($t['TYPE']) {
      case self::T_SECTION:
      case self::T_INVERTED:
        # recurse
        if (!($t = $this->parse($tokens, $t))) {
          return null;# something went wrong
        }
        $tree[] = $t;
        break;
      case self::T_SECTION_END:
        # check
        if (!isset($p) ||
            $t['NAME'] !== $p['NAME'])
        {
          $this->log('unexpected closing tag: '.$t['NAME'].' at line '.$t['LINE'], 1);
          return null;
        }
        # section assembled
        $p['NODES'] = $tree;
        return $p;
      default:
        $tree[] = $t;
        break;
      }
    }
    # check
    if (isset($p))
    {
      $this->log('missing closing tag: '.$p['NAME'].' at line '.$p['LINE'], 1);
      return null;
    }
    return $tree;
  }
  # }}}
  private function compose(&$tree, $depth) # {{{
  {
    $code = '';
    foreach ($tree as $node)
    {
      switch ($node['TYPE']) {
      case self::T_SECTION:
        # recurse and create nested call
        $name  = $node['NAME'];
        $code .= sprintf(
          self::$TE['SECTION'],
          "'$name'", $this->renderFunc('', $node['NODES'], $depth)
        );
        break;
      case self::T_INVERTED:
        $name  = $node['NAME'];
        $code .= sprintf(
          self::$TE['INVERTED_SECTION'],
          "'$name'", $this->renderFunc('', $node['NODES'], $depth)
        );
        break;
      case self::T_VAR:
        $name  = $node['NAME'];
        $code .= sprintf(self::$TE['VARIABLE'], "'$name'");
        break;
      case self::T_TEXT:
        $code .= $node['TEXT'];
        break;
      }
    }
    return $code;
  }
  # }}}
}
class MustacheContext # {{{
{
  public $engine, $stack;
  public function __construct($engine, $context)
  {
    $this->engine = $engine;
    $this->stack  = [];
    if ($engine->helpers) {
      array_push($this->stack, $engine->helpers);
    }
    if ($context) {
      array_push($this->stack, $context);
    }
  }
  public function __invoke($name)
  {
    return is_callable($v = $this->v($name))
      ? call_user_func($v, '')
      : $v;
  }
  public function f($name, $i)
  {
    # {{{
    # prepare
    if (!($v = $this->v($name))) {
      return '';
    }
    # invoke helper first
    if (is_callable($v)) {
      #$v = call_user_func($v, $engine);
      $v = call_user_func($v, '');
    }
    # check iterable
    if (is_array($v))
    {
      # array may have mixed keys,
      # for simplicity, assume all or nothing
      $x = is_int(key($v));
    }
    elseif (is_object($v))
    {
      # object must be special
      $x = ($v instanceof Traversable);
    }
    else {
      $x = false;
    }
    # render
    if ($x)
    {
      $s = '';
      foreach ($v as $x)
      {
        array_push($this->stack, $x);
        $s .= $this->engine->funcs[$i]($this);
        array_pop($this->stack);
      }
    }
    else
    {
      array_push($this->stack, $v);
      $s = $this->engine->funcs[$i]($this);
      array_pop($this->stack);
    }
    return $s;
    # }}}
  }
  private function v($name, &$stack = null)
  {
    # {{{
    # prepare
    if (!$stack) {
      $stack = &$this->stack;
    }
    # check
    if (strpos($name, '.') === false)
    {
      # property name
      # search from the last to the first context frame in the stack
      for ($i = count($stack) - 1; $i >= 0; --$i)
      {
        $x = &$stack[$i];
        if (is_object($x))
        {
          # skip closure/function (non-valid value)
          if ($x instanceof Closure) {
            continue;
          }
          # check method
          if (method_exists($x, $name)) {
            return $x->$name();
          }
          # check property
          if (isset($x->$name)) {
            return $x->$name;
          }
          # check special object's property
          if ($x instanceof ArrayAccess && isset($x[$name])) {
            return $x[$name];
          }
        }
        # check array property
        if (is_array($x) && isset($x[$name])) {
          return $x[$name];
        }
        # continue..
      }
      # not found..
      return '';
    }
    if ($name === '.')
    {
      # implicit iterator
      return end($this->stack);
    }
    # dot notation
    foreach (explode('.', $name) as $name)
    {
      # recurse
      if (!($v = $this->v($name, $stack))) {
        break;
      }
      # dive into the value
      unset($stack);
      $stack = [$v];
    }
    return $v;
    # }}}
  }
}
# }}}
?>
