<?php
include_once __DIR__ . "/SimpleLisp.php";

use \SimpleLisp\SimpleLisp;

$tokenList =
    (SimpleLisp::tokenize('(let ((x 2333) (y 555) (add (lambda (a b) (+ a b 1)))) (print (add x y)))'));
$ast = SimpleLisp::createAst($tokenList);

SimpleLisp::interpret($ast);

//output float(2889)