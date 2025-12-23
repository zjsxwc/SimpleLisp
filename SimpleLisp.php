<?php
namespace SimpleLisp;

class RuntimeContext
{
    /** @var array */
    public $scope;
    /** @var null|self */
    public $parent;

    /**
     * @param array $scope
     * @param null|self $parent
     * @return RuntimeContext
     */
    static public function create($scope = [], $parent = null)
    {
        $c = new self();
        $c->scope = $scope;
        $c->parent = $parent;
        return $c;
    }

    /**
     * @param string $identifier
     * @return mixed|null
     */
    public function get($identifier)
    {
        if (isset($this->scope[$identifier])) {
            return $this->scope[$identifier];
        }
        if ($this->parent) {
            return $this->parent->get($identifier);
        }
        //echo "找不到变量{$identifier} 用null 代替\r\n";
        return null;
    }
}

class EndNode
{
    const END_NODE_TYPE_NUMBER = 'number';
    const END_NODE_TYPE_STRING = 'string';
    const END_NODE_TYPE_IDENTIFIER = 'identifier';

    /** @var string */
    public $type;
    /** @var string */
    public $value;

    /**
     * @param array $a
     * @return EndNode
     */
    static public function create($a)
    {
        $n = new self();
        $n->type = $a["type"];
        $n->value = $a["value"];
        return $n;
    }
}

class SimpleLisp
{
    /**
     * @param $input
     * @return string[] $tokenList
     */
    static public function tokenize($input)
    {
        /** @var string[] $sList */
        $sList = explode('"', $input);
        foreach ($sList as $i => &$s) {
            if ($i % 2 === 0) {
                //强行让非字符串中的 左、右 括号成为独立的 token
                $s = str_replace('(', ' ( ', $s);
                $s = str_replace(')', ' ) ', $s);
            } else {
                //不让字符串中的空格被分隔成token
                $s = str_replace(' ', '!whitespace!', $s);
            }
        }
        $input = implode('"', $sList);
        $input = trim($input);
        $tokenList = preg_split('/\s+/', $input);
        foreach ($tokenList as &$s) {
            $s = str_replace('!whitespace!', ' ', $s);
        }
        return $tokenList;
    }

    /**
     * 目前只有三种EndNode  number string identifier
     * @param string $token
     * @return EndNode
     */
    static public function createEndNode($token)
    {
        if (is_numeric($token)) {
            return EndNode::create([
                'type' => EndNode::END_NODE_TYPE_NUMBER,
                'value' => floatval($token)
            ]);
        } elseif (($token[0] === '"') && ($token[strlen($token) - 1] === '"')) {
            return EndNode::create([
                'type' => EndNode::END_NODE_TYPE_STRING,
                'value' => substr($token, 1, strlen($token) - 2)
            ]);
        } else {
            return EndNode::create([
                'type' => EndNode::END_NODE_TYPE_IDENTIFIER,
                'value' => $token
            ]);
        }
    }

    /**
     * 每个函数都是一个array，这个数组里面每个非函数的元素，都是EndNode，如果元素是函数则用新array表示
     * lisp函数是由  array、 number、 string、identifier 四种类型的Node组成的
     *
     * lisp函数 = Ast = NodeList = array = [array|EndNode]+
     * EndNode = number|string|identifier
     *
     * @param string[] $tokenList
     * @param null|mixed[]|EndNode[][]|EndNode[] $currentLispNodeList 每个函数都有对应的nodeList
     * @return mixed[]|EndNode[][]|EndNode[]  返回当前函数的完整 NodeList
     */
    static public function createAst(&$tokenList, &$currentLispNodeList = null)
    {
        if ($currentLispNodeList === null) {
            $newNodeList = [];
            return self::createAst($tokenList, $newNodeList);
        } else {
            $token = array_shift($tokenList);
            if ($token === null) {
                throw new \RuntimeException("token 不能为 null");
            } elseif ($token === '(') {
                $newNodeList = [];
                $arrayNode = self::createAst($tokenList, $newNodeList);
                array_push($currentLispNodeList, $arrayNode);

                //获取arrayNode后返回 代表 tokenList里面一个 子函数 处理完成，但当前函数还可能没有完成，所以我们继续处理
                if (count($tokenList) > 0) {
                    return self::createAst($tokenList, $currentLispNodeList);
                } else {
                    //边界条件，当前函数是最外层函数，最外层函数也处理完了,由于最外层函数永远只有一个，所以直接pop就是结果了
                    return array_pop($currentLispNodeList);
                }
            } elseif ($token === ')') {
                //当前函数处理结束，返回该函数的完整 NodeList
                return $currentLispNodeList;
            } else {
                $endNode = self::createEndNode($token);
                array_push($currentLispNodeList, $endNode);

                //获取一个endNode后返回 代表 tokenList里面一个 endNode 处理完成，但当前函数还没有完成，所以我们继续处理
                return self::createAst($tokenList, $currentLispNodeList);
            }
        }
    }



    // interpret就是不断求值，变量是被绑定到context中，实现变量作用域, context通过parent方式实现局部作用域
    // lisp，需要支持 lambda 函数、  let变量、 lambda函数中的变量需要能有作用域、 if条件判断
    // identifier变量除了能绑定number与string外，还能绑定 lambda函数
    // lisp中如果array第一个 直接是 callable函数 或者 identifier 且 这个 identifier绑定了lambda函数，那么就调用这个 lambda函数，
    // 否则就直接把 这个 array 当作数组 数据 返回， 没错 identifier和number、string一样也是数据，当然identifier未绑定时的值是null

    //let语法例子 (let ((id1 value1) (id2 value2) (id3 value3))  (+ id1 id2 id3))
    //上面 第二个参数里面是绑定value到id变量  第三个参数是使用这些变量做加法  第三个参数的返回值就是 整个let的返回值，
    //这些变量绑定作用域也只在第三个参数里生效

    //lambda语法例子 ((lambda (x y) (0 x y)) 1 2) 第一个参数是lambda函数，
    //第二个参数1是这个lambda函数的第一个实参，第三个参数2是lambda函数的第二个实参，这个lambda使用这些参数返回了数组结果为 (0 1 2)

    //if判断语法 (if (> x y) (0 x) (1 y)) 第二个参数如果结果是真，
    //那么执行第三个参数作为if的结果为 (0 x的真值) 否则行第四个参数作为if的结果为 (1 y的真值)

    /**
     * @param EndNode[][]|EndNode[]|mixed[] $ast
     * @param null|RuntimeContext $context
     * @return mixed
     */
    static public function interpretArray($ast, $context)
    {
        //如果是 let、if、lambda
        if ($ast && ($ast[0] instanceof EndNode)) {
            if ($ast[0]->type === EndNode::END_NODE_TYPE_IDENTIFIER) {
                if ($ast[0]->value === 'let') {
                    if (!is_array($ast[1])) {
                        throw new \RuntimeException("let 第二个参数必须为数组");
                    }
                    if (count($ast) != 3) {
                        throw new \RuntimeException(sprintf("let  %s 数组长度必须为3", json_encode($ast)));
                    }
                    //$ast[1] 必为数组
                    $newContext = RuntimeContext::create([], $context);
                    foreach ($ast[1] as $bindStatement) {
                        if (!is_array($bindStatement)) {
                            throw new \RuntimeException(sprintf("let 第二个参数中 %s 必须为数组", json_encode($bindStatement)));
                        }
                        if (count($bindStatement) != 2) {
                            throw new \RuntimeException(sprintf("let 第二个参数中 %s 数组长度必须为2", json_encode($bindStatement)));
                        }
                        //把计算的值$bindStatement[1] 绑定到identifier $bindStatement[0]

                        $isStatement0ValidIdentifier = ($bindStatement[0] instanceof EndNode) && ($bindStatement[0]->type === EndNode::END_NODE_TYPE_IDENTIFIER);
                        if (!$isStatement0ValidIdentifier) {
                            throw new \RuntimeException(sprintf("let 第二个参数中 %s 的第一个参数必须为 identifier", json_encode($bindStatement)));
                        }
                        $newContext->scope[$bindStatement[0]->value] = self::interpret($bindStatement[1], $newContext);
                    }

                    return  self::interpret($ast[2], $newContext);
                }

                if ($ast[0]->value === 'if') {
                    if (count($ast) != 4) {
                        throw new \RuntimeException(sprintf("if %s 数组长度必须为4", json_encode($ast)));
                    }
                    $judgeResult = self::interpret($ast[1], $context);
                    if ($judgeResult) {
                        return self::interpret($ast[2], $context);
                    } else {
                        return self::interpret($ast[3], $context);
                    }
                }

                if ($ast[0]->value === 'lambda') {
                    if (count($ast) != 3) {
                        throw new \RuntimeException(sprintf("lambda %s 数组长度必须等于3", json_encode($ast)));
                    }
                    if (!is_array($ast[1])) {
                        throw new \RuntimeException(sprintf("lambda 第二个参数 %s 必须为数组", json_encode($ast[1])));
                    }

                    //localAst 将被返回的闭包函数捕获持有
                    $localAst = $ast;
                    return function () use ($localAst, $context) {
                        $args = func_get_args();
                        //把实参的值绑定到局部变量中，形参也是局部变量
                        $newScope = [];
                        foreach ($localAst[1] as $i => $lambdaArg) {
                            $isLambdaArgValidIdentifier = ($lambdaArg instanceof EndNode) && ($lambdaArg->type === EndNode::END_NODE_TYPE_IDENTIFIER);
                            if (!$isLambdaArgValidIdentifier) {
                                throw new \RuntimeException(sprintf("lambda 第二个参数中 %s 必须为 identifier", json_encode($lambdaArg)));
                            }

                            $newScope[$lambdaArg->value] = $args[$i];
                        }
                        $newContext = RuntimeContext::create($newScope, $context);
                        return self::interpret($localAst[2], $newContext);
                    };
                }
            }
        }

        //求解每个Node
        $interpretedNodeResultArray = [];
        foreach ($ast as $node) {
            $interpretedNodeResultArray[] = self::interpret($node, $context);
        }

        //如果第一个Node是 自定义lambda函数 或者 buildin函数 的结果，则调用它
        if ($interpretedNodeResultArray && is_callable($interpretedNodeResultArray[0])) {
            $args = [];
            foreach ($interpretedNodeResultArray as $i => $result) {
                if ($i) {
                    $args[] = $result;
                }
            }
            return call_user_func_array($interpretedNodeResultArray[0], $args);
        }

        //如果只是普通array就返回它
        return $interpretedNodeResultArray;
    }

    /**
     * @param EndNode[][]|EndNode[]|EndNode $ast
     * @param null|RuntimeContext $context
     * @return mixed
     */
    static public function interpret($ast, $context = null)
    {
        if ($context === null) {
            $newContext = RuntimeContext::create(self::getBuiltInFunctions(), null);
            return self::interpret($ast, $newContext);
        }
        if (is_array($ast)) {
            return self::interpretArray($ast, $context);
        } elseif ($ast instanceof EndNode) {
            if ($ast->type === EndNode::END_NODE_TYPE_IDENTIFIER) {
                return $context->get($ast->value);
            }
            if (($ast->type === EndNode::END_NODE_TYPE_NUMBER) || ($ast->type === EndNode::END_NODE_TYPE_STRING)) {
                return $ast->value;
            }
        }
        throw new \RuntimeException("不可能存在既不是array 又不是 EndNode 的 Node" . json_encode($ast));
    }


    static public function getBuiltInFunctions()
    {
        return [
            "first" => function ($x) {
                if (!is_array($x)) {
                    throw new \RuntimeException("first 参数必须是array，不能传入" . json_encode($x));
                }
                return $x[0];
            },
            "rest" => function ($x) {
                if (!is_array($x)) {
                    throw new \RuntimeException("rest 参数必须是array，不能传入" . json_encode($x));
                }
                $r = [];
                foreach ($x as $i => $p) {
                    if ($i) {
                        $r[] = $p;
                    }
                }
                return $r;
            },
            "print" => function () {
                $args = func_get_args();
                var_dump($args);
                return $args;
            },
            "+" => function () {
                $args = func_get_args();
                $r = 0;
                foreach ($args as $arg) {
                    $r += $arg;
                }
                return $r;
            },
            "-" => function ($a, $b) {
                return $a - $b;
            },
            "*" => function ($a, $b) {
                return $a * $b;
            },
            "/" => function ($a, $b) {
                return floatval($a) / floatval($b);
            },
            "<" => function ($a, $b) {
                return ($a < $b) ? 1 : 0;
            },
            ">" => function ($a, $b) {
                return ($a > $b) ? 1 : 0;
            },
            "=" => function ($a, $b) {
                return ($a == $b) ? 1 : 0;
            },
            "<=" => function ($a, $b) {
                return ($a <= $b) ? 1 : 0;
            },
            ">=" => function ($a, $b) {
                return ($a >= $b) ? 1 : 0;
            },
        ];
    }
}





/**
 * 中文+数字+字母变量名 四则运算转Lisp表达式转换器
 * 支持：中文/数字/字母组合变量名、+ - * / 四则运算、括号优先级、混合运算
 * 变量名规则：可包含中文字符、字母（大小写）、数字，不能以数字开头
 * 示例：(销售额A1 - 成本B2) * 利润率C3 / 数量D4 → (* (/ (- 销售额A1 成本B2) 利润率C3) 数量D4)
 */
class ChineseMathToLisp
{
    // 运算符优先级（数字越大优先级越高）
    private $opPriority = [
        '+' => 1,
        '-' => 1,
        '*' => 2,
        '/' => 2,
        '(' => 0, // 括号优先级最低，仅用于栈控制
    ];

    /**
     * 校验变量名合法性（支持中文/字母开头，可含数字/字母/中文）
     * @param string $var 待校验的变量名
     * @throws InvalidArgumentException
     */
    private function validateVarName(string $var): void
    {
        // 变量名正则：以中文/字母开头，后续可跟中文/字母/数字
        $pattern = '/^[\x{4e00}-\x{9fa5}a-zA-Z][\x{4e00}-\x{9fa5}a-zA-Z0-9]*$/u';
        // 纯数字判定（数值不需要校验变量名规则）
        $isNumber = preg_match('/^\d+(\.\d+)?$/u', $var);

        if (!$isNumber && !preg_match($pattern, $var)) {
            throw new InvalidArgumentException("非法变量名：{$var}（需以中文/字母开头，可含中文/字母/数字）");
        }
    }

    /**
     * 词法分析：将表达式拆分为Token（数值、中文+数字+字母变量、运算符、括号）
     * @param string $expr 原始表达式（如：(销售额A1-成本B2)*利润率C3/数量D4）
     * @return array Token数组
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = mb_strlen($expr, 'UTF-8');
        $current = '';
        $i = 0;

        while ($i < $len) {
            $char = mb_substr($expr, $i, 1, 'UTF-8');
            
            // 跳过空白字符
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // 运算符/括号：直接作为独立Token
            if (in_array($char, ['+', '-', '*', '/', '(', ')'])) {
                // 如果当前有累积的变量/数值，先加入Token并校验
                if (!empty($current)) {
                    $this->validateVarName($current);
                    $tokens[] = $current;
                    $current = '';
                }
                $tokens[] = $char;
                $i++;
            } 
            // 中文/字母/数字/小数点：累积为变量/数值Token
            else if (preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9.]+$/u', $char)) {
                $current .= $char;
                $i++;
            } 
            // 非法字符
            else {
                throw new InvalidArgumentException("非法字符：{$char}，位置：{$i}");
            }
        }

        // 处理最后一个累积的Token并校验
        if (!empty($current)) {
            $this->validateVarName($current);
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * 中缀表达式（常规四则运算）转后缀表达式（逆波兰表示）
     * @param array $tokens 词法分析后的Token数组
     * @return array 后缀表达式数组
     */
    private function infixToPostfix(array $tokens): array
    {
        $output = []; // 输出队列
        $opStack = []; // 运算符栈

        foreach ($tokens as $token) {
            // 变量/数字：直接加入输出队列
            if (!in_array($token, ['+', '-', '*', '/', '(', ')'])) {
                $output[] = $token;
            }
            // 左括号：入栈
            elseif ($token === '(') {
                array_push($opStack, $token);
            }
            // 右括号：弹出栈内运算符直到左括号
            elseif ($token === ')') {
                while (!empty($opStack) && end($opStack) !== '(') {
                    $output[] = array_pop($opStack);
                }
                // 弹出左括号（不加入输出）
                if (empty($opStack)) {
                    throw new InvalidArgumentException("括号不匹配：缺少左括号");
                }
                array_pop($opStack);
            }
            // 运算符：按优先级处理
            else {
                while (!empty($opStack) && $this->opPriority[end($opStack)] >= $this->opPriority[$token]) {
                    $output[] = array_pop($opStack);
                }
                array_push($opStack, $token);
            }
        }

        // 弹出栈内剩余运算符
        while (!empty($opStack)) {
            $op = array_pop($opStack);
            if ($op === '(') {
                throw new InvalidArgumentException("括号不匹配：缺少右括号");
            }
            $output[] = $op;
        }

        return $output;
    }

    /**
     * 后缀表达式转Lisp表达式
     * @param array $postfix 后缀表达式数组
     * @return string Lisp表达式
     */
    private function postfixToLisp(array $postfix): string
    {
        $stack = [];

        foreach ($postfix as $token) {
            // 运算符：弹出两个操作数，构造Lisp节点
            if (in_array($token, ['+', '-', '*', '/'])) {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException("表达式语法错误：操作数不足");
                }
                $right = array_pop($stack);
                $left = array_pop($stack);
                $stack[] = "({$token} {$left} {$right})";
            }
            // 变量/数字：直接入栈
            else {
                $stack[] = $token;
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException("表达式语法错误：运算符/操作数不匹配");
        }

        return $stack[0];
    }

    /**
     * 主转换方法
     * @param string $expr 原始四则运算表达式
     * @return string Lisp表达式
     * @throws InvalidArgumentException
     */
    public function convert(string $expr): string
    {
        // 预处理：移除全角符号、统一格式
        $expr = str_replace(['（', '）', '＋', '－', '×', '÷'], ['(', ')', '+', '-', '*', '/'], $expr);
        $expr = trim($expr);

        if (empty($expr)) {
            throw new InvalidArgumentException("表达式不能为空");
        }

        // 三步转换：词法分析 → 中缀转后缀 → 后缀转Lisp
        $tokens = $this->tokenize($expr);
        $postfix = $this->infixToPostfix($tokens);
        $lisp = $this->postfixToLisp($postfix);

        return $lisp;
    }
}

