<?php

/**
 * SQLForMongodb.php
 *
 * Translate SQL queries to MongoDB
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2017 Pedro Pelaez
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author    Pedro Pelaez <aaaaa976@gmail.com>
 * @copyright 2017 Pedro Pelaez
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */

namespace pedropelaez;

use PHPSQLParser\PHPSQLParser;

class SQLForMongodb {

    protected static $phpsqlparser = null;

    /*
     * MySQL Operator Precedence : Operator precedences are shown in the following list,
     * from highest precedence to the lowest. Operators that are shown together on a line have the same precedence.
     * @link http://download.nust.na/pub6/mysql/doc/refman/5.0/en/operator-precedence.html
     *
     * ------------------------------------------------------------------
     * INTERVAL
     * BINARY, COLLATE
     * !
     * - (unary minus), ~ (unary bit inversion)
     * ^
     * *, /, DIV, %, MOD
     * -, +
     * <<, >>
     * &
     * |
     * =, <=>, >=, >, <=, <, <>, !=, IS, LIKE, REGEXP, IN
     * BETWEEN, CASE, WHEN, THEN, ELSE
     * NOT
     * &&, AND
     * XOR
     * ||, OR
     * :=
     * ------------------------------------------------------------------
     *
     * The || operator has a precedence between ^ and the unary operators if the PIPES_AS_CONCAT SQL mode is enabled.
     *
     * The precedence shown for NOT is as of MySQL 5.0.2. For earlier versions, or from 5.0.2 on if the
     * HIGH_NOT_PRECEDENCE SQL mode is enabled, the precedence of NOT is the same as that of the ! operator. See Section 5.1.7, “Server SQL Modes”.
     *
     * Example:
     * mysql> SELECT 1+2*3;
     * -> 7
     * mysql> SELECT (1+2)*3;
     * -> 9
     * 
     */
    protected static $Simb = array( //[4][3]
        array('INTERVAL', '', '', '', '', '', '', '', '', '', '', ''),
        array('BINARY', 'COLLATE', '', '', '', '', '', '', '', '', '', ''),
        array('!', '', '', '', '', '', '', '', '', '', '', ''),
        array('-' /*(unary minus)*/, '~' /*(unary bit inversion)*/, '', '', '', '', '', '', '', '', '', ''),
        array('^', '', '', '', '', '', '', '', '', '', '', ''),
        array('*', '/', 'DIV', '%', 'MOD', '', '', '', '', '', '', ''),
        array('-', '+', '', '', '', '', '', '', '', '', '', ''),
        array('<<', '>>', '', '', '', '', '', '', '', '', '', ''),
        array('&', '', '', '', '', '', '', '', '', '', '', ''),
        array('|', '', '', '', '', '', '', '', '', '', '', ''),
        array('=', '<=>', '>=', '>', '<=', '<', '<>', '!=', 'IS', 'LIKE', 'REGEXP', 'IN'),
        array('BETWEEN', 'CASE', 'WHEN', 'THEN', 'ELSE', '', '', '', '', '', '', ''),
        array('NOT', '', '', '', '', '', '', '', '', '', '', ''),
        array('&&', 'AND', '', '', '', '', '', '', '', '', '', ''),
        array('XOR', '', '', '', '', '', '', '', '', '', '', ''),
        array('||', 'OR', '', '', '', '', '', '', '', '', '', ''),
        array(':=', '', '', '', '', '', '', '', '', '', '', ''),

//        array('(', ')', ''),
//        array('-', '+', ''),
//        array('/', '*', ''),
//        array('^', '', ''),
    );
    protected static $SimbX = 17;
    protected static $SimbY = 12;
    protected static $SimbParams = array(
//        'INTERVAL' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'BINARY' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'COLLATE' => array( // https://dev.mysql.com/doc/refman/5.7/en/charset-collate.html
//            'nperands' => 2,
//            'implemented' => false,
//            'translate' => '',
//        ),
        '!' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_not
            'nperands' => 1,
            'implemented' => true,
            'translate' => '$not:{<param1>}', // https://docs.mongodb.com/manual/reference/operator/query/not/
        ),
        '-' /*(unary minus)*/ => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html
            'nperands' => 1,
            'implemented' => true,
            'translate' => '$multiply:[-1,<param1>]', // $multiply: [ -1, "$quantity" ] https://docs.mongodb.com/manual/reference/operator/aggregation/multiply/
        ),
//        '~' /*(unary bit inversion)*/ => array( // https://dev.mysql.com/doc/refman/5.7/en/bit-functions.html
//            'nperands' => 1,
//            'implemented' => false,
//            'translate' => '',
//        ),
        '^' => array( // https://dev.mysql.com/doc/refman/5.7/en/bit-functions.html#operator_bitwise-xor
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$bit:{<param1>:{xor:<param2>}}', // { $bit: { <field>: { <and|or|xor>: <int> } } } https://docs.mongodb.com/manual/reference/operator/update/bit/
        ),
        '*' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_times
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$mul:{<param1>:<param2>}', // { $mul: { field: <number> } } https://docs.mongodb.com/manual/reference/operator/update/mul/
        ),
        '/' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_divide
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$divide:[<param2>,<param2>]', // { $divide: [ <expression1>, <expression2> ] } https://docs.mongodb.com/manual/reference/operator/aggregation/divide/#exp._S_divide
        ),
        'DIV' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_div
            'nperands' => 2,
            'implemented' => true,
            'translate' => 'NumberInt({$divide:[<param1>,<param2>]})',  // NumberInt({ $divide: [ <expression1>, <expression2> ] }) https://docs.mongodb.com/manual/reference/operator/aggregation/divide/#exp._S_divide
        ),
        '%' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_mod
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$mod:[<param1>,<param2>]', // $mod: [ divisor, remainder ] https://docs.mongodb.com/manual/reference/operator/query/mod/
        ),
        'MOD' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_mod
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$mod:[<param1>,<param2>]', // $mod: [ divisor, remainder ] https://docs.mongodb.com/manual/reference/operator/query/mod/
        ),
        '-' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_minus
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$subtract:[<param1>,<param2>]', // { $subtract: [ <expression1>, <expression2> ] } https://docs.mongodb.com/v3.2/reference/operator/aggregation/subtract/
        ),
        '+' => array( // https://dev.mysql.com/doc/refman/5.7/en/arithmetic-functions.html#operator_plus
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$add:[<param1>,<param2>]', // { $add: [ <expression1>, <expression2>, ... ] } https://docs.mongodb.com/v3.2/reference/operator/aggregation/add/#exp._S_add
        ),
//        '<<' => array( // https://dev.mysql.com/doc/refman/5.7/en/bit-functions.html#operator_left-shift
//            'nperands' => 2,
//            'implemented' => false,
//            'translate' => '', //
//        ),
//        '>>' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        '&' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        '|' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
        '=' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_equal
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:<param2>', // '{<param1>:{$eq:<param2>}}', // https://docs.mongodb.com/manual/reference/operator/query/eq/
        ),
        '<=>' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_equal-to
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:<param2>', // '{<param1>:{$eq:<param2>}}', // https://docs.mongodb.com/manual/reference/operator/query/eq/
        ),
        '>=' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_greater-than-or-equal
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$gte:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/gte/
        ),
        '>' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_greater-than
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$gt:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/gt/
        ),
        '<=' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_less-than-or-equal
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$lte:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/lte/
        ),
        '<' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_less-than
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$lt:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/lt/
        ),
        '<>' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_not-equal
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$ne:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/ne/
        ),
        '!=' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_not-equal
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$ne:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/ne/
        ),
//        'IS' => array( // {<param1>:{$ne:<param2>}}', // https://docs.mongodb.com/manual/reference/operator/query/ne/
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
        'LIKE' => array( // Require convert param2 to REGEX (self::parseLikePattern()) https://dev.mysql.com/doc/refman/5.7/en/string-comparison-functions.html#operator_like
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$regex:<param2>}', // https://chartio.com/resources/tutorials/how-to-use-a-sql-like-statement-in-mongodb/
        ),
        'REGEXP' => array( // https://dev.mysql.com/doc/refman/5.7/en/regexp.html#operator_regexp
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$regex:<param2>}', // https://docs.mongodb.com/manual/reference/operator/query/regex/
        ),
        'IN' => array( // Require remove '(' & ')' from param2 https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#function_in
            'nperands' => 2,
            'implemented' => true,
            'translate' => '<param1>:{$in:[<param2>]}', // https://docs.mongodb.com/manual/reference/operator/query/in/
        ),
//        'BETWEEN' => array( // https://dev.mysql.com/doc/refman/5.7/en/comparison-operators.html#operator_between
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'CASE' => array( // https://dev.mysql.com/doc/refman/5.7/en/control-flow-functions.html#operator_case
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'WHEN' => array( // https://dev.mysql.com/doc/refman/5.7/en/control-flow-functions.html#operator_case
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'THEN' => array( // https://dev.mysql.com/doc/refman/5.7/en/control-flow-functions.html#operator_case
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
//        'ELSE' => array( // https://dev.mysql.com/doc/refman/5.7/en/control-flow-functions.html#operator_case
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
        'NOT' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_not
            'nperands' => 1,
            'implemented' => true,
            'translate' => '$not:{<param1>}', // https://docs.mongodb.com/manual/reference/operator/query/not/
        ),
        '&&' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_and
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$and:[{<param1>},{<param2>}]', // https://docs.mongodb.com/manual/reference/operator/query/and/
        ),
        'AND' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_and
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$and:[{<param1>},{<param2>}]', // https://docs.mongodb.com/manual/reference/operator/query/and/
        ),
//        'XOR' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_xor
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
        '||' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_or
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$or:[{<param1>},{<param2>}]', // https://docs.mongodb.com/manual/reference/operator/query/or/
        ),
        'OR' => array( // https://dev.mysql.com/doc/refman/5.7/en/logical-operators.html#operator_or
            'nperands' => 2,
            'implemented' => true,
            'translate' => '$or:[{<param1>},{<param2>}]', // https://docs.mongodb.com/manual/reference/operator/query/or/
        ),
//        ':=' => array(
//            'nperands' => 0,
//            'implemented' => false,
//            'translate' => '',
//        ),
    );

    function __construct() {

    }

    /**
     * Parse a SQL string to MongoDB
     * @param string $sql
     * @return string MongoDB JSon
     */
    public static function parse($sql) {
        if (self::$phpsqlparser == null) {
            self::$phpsqlparser = new PHPSQLParser(false, false);
        }
        $skeleton = self::$phpsqlparser->parse($sql, false);
        if ($skeleton === false) {
            throw new \Exception('Error parsing SQL {'.$sql.'}');
        }
        $resp = '';
//        var_dump($sql);
        $xskeleton = array();
        foreach($skeleton as $section => $params) {
            $xskeleton[strtoupper($section)] = $params;
        }
        $skeleton = $xskeleton;
        foreach($skeleton as $section => $params) {
            switch ($section) {
                case 'SELECT':
                    $resp = self::parseSelect($skeleton, $sql);
                    break;
                case '':

                    break;
            }
        }

        return $resp;
    }

    /**
     * Parse a query
     * @param array $skeleton
     * @param string $sql Original SQL for informational porpouses.
     */
    public static function parseSelect($skeleton, &$sql) {
        $resp = '';

        if (!isset($skeleton['FROM'][0]['table'])) {
            throw new \Exception('Error parsing SQL {'.$sql.'}');
        }

        $params = array();

        // Where
        $sWhere = '';
        if (!isset($skeleton['WHERE'])) {
            $skeleton['WHERE'] = array();
        }
//        var_dump($sql);
        $node = self::buildWhereTree($skeleton['WHERE']);
        $sWhere = '{}';
        if (count($node) > 0) {
            $sWhere = '{'.self::parseWhere($node).'}';
        }
        $params[] = $sWhere;

        // Order
        $sort = array();
        if (isset($skeleton['ORDER'])) {
            foreach($skeleton['ORDER'] as $item) {
                switch ($item["expr_type"]) {
                    case 'colref':
                        $order = '"'.$item['base_expr'].'":';
                        if (strtoupper($item['direction']) == 'ASC') {
                            $order .= '1';
                        } else {
                            $order .= '-1';
                        }
                        $sort[] = $order;
                        break;
                }
            }
        }
        
        // Fields
        $fields = array();
        $sFields = '';
        foreach($skeleton['SELECT'] as $field) {
            if ($field["base_expr"] == '*') {
                continue;
            }
            $fields[] = $field["no_quotes"]["parts"][0].':1';
        }
        if (count($fields) > 0) {
            $sFields = '{'.implode(',', $fields).'}';
            $params[] = $sFields;
        } else if ($params[0] == '{}') {
            unset($params[0]);
        }
        
        $resp = "db.{$skeleton['FROM'][0]['table']}.find(".implode(',', $params).")";
        if (count($sort) > 0) {
            $resp .= '.sort({'.join(',', $sort).'})';
        }
        return $resp;
    }

    /**
     * Parse the where expression
     *
     * @param array $whereSkeleton
     * @param string $sql
     */
    protected static function printWhere($whereSkeleton, &$sql) {
        $resp = '';
        // Imprimir origen
        foreach($whereSkeleton as $exp) {
            if ($exp["expr_type"] == 'bracket_expression') {
                $resp .= ' ('.self::printWhere($exp["sub_tree"], $sql).' )';
            } else {
                $resp .= ' '.$exp["base_expr"];
            }
        }
        return $resp;
    }

    /**
     * Convert a prefix array of where conditions into MongoDB condition structure
     * 
     * @param array $epre Infix array
     * @return string MongoDB condition structure
     */
    public static function parseWhere(&$epre) {
        $sql = '';
        $resp = '';
        if(count($epre) > 0) {
            // Get operator
            $op = array_pop($epre);
//            var_dump("OP GET ".$op["expr_type"].'\' {'.$op["base_expr"].'}');
            if($op["expr_type"] != 'operator') {
                throw new \Exception('Spected operator but find \''.$op["expr_type"].'\' {'.$op["base_expr"].'}');
            }
            $op["base_expr"] = strtoupper($op["base_expr"]);
            if (!self::$SimbParams[$op["base_expr"]]['implemented']) {
                throw new \Exception('Operator not implemented {'.$op["base_expr"].'}');
            }
            $nparams = self::$SimbParams[$op["base_expr"]]['nperands'];
            $translate = self::$SimbParams[$op["base_expr"]]['translate'];
            $param1 = '';
            $param2 = '';
            // Get param1
            if(count($epre) <= 0) {
                throw new \Exception('Operation incomplete {'.self::printWhere($epre, $sql).'}');
            }
            $p1 = array_pop($epre);
//            var_dump("P1 GET ".$p1["expr_type"].'\' {'.$p1["base_expr"].'}');
            if($p1["expr_type"] == 'operator') {
//                var_dump("P1 PUT ".$p1["expr_type"].'\' {'.$p1["base_expr"].'}');
                $epre[] = $p1;
//                var_dump(self::printWhere($epre, $sql));
                $param1 = self::parseWhere($epre);
            } elseif($p1["expr_type"] == 'colref') {
                $param1 = '"'.$p1['base_expr'].'"';
            } else {
                $param1 = $p1['base_expr'];
            }
            if ($nparams >= 2) {
                // Get param 2
                if(count($epre) <= 0) {
                    throw new \Exception('Operation incomplete {'.self::printWhere($epre, $sql).'}');
                }
                $p2 = array_pop($epre);
//                var_dump("P2 GET ".$p2["expr_type"].'\' {'.$p2["base_expr"].'}');
                $param2 = '';
                if($p2["expr_type"] == 'operator') {
//                    var_dump("P2 PUT ".$p2["expr_type"].'\' {'.$p2["base_expr"].'}');
                    $epre[] = $p2;
                    $param2 = self::parseWhere($epre);
                } elseif($p2["expr_type"] == 'colref') {
                    $param1 = '"'.$p2['base_expr'].'"';
                } else {
                    switch ($op["base_expr"]) {
                        case 'LIKE':
                            $param2 = self::parseLikePattern($p2['base_expr']);
                            break;
                        default:
                            $param2 = $p2['base_expr'];
                            break;
                    }
                }
            }
            // Do replacements
            if (!empty($param1)) {
                $translate = str_replace('<param1>', $param1, $translate);
            }
            if (!empty($param2)) {
                $translate = str_replace('<param2>', $param2, $translate);
            }
            $resp = $translate;
        }
//        var_dump(self::printWhere($epre, $sql));
        return $resp;
    }
    
    /**
     * Becomes an expression EI (infix) to a EPRE expression (prefix)
     *
     * @param array $stack Infix list of tockens
     * @param integer $level
     * @return array Prefix list of tockens
     */
    public static function buildWhereTree($stack, $level = 0) {
        /* http://www.lawebdelprogramador.com/foros/Algoritmia/181793-convertir-notacion-Infija-a-postfija.html
         *
         * Convierte una expresion EI (Infija) a una espresi¢n EPRE (Prefija)
         */
        //void Conv_Pre(char EI[], char EPRE[]) {
        $EI = $stack;
        $EPRE = array();
        $TOPE = -1;
        $n = count($EI);
        $Simbolo = '';
        $PILA = array(); // 50
        //Clear(PILA, 50);
        /* Hacer TOPE <- -1 */
        //TOPE = -1;
        //$n = length(EI);
        // While the stack has any simbol. Mientras EI sea diferente de la cadena vacia
        while ($n > 0) {
            $n -= 1;
            // Get the top item in stack (PEEK). Tomamos el simbolo mas a la derecha
            $Simbolo = $EI[$n];
            // We remove tha last item in stack (POP). Recortamos la expresi¢n
            self::buildWhereTreeRecExpPre($EI);
            // If the simbol is right parenthesis. Si el s¡mbolo es parentesis derecho
//            if ($Simbolo == ')') {
//                $TOPE += 1;
//                // We add the simbol to the stack. Colocamos el simbolo en la pila
//                $PILA[$TOPE] = $Simbolo;
//            } else
            // If the simbol is left parenthesis. Si el simbolo es izquierdo
//            if ($Simbolo == '(') {
//                while ($PILA[$TOPE] != ')') {
////                    self::Append($EPRE, $PILA[$TOPE]);
//                    $EPRE[] = $PILA[$TOPE];
//                    $PILA[$TOPE] = '';
//                    $TOPE -= 1;
//                }
//                /* Sacamos el parentesis de la pila */
//                $PILA[$TOPE] = '';
//                $TOPE -= 1;
//            } else
            if($Simbolo["expr_type"] == 'bracket_expression') {
                $subtree = self::buildWhereTree($Simbolo["sub_tree"]);
                foreach($subtree as $node) {
                    $EPRE[] = $node;
                }
            } else
            // If operand. Si es operando
            if (self::buildWhereTreeIfSimb($Simbolo) == False) {
//                self::Append($EPRE, $Simbolo);
                $EPRE[] = $Simbolo;
            } else {
                // If the stack has something. Si la pila contiene algo
                if (count($PILA) > 0) {
                    // While the operator has less priority that the item in top of stack. Mientras el operador sea < al que se encuentra al tope de la pila
                    while (self::buildWhereTreePriority($Simbolo["base_expr"], $PILA[$TOPE]["base_expr"]) < 0) {
                        // We add the top item in stack to results. Agregar lo que hay en el tope de la pila
//                        self::Append($EPRE, $PILA[$TOPE]);
                        $EPRE[] = $PILA[$TOPE];
                        // We remove top item in the stack. Eliminamos lo que hay en el tope de la pila
                        $PILA[$TOPE] = '';
                        $TOPE -= 1;
                        if ($TOPE < 0)
                            break;
                    }
                }
                $TOPE += 1;
                // We add the simbol to the stack. Agregamos el simbolo al tope de la pila
                $PILA[$TOPE] = $Simbolo;
            }
        }
        // We add the remain items. Agregamos lo que quedo en la pila
        while ($TOPE >= 0) {
            //self::Append($EPRE, $PILA[$TOPE]);
            $EPRE[] = $PILA[$TOPE];
            $TOPE -= 1;
        }
        return $EPRE;
    }

    /**
     * Remove the last item in Text.
     * Elimina el ultimo elemento de Text
     *
     * IMPORTANT: Really don't remove the item, only change it to a empty string.
     * @param array $Text Items array
     */
    private function buildWhereTreeRecExpPre($Text = null) {
        if ($Text == null) $Text = array();
        $n = count($Text);
        $Text[$n - 1] = '';
    }

    /**
     * Verify if Expr is a simbol or not
     * Verifica si Expr es simbolo o no
     *
     * @param array $Expr Simbol array
     * @return boolean TRUE if $Expr represent a operator.
     */
    private function buildWhereTreeIfSimb($Expr) {
//        $val = False;
        for ($i = 0; $i < self::$SimbX; ++$i) {
            for ($j = 0; $j < self::$SimbY; ++$j) {
                if (strtoupper($Expr["base_expr"]) == self::$Simb[$i][$j]) {
//                    $val = True;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get priority betwenn exp1 and exp2
     * Calcula la prioridad entre exp1 y exp2
     *
     * -1 si exp1 < exp2
     * 0 si exp1 == exp2
     * 1 si exp1 > exp2
     * @param string $exp1 Operator 1
     * @param string $exp2 Operator 2
     * @return integer -1 if exp1 &lt; exp2, 0 if exp1 == exp2, 1 if exp1 > exp2
     */
    private function buildWhereTreePriority($exp1, $exp2) {
        //int i, j, p1, p2;
        $p1 = -1;
        $p2 = -1;
        for ($i = 0; $i < self::$SimbX; ++$i) {
            for ($j = 0; $j < self::$SimbY; ++$j) {
                if ($exp1 == self::$Simb[$i][$j]) {
                    $p1 = $i;
                }
                if ($exp2 == self::$Simb[$i][$j]) {
                    $p2 = $i;
                }
                if ($p1 != -1 && $p2 != -1) break;
            }
            if ($p1 != -1 && $p2 != -1) break;
        }
        if ($p1 < $p2) {
            $i = 1;
        } else if ($p1 == $p2) {
            $i = 0;
        } else if ($p1 > $p2) {
            $i = -1;
        }
        return ($i);
    }

    /**
     * Convert LIKE pattern to REGEX pattern
     * (% and _ wildcards and a generic $escape escape character)
     * 
     * @author kermit <https://stackoverflow.com/users/679449/kermit>
     * @link https://stackoverflow.com/a/11436643
     * @param string $pattern LIKE pattern
     * @param string $escape SCAPE pattern
     * @return type
     */
    public static function parseLikePattern($pattern, $escape = '\\') {
        // Split the pattern into special sequences and the rest
        $expr = '/((?:' . preg_quote($escape, '/') . ')?(?:' . preg_quote($escape, '/') . '|%|_))/';
        $parts = preg_split($expr, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Loop the split parts and convert/escape as necessary to build regex
        $expr = '/^';
        $lastWasPercent = FALSE;
        foreach ($parts as $part) {
            switch ($part) {
                case $escape . $escape:
                    $expr .= preg_quote($escape, '/');
                    $expr .= $escape;
                    break;
                case $escape . '%':
                    $expr .= '%';
                    break;
                case $escape . '_':
                    $expr .= '_';
                    break;
                case '%':
                    if (!$lastWasPercent) {
                        $expr .= '.*?';
                    }
                    break;
                case '_':
                    $expr .= '.';
                    break;
                default:
                    $expr .= preg_quote($part, '/');
                    break;
            }
            $lastWasPercent = $part == '%';
        }
        $expr .= '$/i';
        
        return str_replace('"', '', $expr);
    }

}