# about
reduced, monolithized and namespaced version of [mustache-like template **eval**uator](https://mustache.github.io/)
([origin](https://github.com/bobthecow/mustache.php))


## reduced
- `<`, template parent, inheritance.
- `>`, template partials, inheritance.
- `&`, `{{{trippleStashes}}}`, charset option, entities escaping.
- `=`, section delimiters modifier.
- `!`, comments (will work anyway).
- strict callables option (both variants are oke).
- logger object => function (callable).
- helpers object => array.
- camel/snake case mixture => camel case.
- exceptions (set logger for debug).
- filesystem template loaders (strings only).
- filesystem cache (memory cache only).
- pragmas, may make templates smaller but more cryptic (not in the specs anyway).
- `md5()` hash calculations.
- PHPDoc.
## monolithized
- helper classes unified into a single engine class.
- template classes converted into anonymous render functions (heredoc).
- rendering short-circuited (recursion instead of repetition).
## namespaced
- `SM`


# requirements
- PHP 7+ (tested on 7.4)


# sm vs origin
- size: ~`15`kb vs ~`130`kb
- speed: ...
- features: ...


# usage
```php
$text = <<<EOD

{:#test:}Hello {:name:}, this is a {:test:}!{:/test:}
{:#test:}Hello {:name:}, this is a {:#test:}test{:/test:}!{:/test:}

EOD;
echo $mustache->render($text, [
  'name' => 'tester',
  'test' => (function($s) {return $s;}),
], '{: :}');
```


# todos
- validate and skip falsy tags (`<!-- {{{ -->` vim markers for example).
- avoid template rendering when substitution is more appropriate.
- heredoc guards
- more tests

