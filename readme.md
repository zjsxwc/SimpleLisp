
A simple lisp interpreter implemented by php

#### 例子

```php
<?php
include_once __DIR__ . "/SimpleLisp.php";

use \SimpleLisp\SimpleLisp;

$fib = '
(let
    ((fib    
        (lambda (n)   
            (if (< n 2)  n  (+
                                (fib (- n 1)) (fib (- n 2))
                            )
            
            )
        ) 
    ))
    
    (fib  9)
)
';
//f0 0  f1 1
//f2 1  f3  2
//f4 3  f5  5
//f6 8  f7 13
//f8 21 f9 34

$tokenList = SimpleLisp::tokenize($fib);
$ast = SimpleLisp::createAst($tokenList);

var_dump(SimpleLisp::interpret($ast));
//output float(34)

```



#### 关于如何用vm bytecode实现的思路

lisp如果不用直接解释器而用vm bytecode方式实现，

则context对象要用 内存读写操作 来模拟，类似多级hashmap的数据结构，

而由于bytecode没有php的闭包函数，则lambda要用 固定的程序码，类似c语言“代码区”，
代码执行用jump指令与当前正在执行的指令位置变量ip，
以及每次jump进函数都要创建新子context来模拟局部变量，
子context要记录要返回的代码执行ip地址值，与结果放到父context的哪个位置，
返回函数时函数结果放入父context对应位置变量，
销毁子context。

