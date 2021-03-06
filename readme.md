
A simple lisp interpreter implemented by php.

一个200行左右代码实现的lisp解释器。

由于lisp语法太简单了，所以不需要LL、LR等方法，直接自顶向下递归子程序法就能构建AST树。

#### 语法

目前只支持 `let` `lambda` `if` 三种基础语句，
当然有了变量绑定、lambda函数、条件判断这三种语法后，
lisp也基本是完备了的。

数据类型只有`number` `string` `lambda函数` `数组`，

在执行时`identifier`可以通过`let`绑定这些数据，没有绑定数据的`identifier`会被当作`null`值,

如果`数组`第一个元素的值是`lambda函数`或者`内置函数`则这个`数组`的值会被计算为函数调用后的值，
而不是单纯`数组`本身值，所以如果要传递`函数`值，就不能把函数放在数组第一个元素中。

`内置函数`目前也只是加减乘除等，详见`getBuiltInFunctions`方法返回。



#### 例子

计算第10个斐波那契数：

```php
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

```



#### 关于如何用vm bytecode实现的思路

lisp如果不用直接解释器而用vm bytecode方式实现，

则context对象要用 内存读写操作 来模拟，类似多级hashmap的数据结构，

而由于bytecode没有php的闭包函数，则lambda要用固定的程序码，类似c语言“代码区”，
代码执行用jump指令与当前正在执行的指令位置变量ip来实现，
以及每次jump进函数都要创建新子context来模拟局部变量，
子context要记录要返回的代码执行ip地址值，与结果放到父context的哪个位置，
返回函数时函数结果放入父context对应位置变量，
销毁子context。

