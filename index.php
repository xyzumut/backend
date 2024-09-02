<?php

    class DB {

        private const DBUSERNAME = 'root';
        private const DBPASSWORD = '';
        private const SERVERNAME = 'localhost';
        private const DBNAME     = 'mazotdb';

        protected PDO $db;

        public function __construct() {
            try {
                $this->db = new PDO('mysql:host='.self::SERVERNAME.';dbname='.self::DBNAME, self::DBUSERNAME, self::DBPASSWORD);
            } 
            catch (\Throwable $th) {
                echo $th->getMessage();
                die;
            }
        }

        protected function getPDOType($val){

            $result = null;

            switch (gettype($val)) {
                case 'integer':
                    $result = PDO::PARAM_INT;
                    break;
                case 'double': 
                    $result = PDO::PARAM_STR;
                    break;
                case 'string': 
                    $result = PDO::PARAM_STR;
                    break;
                case 'boolean': 
                    $result = PDO::PARAM_BOOL;
                    break;
                default:break;
            }
            
            return $result;
        }

        protected function res($data, $error = null){
            return [ 'data' => $data, 'error' => $error ];
        }
    }

    final class DBBuilder extends DB {

        public function getUser( $id ){
            try {
                $stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id");

                $stmt->bindParam('id', $id, PDO::PARAM_INT);

                $stmt->execute([$id]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return $this->res(['user' => $user]);
            } 
            catch (\Throwable $th) {
                return $this->res(null, $th->getMessage());
            }
        }

        public function selectQuery( $table, $query = [], $multiple = true ){
            try {
                //code...
            
                $sql = "SELECT * FROM $table";

                if (!empty($query)) {
                    $sql = $sql.' WHERE';
                }

                foreach($query AS $q){
                    $sql = $sql.' '.$q[0];
                    if (isset($q[1]) && isset($q[2])) {
                        $sql = $sql.' '.$q[1].' :'.$q[0].' ';
                        $queryParams[] = [ 'key' => ':'.$q[0], 'value' => $q[2], 'type' => $this->getPDOType($q[2])];
                        if (isset($q[3])) {
                            $sql = $sql.$q[3];
                        }
                    }
                }

                $stmt = $this->db->prepare($sql);

                foreach ($queryParams as $item) {
                    $stmt->bindParam($item['key'], $item['value'], $item['type']);
                }

                $stmt->execute();

                if ($multiple) {
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                else{
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                return $this->res($result);
            } 
            catch (\Throwable $th) {
                return $this->res(null, $th->getMessage());
            }
        }
    }

    $builder = new DBBuilder();
    $result = $builder->selectQuery('do_doktorlar', [ ['id', '<', 1, 'OR'], ['brans', '=', 7], ['ORDER BY id ASC'] ], false);

    var_dump($result);

?>
