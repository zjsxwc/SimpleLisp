<?php
include_once __DIR__ . "/SimpleLisp.php";
use \SimpleLisp\SimpleLisp;

$fib = '
(let
    (
        (nth 9)
        
        (fib    
            (lambda (n)   
                (if (< n 2)  n  (+
                                    (fib (- n 1)) (fib (- n 2))
                                )
                
                )
            ) 
        )
    )
    
    (fib  nth)
)
';
//f0 0  f1  1
//f2 1  f3  2
//f4 3  f5  5
//f6 8  f7 13
//f8 21 f9 34
$tokenList = SimpleLisp::tokenize($fib);
$ast = SimpleLisp::createAst($tokenList);

var_dump(SimpleLisp::interpret($ast));
//output float(34)


//1+2+3+...+100
$sum100 = '
(let
    (
        (nth 100)
        
        (sum100    
            (lambda (acc i max)   
                (if (> i max)  acc  (sum100
                                        (+ acc i) (+ i 1) max
                                    )
                )
            ) 
        )
    )
    
    (sum100  0 1 nth)
)
';
$tokenList = SimpleLisp::tokenize($sum100);
$ast = SimpleLisp::createAst($tokenList);

var_dump(SimpleLisp::interpret($ast));
//output float(5050)