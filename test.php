<?php
include_once __DIR__ . "/SimpleLisp.php";

use \SimpleLisp\SimpleLisp;

$tokenList =
    (SimpleLisp::tokenize('(if 1  (let ((x 2333) (y 555) (add (lambda (a b) (+ a b 1)))) (rest (print (add x y) mm 455)))  "else here")'));
$ast = SimpleLisp::createAst($tokenList);

var_dump(SimpleLisp::interpret($ast));


//output:
//
//array(3) {
//  [0]=>
//  float(2889)
//  [1]=>
//  NULL
//  [2]=>
//  float(455)
//}
//array(2) {
//  [0]=>
//  NULL
//  [1]=>
//  float(455)
//}


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
