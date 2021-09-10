[![logo](https://raw.githack.com/determin1st/sm-mustache/master/tests/logo.jpg)](https://youtu.be/mQ_AdzWE5Ec)
[mustache](https://mustache.github.io/) templates **eval**uator ([prototype](https://github.com/bobthecow/mustache.php))

## requirements
- [PHP](https://www.php.net/) 8+

## tests (2021-06)
<details>
<summary>performance</summary>

test loops over mustache spec files (except lambdas), fails are skipped and counted.
[mustache.js](https://github.com/janl/mustache.js) fails in one test: [issue](https://github.com/janl/mustache.js/issues/65)
[![vs](https://raw.githack.com/determin1st/sm-mustache/master/tests/speed.jpg)](https://github.com/determin1st/sm-mustache#tests)
:point_up: PHPv8.0.7 with JIT, NODEv10.14.2
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

## syntax
<details>
<summary>spec deviations</summary>

- no `<` template parent, inheritance.
- no `>` template partials, inheritance.
- no `=` template delimiters modifier.
  rendering with non-instance delimiters is possible
  but rendered templates will not be cached, assuming,
  custom delimiters are used for preparations.
- non-escaping by default, escaper function or a flag must be specified explicitly.
- no `{{{trippleStashes}}}`, this may be set with `&` variable tag.
- template recursions are disabled by default.
</details>
<details>
<summary>else |</summary>

Else sections `|` may be used inside both if `#` and if not `^` blocks:
```
{{#block}} yes {{|}} no {{/block}}
{{^block}} no {{|}} yes {{/block}}
```
block resolves `falsy`:
```
 no 
 no 
```
block resolves `truthy`:
```
 yes 
 yes 
```
</details>

## examples
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
$tp = \SM\Mustache::construct([
  'delims'  => '{{ }}',
  'logger'  => null,  # callable, for debug logs
  'helpers' => null,  # context fallbacks array/object
  'escaper' => false, # callable, variables escaper (or truthy for HTML escaping)
  'recur'   => false, # template recursion flag
]);

# same (defaults)
$tp = \SM\Mustache::construct();

# mustache spec compatible
$mp = \SM\Mustache::construct([
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
#### `$tp = \SM\Mustache::construct($options);`
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


