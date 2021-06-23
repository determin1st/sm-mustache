# about
reduced, monolithized and namespaced version of [mustache-like template **eval**uator](https://mustache.github.io/)
([origin](https://github.com/bobthecow/mustache.php))


## reduced
- `<`, template parent, inheritance.
- `>`, template partials, inheritance.
- `&`, `{{{trippleStashes}}}`, charset option, entities escaping.
- `=`, section delimiters modifier.
- `!`, comments.
- strict callables option (both variants are oke).
- logger object => function (callable).
- helpers object => array.
- camel/snake case mixture => camel case.
- exceptions (set logger for debug).
- filesystem template loaders (strings only).
- filesystem cache (memory cache only).
- pragmas, may make templates smaller but more cryptic (not in the specs anyway).
- `md5()` hash calculations.
- `mbstring.func_overload` guard (deprecated in new phps).
- PHPDoc.
## monolithized
- helper classes unified into a single engine class.
- template classes converted into anonymous render functions (heredoc).
- rendering short-circuited (recursion instead of repetition).
- accumulation of lines instead of characters in tokenizer.
## namespaced
- `SM`
## added
- falsy tags detection/skip (`<!-- {{{ -->` vim markers for example `<!-- }}} -->`).
- ...
## todos
- avoid template rendering when substitution is more appropriate.
- heredoc guards
- more tests/usecases
- add `|` section inversion shorthand (inner "else" tag).
- add section operators `==`, `>`, `<`, `>=`, `<=`.
- add option of section content re-indenting
- add delims/indent option to render



# sm vs origin
- size: ~`15`kb vs ~`130`kb (**sm**'s smaller)
- speed: ... (**sm**'s faster)
- features: ... (**origin**'s more packed)


# requirements
- PHP 7+ (tested on 7.4)


# usecases
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


# syntax
## sections
## inverted sections
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



