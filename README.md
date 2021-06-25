[![logo](https://raw.githack.com/determin1st/sm-mustache/master/logo.jpg)](https://youtu.be/BNAS2L1dSs8)
Personal [mustache](https://mustache.github.io/) template **eval**uator.

<details>
  <summary>details</summary>
  ---

  [The origin](https://github.com/bobthecow/mustache.php)
  was reduced, monolithized and namespaced.. a total, individual rewrite.
  #### reduced (removed)
  - `=`, section delimiters modifier.
  - `<`, template parent, inheritance.
  - `>`, template partials, inheritance.
  - `{{{trippleStashes}}}` and charset option.
  - escaping by default (specify explicitly).
  - template recursion by default (specify explicitly).
  - strict callables option.
  - logger object => function (callable).
  - helpers object => array.
  - camel/snake case mixture => camel case.
  - exceptions/breaks (set logger to debug).
  - filesystem template loaders (strings only, [UTF-8](https://en.wikipedia.org/wiki/UTF-8) is assumed).
  - filesystem cache (memory cache only).
  - pragmas, may make templates smaller but more cryptic (not in the specs anyway).
  - `md5()` hash calculations.
  - `mbstring.func_overload` guard (deprecated in new PHPs).
  - PHPDoc (i trust myself).
  #### monolithized
  - helper classes unified into a single engine class.
  - template classes converted into anonymous render functions (heredoc).
  - rendering short-circuited (recursion instead of repetition).
  - accumulation of lines instead of characters in tokenizer.
  #### namespaced
  - `SM`
  #### vs origin
  - size: ~`15`kb vs ~`130`kb
  - speed: ...
</details>

# requirements
- [PHP](https://www.php.net/) 7.4+

# spec tests
image here

# speed tests
image here


<details>
  <summary>todo</summary>

# syntax extentions


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
outer `^`
```
{{#section}}
  truthy
{{/section}}
{{^section}}
  falsy
{{/section}}
```
inner `|`
```
{{#section}}
  truthy
{{|}}
  falsy
{{/section}}
```
## lambdas
## indent trimming


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

<details>
  <summary>todo</summary>

  - speedtest
  - test lambdas
  - `|` else block.
  - block operators `==`, `>`, `<`, `>=`, `<=`.
  - content re-indenting
</details>


