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
        var_dump($sql);
//        var_dump($skeleton['WHERE']);
//        $where = self::printWhere($skeleton['WHERE'], $sql);
        $node = self::buildWhereTree($skeleton['WHERE']);
        var_dump(self::printWhere($skeleton['WHERE'], $sql));
        var_dump(self::printWhere($node, $sql));
//        var_dump($node);
//        var_dump($where);
        echo "\n\n";

        $sWhere = '{}';
        $params[] = $sWhere;

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
            self::Rec_Exp_Pre($EI);
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
            if (self::IfSimb($Simbolo) == False) {
//                self::Append($EPRE, $Simbolo);
                $EPRE[] = $Simbolo;
            } else {
                // If the stack has something. Si la pila contiene algo
                if (count($PILA) > 0) {
                    // While the operator has less priority that the item in top of stack. Mientras el operador sea < al que se encuentra al tope de la pila
                    while (self::Priority($Simbolo["base_expr"], $PILA[$TOPE]["base_expr"]) < 0) {
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
    private function Rec_Exp_Pre($Text = null) {
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
    private function IfSimb($Expr) {
//        $val = False;
        for ($i = 0; $i < self::$SimbX; ++$i) {
            for ($j = 0; $j < self::$SimbY; ++$j) {
                if ($Expr["base_expr"] == self::$Simb[$i][$j]) {
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
    private function Priority($exp1, $exp2) {
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
            $i = -1;
        } else if ($p1 == $p2) {
            $i = 0;
        } else if ($p1 > $p2) {
            $i = 1;
        }
        return ($i);
    }

}