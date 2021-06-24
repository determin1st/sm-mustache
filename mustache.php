<?php
namespace SM;
class MustacheEngine {
  # BASE {{{
  const SPEC     = '1.1.2';# origin's
  const DELIMS   = '{}[]()<>:%=~-_?*@!|';# valid delimiter chars
  const DELIM_SZ = 4;# max size of a delimeter (minimal is 2)
  const NAME_SZ  = 32;# max {{name}} size (without delimiters/spacing)
  private static
    $BLOCKS = '#^|/!';
    $TAGS = [
      '#' => '#',# if
      '^' => '^',# if not
      '|' => '|',# else
      '/' => '/',# fi
      '!' => '!',# comment
      '.' => '.',# iterator
      # _ variable
    ],
    $TE = [# evaluated chunks of code
      'FUNC' => '
function($x) { #%s,depth=%s
return <<<TEMPLATE
%s
TEMPLATE;
}
      ',
      '#' => '{$x->f(%s,%s,0)}',
      '^' => '{$x->f(%s,%s,1)}',
      '_' => '{$x(%s)}',
    ];
  ###
  public
    $funcs     = [];# index=>function
  private
    $templates = [],# text=>index
    $delims    = ['{{','}}'],
    $helpers   = null,
    $logger    = null;
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
  function render($text, $context = [], $delims = null) # {{{
  {
    if ($text)# tempate text specified
    {
      if ($delims)# custom delimiters specified
      {
        # check correctness, extract and set
        $i = preg_quote(self::DELIMS);
        $k = '2,'.self::DELIM_SZ;
        if (!is_string($delims) ||
            !preg_match('/^(['.$i.']{'.$k.'})(\s*)(['.$i.']{'.$k.'})$/', $delims, $i))
        {
          $this->log('incorrect delimiters: '.var_export($delims, true));
          return $text;
        }
        $delims = [$i[1], $i[3], (strlen($i[2]) - 1)];
      }
      else {
        $delims = $this->delims;
      }
      # render template function and execute it within given context
      $text = ~($i = $this->renderFunc($delims, $text))
        ? $this->funcs[$i](new MustacheContext($this, $context))
        : '';
    }
    return $text;
  }
  # }}}
  private function renderFunc($delims, $text, &$tree = null, $depth = -1) # {{{
  {
    # check
    if (!$text) {# recursion
      $text = $k = $this->compose($delims, $tree, ++$depth);
    }
    else {# first call
      $k = implode('', $delims).$text;
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
        $tree = $this->tokenize($delims, $text);
        if (!$tree = $this->parse($tree)) {
          return -1;
        }
      }
      # create renderer function code
      $text = $this->compose($delims, $tree, ++$depth);
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
  function tokenize($delims, $text) # {{{
  {
    # prepare
    $tokens = [];# [<TYPE>,<TEXT>,<LINE>,<INDENT>]
    $size0  = strlen($delims[0]);
    $size1  = strlen($delims[1]);
    $length = strlen($text);
    $i = $j = $line = 0;
    # iterate
    while ($i < $length)
    {
      # search both newline and tag opening
      $a = strpos($text, "\n", $i);
      $b = strpos($text, $delims[0], $i);
      # check no more tags left
      if ($b === false)
      {
        # to be able to catch standalone tags later,
        # add next text chunk spearately
        if ($a !== false)
        {
          $tokens[$j++] = [
            '', substr($text, $i, $a - $i + 1),
            $line++, -1
          ];
          $i = $a + 1;# move to the char after newline
        }
        # add last text chunk as a whole and complete
        $tokens[$j++] = ['', substr($text, $i), $line, -1];
        break;
      }
      # accumulate text lines
      while ($a !== false && $a < $b)
      {
        $tokens[$j++] = [
          '', substr($text, $i, $a - $i + 1),
          $line++, -1
        ];
        $i = $a + 1;# move to the char after newline
        $a = strpos($text, "\n", $i);
      }
      # check something left before the opening
      if ($i < $b)
      {
        # add last text token
        $a = substr($text, $i, $b - $i);
        $tokens[$j++] = ['', $a, $line, -1];
        # check it's an indentation and determine it's size
        $indent = (trim($a, " \t") ? -1 : strlen($a));
      }
      else {# opening at newline
        $indent = 0;
      }
      # the tag must not be empty, oversized or unknown, so,
      # find closing delimiter, check for false opening and
      # validate tag (first character)
      $b += $size0;# shift to the tag name
      if (($a = strpos($text, $delims[1], $b)) === false ||
          !($c = trim(substr($text, $b, $a - $b), ' ')) ||
          (!ctype_alnum($c[0]) && !isset(self::$TAGS[$c[0]])) ||
          (strlen($c) > self::NAME_SZ && ($c[0] !== self::$TAGS['!'])))
      {
        # report as problematic but acceptable (not an error)
        $this->log('false tag skipped', 0);
        # check newline
        if ($j && !$tokens[$j - 1][0] &&
            substr($tokens[$j - 1][1], -1) === "\n")
        {
          ++$line;
        }
        # add opening delimiter as a text token
        $tokens[$j++] = ['', $delims[0], $line, -1];
        # continue from the next character..
        $i = $b;
        continue;
      }
      # add token
      if (isset(self::$TAGS[$c[0]]))
      {
        # block
        $b = self::$TAGS[$c[0]];
        $c = ($b === self::$TAGS['!'])
          ? '' # ignore comment
          : ltrim(substr($c, 1));# trim leading whitespace
      }
      else {# variable
        $b = '_';
      }
      $tokens[$j++] = [$b, $c, $line, $indent];
      # continue after the closing delimiter
      $i = $a + $size1;
    }
    # tokens collected,
    # clear standalone blocks
    # {{{
    # prepare
    $line = $size0 = $size1 = 0;
    # iterate
    for ($i = 0; $i <= $j; ++$i)
    {
      # check on the same line
      if ($i < $j && $line === $tokens[$i][2]) {
        ++$size0;# total tokens in a line
      }
      else
      {
        # line changed,
        # check line has any blocks that could be standalone
        if ($size1 && ($c = $size0 - $size1) && $c <= 2)
        {
          # get first and last token indexes
          $a = $i - $size0;
          $b = $i - 1;
          # check count difference
          if ($c === 1)
          {
            # one token isn't a block,
            # it must be the last (line terminator) or
            # the first (identation whitespace) text token
            if (!$tokens[$b][0] &&
                ctype_space($tokens[$b][1]))
            {
              $tokens[$b][1] = '';
            }
            elseif ($i === $j && !$tokens[$a][0] &&
                    ctype_space($tokens[$a][1]))
            {
              $tokens[$a][1] = '';
              break;# final block(s)
            }
          }
          else
          {
            # two tokens are not blocks,
            # check both first and last are whitespaces
            if (!$tokens[$a][0] && !$tokens[$b][0] &&
                ctype_space($tokens[$a][1]) &&
                ctype_space($tokens[$b][1]))
            {
              $tokens[$a][1] = $tokens[$b][1] = '';
            }
          }
        }
        # check the end
        if ($i === $j) {
          break;
        }
        # change line and reset counters
        $line  = $tokens[$i][2];
        $size0 = 1;
        $size1 = 0;
      }
      # count blocks
      if (($a = $tokens[$i][0]) && strpos(self::$BLOCKS, $a) !== false) {
        ++$size1;
      }
    }
    # }}}
    # complete
    return $tokens;
  }
  # }}}
  function parse(&$tokens, $p = null) # {{{
  {
    # construct syntax tree
    $tree = [];# [<TYPE>,<TEXT>,<LINE>,<INDENT>,<CHILDREN>]
    while ($tokens)
    {
      # extract next token
      $t = array_shift($tokens);
      # check
      switch ($t[0]) {
      case '':
      case '_':
      case '.':
        $tree[] = $t;
        break;
      case '#':
      case '^':
        # recurse
        if (!($t = $this->parse($tokens, $t))) {
          return null;# something went wrong
        }
        $tree[] = $t;
        break;
      case '/':
        # check
        if (!isset($p) || $t[1] !== $p[1])
        {
          $this->log('unexpected closing tag: '.$t[1].' at line '.$t[2], 1);
          return null;
        }
        # block assembled
        $p[4] = $tree;
        return $p;
      }
    }
    # check
    if (isset($p))
    {
      $this->log('missing closing tag: '.$p[1].' at line '.$p[2], 1);
      return null;
    }
    return $tree;
  }
  # }}}
  private function compose($delims, &$tree, $depth) # {{{
  {
    $code = '';
    foreach ($tree as $t)
    {
      switch ($t[0]) {
      case '':
        # text
        # apply heredoc guards
        if (strpos($t[1], '\\') !== false) {
          $t[1] = str_replace('\\', '\\\\', $t[1]);
        }
        if (strpos($t[1], '$') !== false) {
          $t[1] = str_replace('$', '\\$', $t[1]);
        }
        $code .= $t[1];
        break;
      case '_':
        # variable
        $code .= sprintf(self::$TE['_'], "'".$t[1]."'");
        break;
      case '#':
      case '^':
        # block
        if ($t[4])
        {
          $code .= sprintf(
            self::$TE[$t[0]],
            $this->renderFunc($delims, '', $t[4], $depth),
            "'".$t[1]."'"
          );
        }
        break;
      case '.':
        # iterator
        $code .= sprintf(self::$TE['_'], "'.".$t[1]."'");
        break;
      }
    }
    # apply heredoc terminator guard and complete
    return (strpos($code, $t = "\nTEMPLATE") !== false)
      ? str_replace($t, '{$x}', $code)
      : $code;
  }
  # }}}
}
class MustacheContext # {{{
{
  private $engine, $stack;
  function __construct($engine, $context)
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
  function __toString() {
    return "\nTEMPLATE";# guard
  }
  function __invoke($name)
  {
    return is_callable($v = $this->v($name))
      ? call_user_func($v, '')# lambda variable
      : $v;# value
  }
  function f($i, $name, $negate)
  {
    # {{{
    # get value and handle negate logic
    if (!($v = $this->v($name)))
    {
      return $negate
        ? $this->engine->funcs[$i]($this)
        : '';
    }
    elseif ($negate) {
      return '';
    }
    # invoke block lambda
    if (is_callable($v)) {
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
