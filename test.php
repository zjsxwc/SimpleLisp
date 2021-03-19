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