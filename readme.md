
A simple lisp interpreter implemented by php


```php
<?php
include_once __DIR__ . "/SimpleLisp.php";

use \SimpleLisp\SimpleLisp;

$fib = '
(let
    ((fib    
        (lambda (n)   
            (if
                (< n 2)  n  (+
                                (fib (- n 1)) (fib (- n 2))
                            )
            
            )
        ) 
    ))
    
    (fib  9 )
)
';
//f0 0 f1 1
//f2 1  f3  2
//f4 3  f5  5
//f6 8  f7 13
//f8 21 f9 34

$tokenList =
    (SimpleLisp::tokenize($fib));
$ast = SimpleLisp::createAst($tokenList);

var_dump(SimpleLisp::interpret($ast));
//output float(34)

```
