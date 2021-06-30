<?php
namespace SM;
class MustacheEngine {
  ### {{{
  const SPEC    = '1.1.2';# origin's
  const NAME_SZ = 32;# max {{name}} size (without delimiters/spacing)
  const T_MAX   = 1000;# max number of cached templates
  const DELIMS  = '{}[]()<>:%=~-_?*@!|';# valid delimiter chars
  private static
    $DELIMS_EXP = '/^([_]{2,4})(\s+)([_]{2,4})$/',
    # evaluated chunks of code
    $E_READY    = false,# initialized
    $E_IF       = '{$x->f(%s,%s,0)}',
    $E_IF_NOT   = '{$x->f(%s,%s,1)}',
    $E_VAR      = '{$x(%s)}',
    $E_TEMPLATE = '

function($x) { #%s,depth=%s
return <<<TEMPLATE
%s
TEMPLATE;
}

    ';
  ###
  public $delims,$helpers,$logger,$escaper,$recur;# config
  public $cache,$hash,$text,$func,$total;# data
  function __construct($o = [])
  {
    # initialize config (assumed correct)
    isset($o[$k = 'helpers']) && ($this->helpers = $o[$k]);
    isset($o[$k = 'logger'])  && ($this->logger  = $o[$k]);
    isset($o[$k = 'escaper']) && ($this->escaper = $o[$k]);
    isset($o[$k = 'recur'])   && ($this->recur   = $o[$k]);
    # set delimiters
    $i = ['{{',' ','}}'];# default
    if (isset($o[$k = 'delims']))
    {
      if (is_string($o[$k]) && preg_match(self::$DELIMS_EXP, $o[$k], $i)) {
        $i = [$i[1],$i[2],$i[3]];
      }
      else {
        $this->log('incorrect delimiters: '.var_export($o[$k], true), 1);
      }
    }
    $this->delims = \SplFixedArray::fromArray($i);
    # initialize data
    $this->cache = new \SplFixedArray(65536);# root (~4mb)
    $this->hash  = new \SplFixedArray(self::T_MAX);
    $this->text  = new \SplFixedArray(self::T_MAX);
    $this->func  = new \SplFixedArray(self::T_MAX);
    $this->total = 0;
    # initialize statics once
    if (!self::$E_READY)
    {
      self::$E_READY = true;
      self::$E_TEMPLATE = str_replace("\r", "", trim(self::$E_TEMPLATE));
      self::$DELIMS_EXP = str_replace('_', preg_quote(self::DELIMS), self::$DELIMS_EXP);
    }
  }
  function log($text, $level = 0) {
    ($log = $this->logger) && $log($text, $level);
  }
  # }}}
  function render(&$text, $p1 = null, $p2 = null) # {{{
  {
    # check parameters
    if (!$text || !is_string($text)) {
      return $text;
    }
    if ($p2 === null)# context only
    {
      $p2 = $p1;
      $p1 = $this->delims;
      $p0 = false;
    }
    elseif ($p1)# context and delimiters
    {
      # check custom
      if ($p0 = ($p1 !== $this->delims))
      {
        # check correct and extract
        $i = null;
        if (!is_string($p1) || !preg_match(self::$DELIMS_EXP, $p1, $i))
        {
          $this->log('incorrect delimiters: '.var_export($p1, true), 1);
          return $text;
        }
        # set strict
        $p1 = \SplFixedArray::fromArray([$i[1],$i[2],$i[3]]);
      }
    }
    else
    {
      $this->log('missing arguments', 1);
      return $text;
    }
    # check current total
    if (($n = $this->total) >= self::T_MAX)
    {
      $this->log('cache overflow', 1);
      return $text;
    }
    # create template function and
    # execute it within the context
    $i = $this->renderFunc($p1, $text);
    $x = ~$i ? $this->func[$i](new MustacheContext($this, $p1, $p2)) : '';
    # check cache updated
    if ($p0 || $i === -1)
    {
      # cleanup
      for ($i = $this->total - 1; $i >= $n; --$i)
      {
        $this->text[$i] = null;
        $this->func[$i] = null;
        $this->hash[$i] = null;
      }
      $this->total = $n;
    }
    # complete
    return $x;
  }
  # }}}
  function renderFunc($delims, &$text, &$tree = null, $depth = -1) # {{{
  {
    # check delimiters are default
    if ($delims === $this->delims)
    {
      # compute hash and checkout cache
      $k = hash('md4', $text, true);
      if (($i = $this->cacheGet($k)) !== null) {
        return $i;
      }
    }
    else {# not cached
      $k = null;
      echo "\nNO CACHE!!\n";
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
    $i = $this->total;# must go after composition
    $f = sprintf(self::$E_TEMPLATE, $i, $depth, $f);
    $this->func[$i] = eval("return ($f);");
    $this->text[$i] = $text;
    $this->hash[$i] = $k;
    $this->total    = $i + 1;
    $this->log($f, 0);
    # complete
    return (!$k || $this->cacheSet($k, $i)) ? $i : -1;
  }
  # }}}
  function cacheGet($k) # {{{
  {
    # determine root index
    $y = (ord($k[1]) << 8) + ord($k[0]);
    $x = $this->cache[$y];
    # lookup
    for ($i = 2; $i < 16; ++$i)
    {
      if ($x === null || is_int($x)) {
        break;
      }
      $x = $x[ord($k[$i])];
    }
    return $x;
  }
  # }}}
  function cacheSet($k, $ki) # {{{
  {
    # determine root index
    $z = $this->cache;
    $y = (ord($k[1]) << 8) + ord($k[0]);
    $x = $z[$y];
    # lookup
    for ($i = 2; $i < 16; ++$i)
    {
      if ($x === null)# free place
      {
        $z[$y] = $ki;
        return true;
      }
      if (is_int($x))# hold
      {
        # replace invalid
        if ($x >= $this->total)
        {
          $z[$y] = $ki;
          return true;
        }
        # allocate new bucket
        $b = new \SplFixedArray(256);
        # put holder to a new position
        $a = $this->hash[$x];
        $b[ord($a[$i])] = $x;
        # replace holder with the bucket
        $z[$y] = $b;
      }
      # traverse to the next bucket
      $z = $z[$y];
      $y = ord($k[$i]);
      $x = $z[$y];
    }
    $this->log('hash collision', 1);
    return false;
  }
  # }}}
  function tokenize($delims, &$text) # {{{
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
          (!ctype_alnum($c[0]) && strpos('#^/.&!', $c[0]) === false) ||
          (strlen($c) > self::NAME_SZ && $c[0] !== '!'))
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
      # add token
      switch ($c[0]) {
      case '#':
      case '^':
        # block start
        $tokens[$j++] = [$c[0], ltrim(substr($c, 1)), $line, $indent, $a + $size1];
        break;
      case '/':
        # block end
        $tokens[$j++] = ['/', ltrim(substr($c, 1)), $line, $indent, $i];
        break;
      case '!':
        # comment
        $tokens[$j++] = ['!', '', $line, $indent];
        break;
      case '&':
        # tagged variable
        $c = $c[0].ltrim(substr($c, 1));
        # fallthrough..
      default:
        # variable
        $tokens[$j++] = ['_', $c, $line, $indent];
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
      if (($a = $tokens[$i][0]) && strpos('#^/!', $a) !== false) {
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
  function compose($delims, &$tree, $depth) # {{{
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
        $code .= sprintf(self::$E_VAR, "'".$t[1]."'");
        break;
      case '#':
      case '^':
        # block
        if ($t[4])
        {
          $code .= sprintf(
            ($t[0] === '#' ? self::$E_IF : self::$E_IF_NOT),
            $this->renderFunc($delims, $t[4], $t[5], $depth),
            "'".$t[1]."'"
          );
        }
        break;
      case '.':
        # iterator
        $code .= sprintf(self::$E_VAR, "'.".$t[1]."'");
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
  private $engine, $delims, $stack, $last;
  function __construct($engine, $delims, &$context)
  {
    $this->engine = $engine;
    $this->delims = $delims;
    $this->stack  = [null,null];
    $this->last   = 1;
    if ($engine->helpers) {
      $this->stack[0] = &$engine->helpers;
    }
    if ($context) {
      $this->stack[1] = &$context;
    }
  }
  function __toString() {
    return "\nTEMPLATE";# terminator guard
  }
  function __invoke($name)
  {
    # variable {{{
    # strip name tag
    ($tag = ($name[0] === '&')) && ($name = substr($name, 1));
    # resolve name to value
    if (!($v = $this->v($name)) && $v !== 0) {
      return '';# falsy, but not 0
    }
    # invoke function
    if ($isFunc = is_callable($v)) {
      $v = ($v instanceof MustacheWrap) ? $v('') : call_user_func($v, '');
    }
    # check proper type
    if (is_string($v))
    {
      # check template recursion
      if ($isFunc && $this->engine->recur &&
          strpos($v, $this->delims[0]) !== false &&
          strpos($v, $this->delims[2]) !== false)
      {
        # recurse
        $i = $this->engine->renderFunc($this->delims, $v);
        $v = ~$i ? $this->engine->func[$i]($this) : '';
      }
      elseif (!$tag && ($f = $this->engine->escaper))
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
    # resolve name to value
    if (!($v = $this->v($name)))
    {
      # handle falsy (not found or empty string/array, 0, null, false)
      return $inverted
        ? $this->engine->func[$i]($this)
        : '';
    }
    /***
    elseif ($inverted) # LAMBDAS IMPLEMENTED!?
    {
      return '';
    }
    /***/
    elseif (is_callable($v))
    {
      # handle lambda block
      # get raw block contents and invoke function
      $x = $this->engine->text[$i];
      $v = ($v instanceof MustacheWrap)
        ? $v($x) # wrapped object method
        : call_user_func($v, $x); # callable
      # check result type
      if (!$v)
      {
        # handle falsy
        return $inverted
          ? $this->engine->func[$i]($this)
          : '';
      }
      elseif ($inverted) {
        return '';# handle truthy inverted
      }
      elseif (is_string($v))
      {
        # handle content substitution
        if ($this->engine->recur &&
            strpos($v, $this->delims[0]) !== false &&
            strpos($v, $this->delims[2]) !== false)
        {
          # recurse
          $j = $this->engine->renderFunc($this->delims, $v);
          $v = ~$j ? $this->engine->func[$j]($this) : '';
        }
        return $v;
      }
      # fallthrough..
    }
    elseif ($inverted) {
      return '';# handle truthy inverted
    }
    # handle standard block
    # check iterable
    # - array must have all keys numeric (assumed all or none)
    # - object must be traversable
    if ((($x = is_array($v)) && is_int(key($v))) ||
        (!$x && is_object($v) && ($v instanceof Traversable)))
    {
      # implicit iterator
      $x = '';
      foreach ($v as &$w)
      {
        $this->stack[++$this->last] = &$w;
        $x .= $this->engine->func[$i]($this);
        $this->last--;
      }
    }
    else
    {
      # context expansion
      $this->stack[++$this->last] = &$v;
      $x = $this->engine->func[$i]($this);
      $this->last--;
    }
    # done
    return $x;
    # }}}
  }
  private function v($name)
  {
    # name resolution {{{
    # resolve implicit iterator
    if ($name === '.') {
      return $this->stack[$this->last];
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
    # resolve the first name
    # iterate stack backwards
    for ($v = '', $i = $this->last; $i >= 0; --$i)
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
            # wrap the last name's function and complete
            if (!$dots) {
              return new MustacheWrap($x, $name);
            }
            # otherwise, use the call result for the further traversal
            $v = &$x->$name();
            break;
          }
        }
      }
    }
    # check non-resolved or nothing more to resolve
    if (!$v || !$dots) {
      return $v;
    }
    # resolve dot notation
    # traverse the value (til the last name)
    $name = array_pop($dots);
    foreach ($dots as $i)
    {
      if (is_array($v))
      {
        # property must be set (may be callbable)
        if (isset($v[$i]))
        {
          if (is_callable($v[$i])) {
            $v = &$v[$i]();
          }
          else {
            $v = &$v[$i];
          }
          continue;
        }
      }
      elseif (is_object($v))
      {
        # property must be set, method must be called otherwise
        if (isset($v->$name))
        {
          $v = &$v->$name;
          continue;
        }
        elseif (method_exists($name, $v))
        {
          $v = &$v->$name();
          continue;
        }
      }
      return '';# traverse failed
    }
    # resolve the last name (array property/function or object property/method)
    if (is_array($v)) {
      return isset($v[$name]) ? $v[$name] : '';
    }
    if (is_object($v))
    {
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
  function __construct($o, $m) { $this->o = $o; $this->m = $m; }
  function __invoke($a) { return $this->o[$this->m]($a); }
}
# }}}
?>
