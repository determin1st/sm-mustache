[![logo](https://raw.githack.com/determin1st/sm-mustache/master/tests/logo.jpg)](https://youtu.be/mQ_AdzWE5Ec)
Personal [mustache](https://mustache.github.io/) templates **eval**uator

<details>
  <summary>details</summary>

  [The origin](https://github.com/bobthecow/mustache.php)
  was reduced, monolithized and namespaced.. a total, individual rewrite from ~`130`kb to ~`15`kb.
  #### reduced (removed)
  - `=`, template delimiters modifier.
  - `<`, template parent, inheritance.
  - `>`, template partials, inheritance.
  - pragmas (not in spec).
  - escaping with `{{{trippleStash}}}`.
  - escaping by default (specified explicitly).
  - template recursion by default (specified explicitly).
  - exceptions/breaks.
  - strict callables option.
  - logger object => function (callable).
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

## tests
<details>
<summary>performance</summary>

images here
</details>
<details>
<summary>specification <https://github.com/mustache/spec></summary>

[![comments](https://raw.githack.com/determin1st/sm-mustache/master/tests/comments.jpg)](https://github.com/determin1st/sm-mustache/blob/master/tests/comments.json)
fails below are `{{{triple_stash}}}`es, which are not supported.
[![interpolation](https://raw.githack.com/determin1st/sm-mustache/master/tests/interpolation.jpg)](https://github.com/determin1st/sm-mustache/blob/master/tests/interpolation.json)
[![inverted](https://raw.githack.com/determin1st/sm-mustache/master/tests/inverted.jpg)](https://github.com/determin1st/sm-mustache/blob/master/tests/inverted.json)
lambdas fail because delimiter alternation in template is not supported.
the last one is [doubtful](https://github.com/mustache/spec/issues/128#issuecomment-868940293).
[![lambdas](https://raw.githack.com/determin1st/sm-mustache/master/tests/lambdas.jpg)](https://github.com/determin1st/sm-mustache/blob/master/tests/lambdas.json)
[![sections](https://raw.githack.com/determin1st/sm-mustache/master/tests/sections.jpg)](https://github.com/determin1st/sm-mustache/blob/master/tests/sections.json)
</details>










<details>
  <summary>todo</summary>

# usage
### construct
### render

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


