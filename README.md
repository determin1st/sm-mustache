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
:point_up: PHPv8.0.7 +JIT, NODEv16.5.0
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
<summary>if</summary>

if block is rendered when block value is truthy
```
{{#block}} truthy {{/block}}
```
</details>
<details>
<summary>if-not</summary>

if-not block is rendered when block value is falsy
```
{{^block}} falsy {{/block}}
```
</details>
<details>
<summary>if-else</summary>

if-else block has two sections, one is always rendered
```
{{#block}} truthy {{|}} falsy {{/block}}
```
</details>
<details>
<summary>if-not-else</summary>

if-not-else block has two sections, one is always rendered
```
{{^block}} falsy {{|}} truthy {{/block}}
```
</details>
<details>
<summary>switch</summary>

switch block is similar to if/if-else block.
only one section may be rendered.
```
  {{#block}}
    truthy section (default)
  {{|0}}
    zero (string)
  {{|1}}
    one (string/number)
  {{|2}}
    two (string/number)
  {{|}}
    falsy section
  {{/block}}
```
</details>
<details>
<summary>switch-not</summary>

switch-not block is similar to if-not block.
only one section may be rendered.
it is more natural than switch block because default section is not the first one.
```
  {{^block}}
    falsy section
  {{|0}}
    zero (string)
  {{|1}}
    one (string/number)
  {{|2}}
    two (string/number)
  {{|}}
    truthy section (default)
  {{/block}}
```
</details>

## usage
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
  'helper'  => null,  # object/array, context fallback
  'escaper' => false, # callable, variable escaper (or truthy for HTML escaping)
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


## call spec
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
## helper
</details>


