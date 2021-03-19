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
        return null;
    }
}

class EndNode
{
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
     * @return string[]
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
        $sList = preg_split('/\s+/', $input);
        foreach ($sList as &$s) {
            $s = str_replace('!whitespace!', ' ', $s);
        }
        return $sList;
    }


    const TOKEN_NUMBER = 'number';
    const TOKEN_STRING = 'string';
    const TOKEN_IDENTIFIER = 'identifier';


    /**
     * 目前只有三种EndNode  number string identifier
     * @param string $token
     * @return EndNode
     */
    static public function createEndNode($token)
    {
        if (is_numeric($token)) {
            return EndNode::create([
                'type' => self::TOKEN_NUMBER,
                'value' => floatval($token)
            ]);
        } elseif (($token{0} === '"') && ($token{strlen($token) - 1} === '"')) {
            return EndNode::create([
                'type' => self::TOKEN_STRING,
                'value' => substr($token, 1, strlen($token) - 2)
            ]);
        } else {
            return EndNode::create([
                'type' => self::TOKEN_IDENTIFIER,
                'value' => $token
            ]);
        }
    }

    /**
     * 每个函数都是一个array，这个数组里面每个非函数的元素，都是EndNode，如果元素是函数则用新array表示
     * lisp函数是由  array、 number、 string、identifier 四种类型的Node组成的
     *
     * lisp函数 = Node = array = [array|EndNode]+
     * EndNode = number|string|identifier
     *
     * @param string[] $tokenList
     * @param null|array[] $currentLispNodeList 每个函数都有对应的nodeList
     * @return mixed[]|EndNode[][]  返回当前函数的完整 NodeList
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


    // runtime 运行时，需要支持 lambda 函数、  let变量、 lambda函数中的变量需要能有作用域、 if条件判断
    // identifier变量除了能绑定number与string外，还能绑定 lambda函数
    // lisp中如果array第一个 是 identifier 且 这个 identifier绑定了lambda函数，那么就调用这个 lambda函数，
    // 否则就直接把 这个 array 当作数组 数据 返回， 没错 identifier和number、string一样也是数据，当然identifier未绑定时的值是null

    //let语法例子 (let ((id1 value1) (id2 value2) (id3 value3))  (+ id1 id2 id3))
    //上面 第二个参数里面是绑定value到id变量  第三个参数是使用这些变量做加法  第三个参数的返回值就是 整个let的返回值，
    //这些变量绑定作用域也只在第三个参数里生效

    //lambda语法例子 ((lambda (x y) (0 x y)) 1 2) 第一个参数是lambda函数，
    //第二个参数1是这个lambda函数的第一个实参，第三个参数2是lambda函数的第二个实参，这个lambda使用这些参数返回了数组结果为 (0 1 2)

    //if判断语法 (if (> x y) (0 x) (1 y)) 第二个参数如果结果是真，
    //那么执行第三个参数作为if的结果为 (0 x的真值) 否则行第四个参数作为if的结果为 (1 y的真值)

    // interpret就是不断求值，变量是被绑定到context中，实现变量作用域






    /**
     * @param EndNode[][]|EndNode[]|EndNode $ast
     * @param null|RuntimeContext $context
     * @return mixed
     */
    static public function interpretArray($ast, $context)
    {
        //如果是 let、if、lambda
        if ($ast[0] instanceof EndNode) {
            if ($ast[0]->type === self::TOKEN_IDENTIFIER) {
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

                        $isStatement0ValidIdentifier = ($bindStatement[0] instanceof EndNode) && ($bindStatement[0]->type === self::TOKEN_IDENTIFIER);
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
                    return function () use ($ast, $context){
                        $args = func_get_args();
                        $newScope = [];
                        foreach ($ast[1] as $i => $lambdaArg) {
                            $isLambdaArgValidIdentifier = ($lambdaArg instanceof EndNode) && ($lambdaArg->type === self::TOKEN_IDENTIFIER);
                            if (!$isLambdaArgValidIdentifier) {
                                throw new \RuntimeException(sprintf("lambda 第二个参数中 %s 必须为 identifier", json_encode($lambdaArg)));
                            }

                            $newScope[$lambdaArg->value] = $args[$i];
                        }
                        $newContext = RuntimeContext::create($newScope, $context);
                        return self::interpret($ast[2], $newContext);
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
            if ($ast->type === self::TOKEN_IDENTIFIER) {
                return $context->get($ast->value);
            }
            if (($ast->type === self::TOKEN_NUMBER) || ($ast->type === self::TOKEN_STRING)) {
                return $ast->value;
            }
        }
        throw new \RuntimeException("不可能存在既不是array 又不是 EndNode 的 Node" . json_encode($ast));
    }


    static public function getBuiltInFunctions()
    {
        return [
            "first" => function ($x) {
                return $x[0];
            },
            "rest" => function ($x) {
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
            }
        ];
    }
}
