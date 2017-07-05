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

//require_once('php-sql-parser.php');
//$sql = 'select DISTINCT 1+2   c1, 1+ 2 as
//`c2`, sum(c2),sum(c3) as sum_c3,"Status" = CASE
//        WHEN quantity > 0 THEN \'in stock\'
//        ELSE \'out of stock\'
//        END case_statement
//, t4.c1, (select c1+c2 from t1 inner_t1 limit 1) as subquery into @a1, @a2, @a3 from t1 the_t1 left outer join t2 using(c1,c2) join t3 as tX ON tX.c1 = the_t1.c1 join t4 t4_x using(x) where c1 = 1 and c2 in (1,2,3, "apple") and exists ( select 1 from some_other_table another_table where x > 1) and ("zebra" = "orange" or 1 = 1) group by 1, 2 having sum(c2) > 1 ORDER BY 2, c1 DESC LIMIT 0, 10 into outfile "/xyz" FOR UPDATE LOCK IN SHARE MODE';
//
//
//$parser = new PHPSQLParser($sql, true);
//print_r($parser->parsed);

namespace pedropelaez;

use PHPSQLParser\PHPSQLParser;

class SQLForMongodb {
    
    protected static $phpsqlparser = null;
    

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
        $where = self::SortedArrayToBalancedBST($skeleton['WHERE']);
        var_dump($sql);
        var_dump(self::printTreeInOrder($where));
        
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
     * @link http://javabypatel.blogspot.com.es/2016/10/sorted-array-to-binary-search-tree.html
     * @param array $array Conditions
     * @return \stdClass Balanced BST
     */
    protected static function SortedArrayToBalancedBST($array) {
        return self::sortedArrayToBST($array, 0, count($array)-1);
    }
 
    /**
     * @link http://javabypatel.blogspot.com.es/2016/10/sorted-array-to-binary-search-tree.html
     * @param array $array
     * @param integer $start
     * @param integer $end
     * @return \stdClass Balanced BST
     */
    protected static function sortedArrayToBST($array, $start, $end){
        if($start > $end){
            return null;
        }
         
        $mid = $start + ($end - $start) / 2;
         
        $temp = new \stdClass();
        $temp->root = $array[$mid];
        $temp->left = self::sortedArrayToBST($array, $start, $mid - 1);
        $temp->right = self::sortedArrayToBST($array, $mid + 1, $end);
         
        return $temp;
    }
    
    /**
     * Print Balanced BST in order: Left, Root, Right. Method used for debuging,
     * @link http://javabypatel.blogspot.com.es/2016/10/sorted-array-to-binary-search-tree.html
     * @param \stdClass $rootNode Balanced BST
     * @return string
     */
    private static function printTreeInOrder($rootNode){
        if($rootNode == null) {
            return '';
        }
        $resp = ' ';
        $resp .= self::printTreeInOrder($rootNode->left);
        $resp .= '{' . $rootNode->root["expr_type"] . '} ' . $rootNode->root['base_expr'] . ' ';
        $resp .= self::printTreeInOrder($rootNode->right);
        return $resp;
    }
}