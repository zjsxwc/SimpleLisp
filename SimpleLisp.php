<?php


namespace SimpleLisp;


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


    /**
     * 目前只有三种EndNode  number string identifier
     * @param string $token
     * @return EndNode
     */
    static public function createEndNode($token)
    {
        if (is_numeric($token)) {
            return EndNode::create([
                'type' => 'number',
                'value' => floatval($token)
            ]);
        } elseif (($token{0} === '"') && ($token{strlen($token) - 1} === '"')) {
            return EndNode::create([
                'type' => 'string',
                'value' => substr($token, 1, strlen($token) - 2)
            ]);
        } else {
            return EndNode::create([
                'type' => 'identifier',
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
                throw new \RuntimeException("token cannot be null");
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


    // runtime 运行时，需要支持 lambda 函数、  let变量、 lambda函数中的变量需要能有作用域、
    // identifier变量除了能绑定number与string外，还能绑定 lambda函数
    // lisp中如果array第一个 是 identifier 且 这个 identifier绑定了lambda函数，那么就调用这个 lambda函数，
    // 否则就直接把 这个 array 当作数组 数据 返回， 没错 identifier和number、string一样也是数据

    //let语法例子 (let ((id1 value1) (id2 value2) (id3 value3))  (+ id1 id2 id3))
    //上面 第二个参数里面是绑定value到id变量  第三个参数是使用这些变量做加法  第三个参数的返回值就是 整个let的返回值，
    //这些变量绑定作用域也只在第三个参数里生效

    //lambda语法例子 ((lambda (x y) (0 x y)) 1 2) 第一个参数是lambda函数，
    //第二个参数1是这个lambda函数的第一个实参，第三个参数2是lambda函数的第二个实参，这个lambda使用这些参数返回了数组结果为 (0 1 2)

}

$tokenList = (SimpleLisp::tokenize('(let (x "hello lisp") (y 1 "2d  fs" 455 k (j 33)))'));
var_dump(SimpleLisp::createAst($tokenList));