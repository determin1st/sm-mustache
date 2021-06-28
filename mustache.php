<?php
namespace SM;
class MustacheEngine {
  # BASE {{{
  const SPEC    = '1.1.2';# origin's
  const NAME_SZ = 32;# max {{name}} size (without delimiters/spacing)
  const DELIMS  = '{}[]()<>:%=~-_?*@!|';# valid delimiter chars
  private static
    $DELIMS_EXP = '/^([_]{2,4})(\s+)([_]{2,4})$/',
    $TAGS = [
      '#' => '#',# if
      '^' => '^',# if not
      #'|' => '|',# else
      '/' => '/',# fi
      '!' => '!',# comment
      '.' => '.',# iterator
      '&' => '_',# variable (tagged)
      # _ => variable (not tagged)
    ],
    $BLOCKS = '#^|/!',
    $TE = [# evaluated chunks of code
      'f' => '

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
    'delims'    => ['{{',' ','}}','{{ }}'],# default: opener,indent,closer,all
    'templates' => [],    # template=>[delims[3]=>index]
    'funcs'     => [],    # index=>function
    'helpers'   => null,  # context fallback array/object
    'logger'    => null,  # callable, for debug logs
    'escaper'   => null,  # callable, variables escaper (or truthy for HTML escaping)
    'recur'     => false, # templates recursion flag
  ];
  function __construct($o = [])
  {
    # assumed correct
    isset($o[$k = 'helpers']) && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'logger'])  && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'escaper']) && ($this->O[$k] = $o[$k]);
    isset($o[$k = 'recur'])   && ($this->O[$k] = $o[$k]);
    # initialize instance once
    if (!isset(self::$TE['i']))
    {
      self::$TE['i'] = '';
      self::$TE['f'] = str_replace("\r", "", trim(self::$TE['f']));
      self::$DELIMS_EXP = str_replace('_', preg_quote(self::DELIMS), self::$DELIMS_EXP);
    }
    # set instance delimiters
    if (isset($o[$k = 'delims']))
    {
      if (is_string($o[$k]) && preg_match(self::$DELIMS_EXP, $o[$k], $i)) {
        $this->O[$k] = $p1 = [$i[1],$i[2],$i[3],$o[$k]];
      }
      else {
        $this->log('incorrect delimiters: '.var_export($o[$k], true), 1);
      }
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
      # check non-strict (usage)
      if (!is_array($p1))
      {
        # check correct and extract
        $i = null;
        if (!is_string($p1) || !preg_match(self::$DELIMS_EXP, $p1, $i))
        {
          $this->log('incorrect delimiters: '.var_export($p1, true), 1);
          return $text;
        }
        # set strict
        $p1 = [$i[1],$i[2],$i[3],$p1];
      }
    }
    else
    {
      $this->log('incorrect usage, check the call syntax', 1);
      return $text;
    }
    # create template function
    if (($i = $this->renderFunc($p1, $text)) === -1) {
      return $text;
    }
    # execute within the context
    return $this->O['funcs'][$i](
      new MustacheContext($this, $this->O, $p1, $p2)
    );
  }
  # }}}
  private function renderFunc(&$delims, &$text, &$tree = null, $depth = -1) # {{{
  {
    # check cache
    $t = &$this->O['templates'];
    if (!isset($t[$text])) {
      $t[$text] = [];
    }
    # check cached
    $k = &$t[$text];
    if (isset($k[$delims[3]])) {
      return $k[$delims[3]];
    }
    # create parse tree
    if (!$tree)
    {
      $tree = $this->tokenize($delims, $text);
      if (!$tree = $this->parse($text, $tree)) {
        return -1;
      }
    }
    # create template renderer function
    $f = $this->compose($delims, $tree, ++$depth);
    $i = count($this->O['funcs']);# must be after composition
    $f = sprintf(self::$TE['f'], $i, $depth, $f);
    $this->O['funcs'][$i] = eval("return ($f);");
    $this->log($f, 0);
    # set cache and complete
    return $k[$delims[3]] = $i;
  }
  # }}}
  function tokenize(&$delims, &$text) # {{{
  {
    # prepare
    $tokens = [];# [<type>,<name>,<line>,<indent>,<index>]
    $size0  = strlen($delims[0]);
    $size1  = strlen($delims[2]);
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
      if (($a = strpos($text, $delims[2], $b)) === false ||
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
      # determine token tag
      $b = isset(self::$TAGS[$c[0]]) ?
        self::$TAGS[$c[0]] : '';
      # add token
      switch ($b) {
      case '_':
        # tagged variable
        $c = $c[0].ltrim(substr($c, 1));
        # fallthrough..
      case '':
        # variable
        $tokens[$j++] = ['_', $c, $line, $indent];
        break;
      case '!':
        # comment
        $tokens[$j++] = [$b, '', $line, $indent];
        break;
      case '/':
        # block end
        $c = ltrim(substr($c, 1));
        $tokens[$j++] = [$b, $c, $line, $indent, $i];
        break;
      default:
        # block start
        $c = ltrim(substr($c, 1));
        $tokens[$j++] = [$b, $c, $line, $indent, $a + $size1];
        break;
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
  function parse(&$text, &$tokens, &$p = null) # {{{
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
  private function compose(&$delims, &$tree, $depth) # {{{
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
  function __construct($engine, &$O, &$delims, &$context)
  {
    $this->engine = $engine;
    $this->O      = &$O;
    $this->delims = &$delims;
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
    # variable {{{
    # get tag and strip it from the name
    ($tag = ($name[0] === '&')) && ($name = substr($name, 1));
    # checkout the value
    if (!($v = $this->v($name))) {
      return '';
    }
    # invoke lambda
    if ($isFunc = is_callable($v))
    {
      $v = ($v instanceof MustacheWrap)
        ? $v('') : call_user_func($v, '');
    }
    # check proper type
    if (is_string($v))
    {
      # check template recursion
      if ($isFunc && $this->O['recur'] &&
          strpos($v, $this->delims[0]) !== false &&
          strpos($v, $this->delims[2]) !== false)
      {
        # recurse
        $v = $this->engine->render($v, $this->delims, $this->stack[1]);
      }
      elseif (!$tag && ($f = $this->O['escaper']))
      {
        # escape characters
        $v = is_callable($f) ? $f($v) : htmlspecialchars($v);
      }
      return $v;
    }
    elseif (is_numeric($v)) {
      return "$v";
    }
    return '';
    # }}}
  }
  function f($i, $name, $inverted)
  {
    # block {{{
    # checkout the value
    if (!($v = $this->v($name)))
    {
      # handle falsy
      return $inverted
        ? $this->O['funcs'][$i]($this)
        : '';
    }
    elseif (is_callable($v))
    {
      # handle lambda block
      # determine raw block contents
      $t = &$this->O['templates'];
      $a = $this->delims[3];
      $b = reset($t);
      while (!isset($b[$a]) || $b[$a] !== $i) {
        $b = next($t);
      }
      $a = key($t);
      # invoke
      $v = ($v instanceof MustacheWrap)
        ? $v($a) # wrapped object method
        : call_user_func($v, $a); # true lambda
      # check falsy
      if (!$v)
      {
        return $inverted
          ? $this->O['funcs'][$i]($this)
          : '';
      }
      # check inverted block
      if ($inverted) {
        return '';
      }
      # check block substitution
      if (is_string($v))
      {
        # check template recursion
        if ($this->O['recur'] &&
            strpos($v, $this->delims[0]) !== false &&
            strpos($v, $this->delims[2]) !== false)
        {
          $v = $this->engine->render($v, $this->delims, $this->stack[1]);
        }
        # replace contents
        return $v;
      }
      # proceed to expansion..
    }
    # handle inverted
    if ($inverted) {
      return '';
    }
    # handle standard block
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
        $this->stack[] = $j;
        $k .= $this->O['funcs'][$i]($this);
        array_pop($this->stack);
      }
    }
    else
    {
      # context expansion
      $this->stack[] = $v;
      $k = $this->O['funcs'][$i]($this);
      array_pop($this->stack);
    }
    return $k;
    # }}}
  }
  private function v($name)
  {
    # name resolution {{{
    # resolve implicit iterator
    if ($name === '.') {
      return end($this->stack);
    }
    # prepare
    if (strpos($name, '.') === false) {
      $dots = null;
    }
    else
    {
      $dots = explode('.', $name);
      $name = array_shift($dots);
    }
    # resolve first name
    # iterate stack backwards
    for ($v = '', $i = count($this->stack) - 1; $i >= 0; --$i)
    {
      # checkout truthy frame
      if ($x = &$this->stack[$i])
      {
        # check array or object
        if (is_array($x))
        {
          # check property
          if (array_key_exists($name, $x))
          {
            $v = &$x[$name];
            break;
          }
        }
        elseif (is_object($x))
        {
          # check property
          if (isset($x->$name))
          {
            $v = &$x->$name;
            break;
          }
          # check method
          if (method_exists($x, $name))
          {
            # traversal through methods is not supported, so,
            # this must be the last name, wrap otherwise
            return $dots
              ? '' : new MustacheWrap($x, $name);
          }
        }
      }
    }
    # check non-resolved or nothing more to resolve
    if (!$v || !$dots) {
      return $v;
    }
    # resolve dot notation
    # dive into value (til the last name)
    $name = array_pop($dots);
    foreach ($dots as $i)
    {
      # array or object property must be set
      if (is_array($v))
      {
        if (isset($v[$i]))
        {
          $v = &$v[$i];
          continue;
        }
      }
      elseif (is_object($v))
      {
        if (isset($v->$name))
        {
          $v = &$v->$name;
          continue;
        }
      }
      return '';
    }
    # resolve the last name
    if (is_array($v)) {
      return isset($v[$name]) ? $v[$name] : '';
    }
    if (is_object($v))
    {
      # must be property or method
      if (isset($v->$name)) {
        return $v->$name;
      }
      if (method_exists($name, $v)) {
        return new MustacheWrap($v, $name);
      }
    }
    return '';
    # }}}
  }
}
# }}}
class MustacheWrap # {{{
{
  private $o, $m;
  function __construct($o, $m) {
    $this->o = $o; $this->m = $m;
  }
  function __invoke($a) {
    return $this->o[$m]($a);
  }
}
# }}}
?>
