<?php

    class DB {

        protected const DBUSERNAME = 'root';
        protected const DBPASSWORD = '';
        protected const SERVERNAME = 'localhost';
        protected const DBNAME     = 'mazotdb';

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
    }

    class BuilderResponse{
        
        public $data;
        public $error     = null;
        public $errorCode = null;

        public function __construct($data, Throwable|null $error = null) {
            $this->data = $data;
            if ($error) {
                $this->error     = $error->getMessage();
                $this->errorCode = $error->getCode();
            }

            return $this;
        }

    }

    class UpdateDeleteRow{

        public $rows;
        public $beforeRows;

        public function __construct(array|null $rows, array|null $beforeRows) {
            
            $this->rows         = $rows;
            $this->beforeRows   = $beforeRows;

            return $this;
        }
    }

    final class DBBuilder extends DB{

        public function __construct() {
            parent::__construct();
        }

        /** Değişkenin PDO tipini döndüren fonksiyon
         * @param mixed $val
         * @return PDO::PARAM_BOOL|PDO::PARAM_STR
         */
        private function getPDOType($val){

            if (gettype($val) === 'boolean') {
                return PDO::PARAM_BOOL;
            }

            return PDO::PARAM_STR;
        }

        /** Standart return fonksiyonu
         * @param mixed $data
         * @param Throwable|null $error
         */
        private function res($data, Throwable|null $error = null){
            return new BuilderResponse($data, $error);
        }

        /** Verilen değişken string ise sağındaki ve solundaki boşlukları siler
         */
        private function killSpace($val){
            if (gettype($val) === 'string') {
                $val = trim($val);
            }
            return $val;
        }

        public function getUser( $id ){
            try {
                $stmt = $this->db->prepare("SELECT * FROM user WHERE id = :id");

                $stmt->bindParam('id', $id, PDO::PARAM_INT);

                $stmt->execute([$id]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return $this->res(['user' => $user]);
            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        public function getUsers(){
            try {
                $stmt = $this->db->prepare("SELECT * FROM user");

                $stmt->execute();

                $user = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return $this->res(['user' => $user]);
            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        public function insertUser( $username, $password, $token ) {
            try {
                $stmt = $this->db->prepare("INSERT INTO user (username, password, token) VALUES (?,?,?), (?,?,?)");

                $stmt->execute([$username, $password, $token, 'fako', 'fako', 'asdasdas']);

                return $this->res(true);

            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        public function deleteUser( $id ) {
            try {

                $stmt = $this->db->prepare("DELETE FROM user WHERE id = ?");

                $xx = $stmt->execute([$id]);

                return $this->res($xx);

            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        /** Çoklu ve tekli veri çekebilen select sorgusu
         * @param string $table
         * @param array|string|integer $query
         * @param boolean $multiple
         * $builder->select('do_doktorlar', [ ['id', '<', 1, 'OR'], ['brans', '=', 7], ['ORDER BY id ASC'] ], false);
         * $builder->select('do_doktorlar', 24, false)
         * $builder->select('user', [['username', 'LIKE', 'user', 'AND'], ['tarih', '<', (new DateTime())->format('Y-m-d')]])
         */
        public function select( $table, $query = [], $multiple = true ){
            try {
            
                $sql = "SELECT * FROM $table";

                $queryParams = [];

                if (!empty($query)) {
                    $sql = $sql.' WHERE';
                }

                if (gettype($query) !== 'array') {
                    $queryParams[] = [ 'key' => 'id', 'value' => $this->killSpace($query), 'type' => $this->getPDOType($query) ];
                    $sql = $sql.' id = :id';
                }
                else{
                    foreach($query AS $q){
    
                        $sql = $sql.' '.$q[0];
    
                        if (isset($q[1]) && isset($q[2])) {
    
                            $sql = $sql.' '.$q[1].' :'.$q[0].' ';
                            
                            $param = [ 'key' => ':'.$q[0], 'value' => $this->killSpace($q[2]), 'type' => $this->getPDOType($q[2])];
    
                            if (strtolower($q[1]) === strtolower('LIKE')) {
                                $param['value'] = '%'.$q[2].'%';
                            }
    
                            $queryParams[] = $param;
                           
                            if (isset($q[3])) {
                                $sql = $sql.$q[3];
                            }
                        }
                    }
                }

                $stmt = $this->db->prepare($sql);

                foreach ($queryParams as $item) {
                    $stmt->bindValue($item['key'], $item['value'], $item['type']);
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
                return $this->res(null, $th, $sql);
            }
        }

        /** update ve delete içerisinde kullanmak üzere ilgili şartları sağlayan satırları bulan fonksiyon
         * @param string $table
         * @param array|string|integer $query
         * @return UpdateDeleteRow
         * @throws Exception
         */
        private function selectRowsForDeleteOrUpdate( $table, $query = [] ){

            $select = $this->select($table, $query);
            
            if ($select->error) {
                throw new Exception($select->error);
            }

            if (empty($select->data)) {
                throw new Exception('İşlem yapılacak kayıtlar bulunamadı', -1);
            }

            $rows = [];
            
            foreach ($select->data as $item) {
                $rows[] = $item['id'];
            }

            return new UpdateDeleteRow($rows, $select->data);
        }

        /** Tekli ve Çoklu veri ekleyebilen metot
         * @param string $table 
         * @param string $columns 
         * @param array $values 
         * @return BuilderResponse
         * $builder->insert('user', 'username, password, token', ["yenikentbaabakar, 123456, qweqwe", "mazot06, umut5248,qwexcaq", "karamanlimm, allods06, pqwdnmj"]);
         */
        public function insert( $table, $columns, $values){
            try {
                    
                $sql = "INSERT INTO $table (";

                $columns = explode(',', $columns);

                foreach ($columns as $key => $col) {

                    $sql = "$sql $col";

                    if ($key+1 !== count($columns)) {
                        $sql = $sql.',';
                    }
                    else{
                        $sql = "$sql ) VALUES";
                    }

                }
                
                foreach ($values as $key => $value) {

                    $sql = "$sql (";

                    $value = explode(',', $value);

                    if (count($value) !== count($columns)) {
                        throw new Exception('Parametre Uyuşmazlığı');
                    }
                    

                    foreach ($value as $subKey => $subValue) {
                        $sql = "$sql ?";
                        if( $subKey+1 !== count($value) ){
                            $sql = $sql.',';
                        }
                        else{
                            $sql = "$sql )";
                        }

                        $prepareValue[] = $this->killSpace($subValue);

                    }

                    if ($key+1 !== count($values)) {
                        $sql = $sql.',';
                    }
                }

                $stmt = $this->db->prepare($sql);

                $stmt->execute($prepareValue);

                return $this->res(true);

            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        /** Delete sorgusu, select sorgusu ile bir çalışır
         * @param string $table
         * @param array|string|integer $query
         * @return BuilderResponse
         * $builder->delete('user', [ ['id', '>', 2, 'AND'], ['username', 'LIKE', 'aman'] ])
         * $builder->delete('user', [ ['tarih', '<', (new DateTime())->format('Y-m-d')] ])
         * $builder->delete('user', 30)

         */
        public function delete( $table, $query = [] ) {

            try {

                $items = $this->selectRowsForDeleteOrUpdate($table, $query);

                $rows = $items->rows;

                $beforeRows = $items->beforeRows;

                $sql = "DELETE FROM $table WHERE id IN (".implode(',', $rows).")";

                $stmt = $this->db->prepare($sql);

                $stmt->execute();

                return $this->res($beforeRows);

            } catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

        /** Update sorgusu, select sorgusu ile bir çalışır
         * @param string $table
         * @param array $columns
         * @param array $values
         * @param array|string|integer $query
         * @return BuilderResponse
         * $builder->update('user', ['password'], ['yeniPartiSifre'], [ ['tarih', '<', (new DateTime())->format('Y-m-d'), 'AND'], ['username', 'LIKE', '2'] ])
         */
        public function update( $table, $columns, $values, $query = [] ) {

            try {

                if (count($columns) !== count($values)) {
                    throw new Exception('Parametre uyuşmazligi');
                }

                $items = $this->selectRowsForDeleteOrUpdate($table, $query);

                $rows = $items->rows;

                $beforeRows = $items->beforeRows;

                $sql = "UPDATE $table SET";

                foreach ($columns as $key => $column) {
                    $sql = "$sql $column = ?";
                    if (count($columns) !== $key + 1) {
                        $sql = $sql.',';
                    }
                }

                foreach ($values as $key => $value) {
                    $values[$key] = $this->killSpace($value);
                }

                $sql = "$sql WHERE id IN (".implode(',', $rows).")";

                $stmt = $this->db->prepare($sql);

                $stmt->execute($values);

                return $this->res($beforeRows);
            } 
            catch (\Throwable $th) {
                return $this->res(null, $th);
            }
        }

    }

    $builder = new DBBuilder();
?>