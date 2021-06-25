<?php
namespace SM;
class MustacheEngine {
  # BASE {{{
  const SPEC     = '1.1.2';# origin's
  const DELIMS   = '{}[]()<>:%=~-_?*@!|';# valid delimiter chars
  const DELIM_SZ = 4;# max size of a delimeter (minimal is 2)
  const NAME_SZ  = 32;# max {{name}} size (without delimiters/spacing)
  private static
    $TAGS = [
      '#' => '#',# if
      '^' => '^',# if not
      #'|' => '|',# else
      '/' => '/',# fi
      '!' => '!',# comment
      '.' => '.',# iterator
      # _ => variable (no tag)
    ],
    $BLOCKS = '#^|/!',
    $TE = [# evaluated chunks of code
      'F' => '

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
  private $O = [
    'templates' => [],# template=>index
    'funcs'     => [],# index=>function
    'delims'    => ['{{','}}',' '],# opener,closer,indent
    'helpers'   => null,# context fallback data
    'logger'    => null,# callable
    'recur'     => false,# template context recursion
  ];
  function __construct($o = [])
  {
    # assumed correct
    isset($o[$k = 'delims'])  && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'helpers']) && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'logger'])  && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'recur'])   && ($this->O[$k] = $o[$k]);
    # prepare once
    if (!isset(self::$TE['f'])) {
      self::$TE['f'] = str_replace("\r", "", trim(self::$TE['F']));
    }
  }
  function log($text, $level = 0) {
    ($log = $this->O['logger']) && $log($text, $level);
  }
  # }}}
  function render($text, $p1 = null, $p2 = null) # {{{
  {
    # check parameters
    if (!$text || !is_string($text)) {
      return $text;
    }
    if ($p2 === null)# context only
    {
      $p2 = $p1;
      $p1 = $this->O['delims'];
    }
    elseif ($p1)# delimiters and context
    {
      # check non-strict
      if (!is_array($p1))
      {
        # check correct
        $i = preg_quote(self::DELIMS);
        $k = '2,'.self::DELIM_SZ;
        if (!is_string($p1) ||
            !preg_match('/^(['.$i.']{'.$k.'})(\s+)(['.$i.']{'.$k.'})$/', $p1, $i))
        {
          $this->log('incorrect delimiters: '.var_export($p1, true), 1);
          return $text;
        }
        # extract
        $p1 = [$i[1], $i[3], $p[2]];
      }
    }
    else
    {
      $this->log('incorrect api usage, check call spec', 1);
      return '';
    }
    # create template function
    if (($i = $this->renderFunc($p1, $text)) === -1) {
      return '';
    }
    # execute it within the given context
    $k = new MustacheContext($this, $this->O, $p1, $p2);
    return $this->O['funcs'][$i]($k);
  }
  # }}}
  private function renderFunc($delims, $text, &$tree = null, $depth = -1) # {{{
  {
    # check cache
    $k = implode('', $delims).$text;
    if (isset($this->O['templates'][$k])) {
      $i = $this->O['templates'][$k];
    }
    else
    {
      # create parse tree
      if (!$tree)
      {
        $tree = $this->tokenize($delims, $text);
        if (!$tree = $this->parse($text, $tree)) {
          return -1;
        }
      }
      # create renderer function code
      $text = $this->compose($delims, $tree, ++$depth);
      $i    = count($this->O['funcs']);
      $text = sprintf(self::$TE['f'], $i, $depth, $text);
      # evaluate and store
      $this->O['templates'][$k] = $i;
      $this->O['funcs'][$i] = eval("return ($text);");
      $this->log($text);
    }
    # complete
    return $i;
  }
  # }}}
  function tokenize($delims, &$text) # {{{
  {
    # prepare
    $tokens = [];# [<type>,<name>,<line>,<indent>,<index>]
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
        $i = $b;
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
        # add false opening as a text token and restart
        $tokens[$j++] = ['', $delims[0], $line, -1];
        # continue after the false opening
        $i = $b;
        continue;
      }
      # check token tag
      if (isset(self::$TAGS[$c[0]]))
      {
        # determine type and name
        $b = self::$TAGS[$c[0]];
        $c = ($b === self::$TAGS['!'])
          ? '' # comment
          : ltrim(substr($c, 1));# trim leading whitespace
        # add block
        $tokens[$j++] = [
          $b, $c, $line, $indent,
          ($b === '/' ? $i : $a + $size1) # start/end index reversed by tag
        ];
      }
      else
      {
        # add variable
        $tokens[$j++] = ['_', $c, $line, $indent];
      }
      # shift to the next char after the closing delimiter
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
  function parse(&$text, &$tokens, $p = null) # {{{
  {
    # construct syntax tree
    $tree = [];# [0:<type>,1:<name>,2:<line>,3:<indent>,4:<text>,5:<children>]
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
        if (!($t = $this->parse($text, $tokens, $t))) {
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
        $p[4] = substr($text, $p[4], $t[4] - $p[4]);
        $p[5] = $tree;
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
            $this->renderFunc($delims, $t[4], $t[5], $depth),
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
  private $engine, $O, $delims, $stack;
  function __construct($engine, &$O, $delims, &$context)
  {
    $this->engine = $engine;
    $this->O      = $O;
    $this->delims = $delims;
    $this->stack  = [
      ($O['helpers'] ?: null),
      ($context ?: null),
    ];
  }
  function __toString() {
    return "\nTEMPLATE";# terminator guard
  }
  function __invoke($name)
  {
    # get value and invoke lambda
    if (is_callable($v = $this->v($name))) {
      $v = call_user_func($v, '');
    }
    # determine value type flags
    $s = is_string($v);
    $n = (!$s && is_numeric($v));
    $x = ($s && $this->O['recur'] &&
          strpos($v, $this->delims[0]) !== false &&
          strpos($v, $this->delims[1]) !== false);
    # complete
    return $x # handle recursive template
      ? $this->engine->render($v, $this->delims, $this->stack[1])
      : (($s || $n) ? $v : '');
  }
  function f($i, $name, $negate)
  {
    # invoke template function {{{
    # get value and handle negate logic
    if (!($v = $this->v($name)))
    {
      # negated value
      return $negate
        ? $this->O['funcs'][$i]($this)
        : '';
    }
    elseif ($negate)
    {
      # negated block (simplified lambda)
      return (is_callable($v) && !call_user_func($v, ''))
        ? $this->O['funcs'][$i]($this)
        : '';
    }
    elseif (is_callable($v))
    {
      # standard block lambda
      # search template key
      $j = reset($this->O[$k = 'templates']);
      while ($j !== $i) {
        $j = next($this->O[$k]);
      }
      # extract template text (remove key prefix)
      $j = strlen(implode('', $this->delims));
      $k = substr(key($this->O[$k]), $j);
      # invoke and check the result
      if (!($v = call_user_func($v, $k))) {
        return '';
      }
      # check block substitution
      if (is_string($v))
      {
        # check template recursion
        if ($this->O['recur'] &&
            strpos($v, $this->delims[0]) !== false &&
            strpos($v, $this->delims[1]) !== false)
        {
          $v = $this->engine->render($v, $this->delims, $this->stack[1]);
        }
        # replace contents
        return $v;
      }
    }
    # check non-expandable
    if (!($k = is_array($v)) && !is_object($v))
    {
      # iterate once
      array_push($this->stack, $v);
      $k = $this->O['funcs'][$i]($this);
      array_pop($this->stack);
      return $k;
    }
    # check iterable
    # array must have all keys numeric (assume all or nothing),
    # object must be of a special type
    if (($k && is_int(key($v))) ||
        (!$k && ($v instanceof Traversable)))
    {
      # implicit iterator
      $k = '';
      foreach ($v as $j)
      {
        array_push($this->stack, $j);
        $k .= $this->O['funcs'][$i]($this);
        array_pop($this->stack);
      }
    }
    else
    {
      # context expansion
      array_push($this->stack, $v);
      $k = $this->O['funcs'][$i]($this);
      array_pop($this->stack);
    }
    return $k;
    # }}}
  }
  private function v($name, &$stack = null)
  {
    # get context value {{{
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
