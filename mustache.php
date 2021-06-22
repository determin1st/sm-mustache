<?php
namespace SM;
class MustacheEngine {
  # BASE {{{
  const SPEC   = '1.1.2';
  const PREFIX = '__mustache_';
  public
    $templates = [],
    $funcs     = [],
    $delims    = '{{ }}',
    $helpers   = null,
    $logger    = null;
  private static $TE = [
    # evaluated chunks of code
    'FUNC' =>
    '

return (function($x) { #%s,depth=%s
  return <<<TEMPLATE
%s
TEMPLATE;
});

    ',
    'SECTION'  => '{$x->section(%s,%s)}',
    'VARIABLE' => '{$x->value(%s)}',
    'INVERTED_SECTION' => 'if (!self::%s) {%s}',
  ];
  public function __construct($o = [])
  {
    isset($o['delims'])  && ($this->delims  = $o['delims']);
    isset($o['helpers']) && ($this->helpers = $o['helpers']);
    isset($o['logger'])  && ($this->logger  = $o['logger']);
    isset($o['debug'])   && ($this->debug   = $o['debug']);
    self::$TE['FUNC'] = str_replace("\r", "", trim(self::$TE['FUNC']));
  }
  private function log($text, $level = 0) {
    ($log = $this->logger) && $log($text, $level);
  }
  # }}}
  public function render($text, $context = [], $delims = null) # {{{
  {
    if ($text)
    {
      $delims && ($this->delims = $delims);
      $text = ~($i = $this->renderFunc($text))
        ? $this->funcs[$i](new MustacheContext($this, $context))
        : '';
    }
    return $text;
  }
  # }}}
  private function renderFunc($text, &$tree = null, $depth = -1) # {{{
  {
    # check recursion
    if (!$text) {
      $text = $k = $this->compose($tree, ++$depth);
    }
    else {# first call
      $k = $this->delims.$text;
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
        $tree = $this->tokenize($text, $this->delims);
        $this->clearStandaloneSections($tree);
        $tree = $this->parse($tree);
      }
      # create renderer function code
      $text = $this->compose($tree, ++$depth);
      $i    = count($this->funcs);
      $text = sprintf(self::$TE['FUNC'], $i, $depth, $text);
      # evaluate and store
      $this->templates[$k] = $i;
      $this->funcs[$i] = eval($text);
      $this->log($text);
    }
    # complete
    return $i;
  }
  # }}}
  # TODO: TOKENIZER {{{
  # token types
  const T_SECTION     = '#';
  const T_INVERTED    = '^';
  const T_SECTION_END = '/';
  const T_VAR         = '_v';
  const T_TEXT        = '_t';
  private static $tagTypes = [
    self::T_SECTION     => true,
    self::T_INVERTED    => true,
    self::T_SECTION_END => true,
    self::T_VAR         => true,
  ];
  # state
  const IN_TEXT     = 0;
  const IN_TAG_TYPE = 1;
  const IN_TAG      = 2;
  public
    $state,
    $tagType,
    $buffer,
    $tokens,
    $seenTag,
    $line,
    $otag,
    $otagChar,
    $otagLen,
    $ctag,
    $ctagChar,
    $ctagLen;
  private function tokenize($text, $delims)
  {
    ###
    # Setting mbstring.func_overload makes things *really* slow.
    # Let's do everyone a favor and scan this string as ASCII instead.
    # @codeCoverageIgnoreStart
    $encoding = null;
    if (function_exists('mb_internal_encoding') &&
        ini_get('mbstring.func_overload') & 2)
    {
      $encoding = mb_internal_encoding();
      mb_internal_encoding('ASCII');
    }
    # @codeCoverageIgnoreEnd
    ###
    # prepare
    $this->state    = self::IN_TEXT;
    $this->tagType  = null;
    $this->buffer   = '';
    $this->tokens   = array();
    $this->seenTag  = false;
    $this->line     = 0;
    $this->otag     = '{{';
    $this->otagChar = '{';
    $this->otagLen  = 2;
    $this->ctag     = '}}';
    $this->ctagChar = '}';
    $this->ctagLen  = 2;
    # set delimiters
    if (!preg_match('/^\s*(\S+)\s+(\S+)\s*$/', $delims, $matches)) {
      throw new \Exception('incorrect delimiters: '.$delims);
    }
    list($_, $otag, $ctag) = $matches;
    $this->otag     = $otag;
    $this->otagChar = $otag[0];
    $this->otagLen  = strlen($otag);
    $this->ctag     = $ctag;
    $this->ctagChar = $ctag[0];
    $this->ctagLen  = strlen($ctag);
    ###
    ###
    $len = strlen($text);
    for ($i = 0; $i < $len; ++$i)
    {
      switch ($this->state) {
      case self::IN_TEXT:
        $char = $text[$i];
        // Test whether it's time to change tags.
        if ($char === $this->otagChar && substr($text, $i, $this->otagLen) === $this->otag)
        {
          $i--;
          $this->flushBuffer();
          $this->state = self::IN_TAG_TYPE;
        }
        else
        {
          $this->buffer .= $char;
          if ($char === "\n")
          {
            $this->flushBuffer();
            $this->line++;
          }
        }
        break;
      case self::IN_TAG_TYPE:
        $i += $this->otagLen - 1;
        $char = $text[$i + 1];
        if (isset(self::$tagTypes[$char]))
        {
          $tag = $char;
          $this->tagType = $tag;
        }
        else
        {
          $tag = null;
          $this->tagType = self::T_VAR;
        }
        if ($tag !== null) {
          $i++;
        }
        $this->state = self::IN_TAG;
        $this->seenTag = $i;
        break;
      default:
        $char = $text[$i];
        // Test whether it's time to change tags.
        if ($char === $this->ctagChar && substr($text, $i, $this->ctagLen) === $this->ctag)
        {
          $token = [
            'TYPE'  => $this->tagType,
            'NAME'  => trim($this->buffer),
            'OTAG'  => $this->otag,
            'CTAG'  => $this->ctag,
            'LINE'  => $this->line,
            'INDEX' => (($this->tagType === self::T_SECTION_END)
              ? $this->seenTag - $this->otagLen
              : $i + $this->ctagLen),
          ];
          $this->buffer = '';
          $i += $this->ctagLen - 1;
          $this->state = self::IN_TEXT;
          $this->tokens[] = $token;
        }
        else {
          $this->buffer .= $char;
        }
        break;
      }
    }
    $this->flushBuffer();
    ###
    # Restore the user's encoding...
    # @codeCoverageIgnoreStart
    if ($encoding) {
        mb_internal_encoding($encoding);
    }
    # @codeCoverageIgnoreEnd
    ###
    return $this->tokens;
  }
  private function flushBuffer()
  {
    if (strlen($this->buffer) > 0)
    {
      $this->tokens[] = [
        'TYPE'  => self::T_TEXT,
        'LINE'  => $this->line,
        'VALUE' => $this->buffer,
      ];
      $this->buffer = '';
    }
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
                ctype_space($tokens[$j]['VALUE']))
            {
              $tokens[$j]['VALUE'] = '';
            }
            elseif ($a === $b && $tokens[$i]['TYPE'] === self::T_TEXT &&
                    ctype_space($tokens[$i]['VALUE']))
            {
              $tokens[$i]['VALUE'] = '';
            }
          }
          else
          {
            # check first and last are whitespace
            if ($tokens[$i]['TYPE'] === self::T_TEXT &&
                $tokens[$j]['TYPE'] === self::T_TEXT &&
                ctype_space($tokens[$i]['VALUE']) &&
                ctype_space($tokens[$j]['VALUE']))
            {
              $tokens[$i]['VALUE'] = $tokens[$j]['VALUE'] = '';
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
        $p['END']   = $t['INDEX'];
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
        $code .= $node['VALUE'];
        break;
      default:
        throw new \Exception('unknown token: '.var_export($node, true));
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
  public function section($name, $i)
  {
    # {{{
    # prepare
    if (!($v = $this->get($name))) {
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
  public function value($name)
  {
    # {{{
    $v = $this->get($name);
    return is_callable($v)
      ? call_user_func($v, '')
      : $v;
    # }}}
  }
  private function get($name, &$stack = null)
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
      if (!($v = $this->get($name, $stack))) {
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
