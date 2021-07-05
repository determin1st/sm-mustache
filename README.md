[![logo](https://raw.githack.com/determin1st/sm-mustache/master/tests/logo.jpg)](https://youtu.be/mQ_AdzWE5Ec)
[mustache](https://mustache.github.io/) templates **eval**uator ([prototype](https://github.com/bobthecow/mustache.php))

## requirements
- [PHP](https://www.php.net/) 7.4+

## tests (2021-06)
<details>
<summary>performance</summary>

test loops over mustache spec files (except lambdas), fails are skipped and counted.
[mustache.js](https://github.com/janl/mustache.js) fails in one test: [issue](https://github.com/janl/mustache.js/issues/65)
[![vs](https://raw.githack.com/determin1st/sm-mustache/master/tests/speed.jpg)](https://github.com/determin1st/sm-mustache#tests)
:point_up: PHPv7.4.5, NODEv10.14.2
[![vs2](https://raw.githack.com/determin1st/sm-mustache/master/tests/speed2.jpg)](https://github.com/determin1st/sm-mustache#tests)
:point_up: PHPv8.0.7 with OPcache and JIT enabled
---
</details>
<details>
<summary>specification</summary>

<https://github.com/mustache/spec>
[![comments](https://raw.githack.com/determin1st/sm-mustache/master/tests/comments.jpg)](https://github.com/determin1st/sm-mustache#tests)
[![interpolation](https://raw.githack.com/determin1st/sm-mustache/master/tests/interpolation.jpg)](https://github.com/determin1st/sm-mustache#tests)
:point_up: `{{{triple_stashes}}}` are not supported
[![inverted](https://raw.githack.com/determin1st/sm-mustache/master/tests/inverted.jpg)](https://github.com/determin1st/sm-mustache#tests)
[![lambdas](https://raw.githack.com/determin1st/sm-mustache/master/tests/lambdas.jpg)](https://github.com/determin1st/sm-mustache#tests)
:point_up: delimiter alternation in template is not supported,
the last one is [doubtful](https://github.com/mustache/spec/issues/128).
[![sections](https://raw.githack.com/determin1st/sm-mustache/master/tests/sections.jpg)](https://github.com/determin1st/sm-mustache#tests)
---
</details>
<details>
<summary>deviations</summary>

- no `<` template parent, inheritance.
- no `>` template partials, inheritance.
- no `=` template delimiters modifier. rendering with custom delimiters
  is possible but templates will not be cached. this implementaion
  assumes that custom delimiters are used for preparation renders.
- non-escaping by default, escaper function or a flag must be specified explicitly as an option.
- non-escaping with `{{{trippleStashes}}}` (when escaping enabled). this behaviour is set with `&` variable tag.
- template recursions by default are disabled.
  recursion occurs when a variable/block is rendered with a function
  which may return a new template string (contains current delimiters).
  may be enabled explicitly.
</details>

## syntax extentions
<details>
<summary>else section</summary>

Else sections `|` may be used inside both if `#` and if not `^` blocks:
```
{{#block}} yes {{|}} no {{/block}}
{{^block}} no {{|}} yes {{/block}}
```
block is `falsy` =>
```
 no 
 no 
```
block is `truthy` =>
```
 yes 
 yes 
```
</details>

## usage examples
<details>
<summary>include</summary>

```php
# dropped into <project_home>/inc/mustache.php
require_once __DIR__.DIRECTORY_SEPARATOR.'.inc.'.DIRECTORY_SEPARATOR.'mustache.php';
```
</details>
<details>
<summary>construct</summary>

```php
# defaults
$tp = new \SM\MustacheEngine([
  'delims'  => '{{ }}',
  'helpers' => null,  # context fallbacks array/object
  'logger'  => null,  # callable, for debug logs
  'escaper' => null,  # callable, variables escaper (or truthy for HTML escaping)
  'recur'   => false, # template recursion flag
]);

# same (defaults)
$tp = new \SM\MustacheEngine();

# mustache spec compatible
$mp = new \SM\MustacheEngine([
  'escaper' => true,  # htmlspecialchars($variable)
  'recur'   => true,  # checks function result for delimiters and re-renders
]);
```
</details>
<details>
<summary>render</summary>

```php
# ...
# ...
# ...
```
</details>


## call syntax
#### `$tp = new \SM\MustacheEngline($options);`
<details>
<summary>parameters</summary>

todo
</details>

#### `$tp->render($template, $context);`
#### `$tp->render($template, $delimiters, $context);`
<details>
<summary>parameters</summary>

todo
</details>










<details>
  <summary>later</summary>


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

# examples
## multipass
## helpers
</details>


