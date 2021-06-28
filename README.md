[![logo](https://raw.githack.com/determin1st/sm-mustache/master/tests/logo.jpg)](https://youtu.be/mQ_AdzWE5Ec)
personal [mustache](https://mustache.github.io/) templates **eval**uator
<details>
  <summary>history</summary>

[The origin](https://github.com/bobthecow/mustache.php)
was reduced, monolithized and namespaced.. a total, individual rewrite from ~`130`kb to ~`20`kb.
#### reduced (removed)
- `=`, template delimiters modifier.
- `<`, template parent, inheritance.
- `>`, template partials, inheritance.
- pragmas (not in spec).
- escaping with `{{{trippleStash}}}`.
- escaping by default (specified explicitly).
- template recursions by default (specified explicitly).
- exceptions/breaks.
- strict callables.
- logger object => function.
- helpers object => array.
- camel/snake case mixture => camel case.
- filesystem template loaders (strings only, [UTF-8](https://en.wikipedia.org/wiki/UTF-8) is assumed).
- filesystem cache (memory cache only).
- `md5()` hash calculations.
- `mbstring.func_overload` guard (deprecated in new PHPs).
- PHPDoc.
#### monolithized
- helper classes unified into a single engine class.
- template classes converted into anonymous render functions (heredoc).
- rendering short-circuited (recursion instead of repetition).
- accumulation of lines instead of characters in tokenizer.
#### namespaced
- `SM`
</details>

## requirements
- [PHP](https://www.php.net/) 7.4+

## tests (2021-06)
<details>
<summary>performance</summary>

test loops over mustache spec files (except lambdas), fails are skipped and counted.
[mustache.js](https://github.com/janl/mustache.js) fails in one test: [issue](https://github.com/janl/mustache.js/issues/65)
[![vs](https://raw.githack.com/determin1st/sm-mustache/master/tests/speed.jpg)](https://github.com/determin1st/sm-mustache#tests)
PHPv7.4.5, NODEv10.14.2
---
</details>
<details>
<summary>specification</summary>

<https://github.com/mustache/spec>
[![comments](https://raw.githack.com/determin1st/sm-mustache/master/tests/comments.jpg)](https://github.com/determin1st/sm-mustache#tests)
fails below: `{{{triple_stashes}}}` are not supported.
[![interpolation](https://raw.githack.com/determin1st/sm-mustache/master/tests/interpolation.jpg)](https://github.com/determin1st/sm-mustache#tests)
[![inverted](https://raw.githack.com/determin1st/sm-mustache/master/tests/inverted.jpg)](https://github.com/determin1st/sm-mustache#tests)
fails below: delimiter alternation is not supported.
the last one is [doubtful](https://github.com/mustache/spec/issues/128#issuecomment-868940293).
[![lambdas](https://raw.githack.com/determin1st/sm-mustache/master/tests/lambdas.jpg)](https://github.com/determin1st/sm-mustache#tests)
[![sections](https://raw.githack.com/determin1st/sm-mustache/master/tests/sections.jpg)](https://github.com/determin1st/sm-mustache#tests)
---
</details>


## usage
#### include
```php
# dropped into <project_home>/inc/mustache.php
require_once __DIR__.DIRECTORY_SEPARATOR.'.inc.'.DIRECTORY_SEPARATOR.'mustache.php';
```
#### construct
```php
# defaults
$tp = new \SM\MustacheEngine([
  'delims'  => '{{ }}',
  'helpers' => null,  # context fallbacks array/object
  'logger'  => null,  # callable, for debug logs
  'escaper' => null,  # callable, variables escaper (or truthy for HTML escaping)
  'recur'   => false, # templates recursion flag
]);

# same
$tp = new \SM\MustacheEngine();

# mustache spec compatible
$mp = new \SM\MustacheEngine([
  'escaper' => true,  # htmlspecialchars($variable)
  'recur'   => true,  # check lambda result for delimiters and re-render
]);
```
#### render
```php
# ...
```




<details>
  <summary>todo</summary>

# syntax extentions
## else block
## block operators `==`, `>`, `<`, `>=`, `<=`
## block reindentation

# syntax
## delimiters
a pair of markers around constructs, for example `{{` and `}}`.
minimal size of a marker is 2 characters, maximal is 4.
the pair sizes may differ, for example `<!--` and `-->` are valid delimiters.
## variables
a name inside delimiters identify a variable, for example `{{name}}`.
a variable will be substituted by name with the specified data.
surrounding spaces are ignored so, `{{ name }}` is also valid.
the name of variable must be alpha-numeric, like `{{1}}`, `{{name}}`, `{{name1}}` or `{{1name}}`.
the exception is a variables chain `{{item.1.has.name}}` (called dot notation in the origin).
## block
## inverted block
## lambdas


# examples
## multipass
```php
[
  'en' => [
    'title' => '{:point_up:} multi-language templates with emojis',
    'text'  => '
    {{question_text}} {:question_symbol:}
    {{#answers}}
      {{#chosen}}
        {:white_small_square:} {{answer_text}}
      {{|}}
        {:black_small_square:} {{answer_text}}
      {{/chosen}}
    {{/answers}}
    ',
  ],
  # other languages...
]
```
## motd
</details>


