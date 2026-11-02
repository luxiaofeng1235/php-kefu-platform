<?php
/*
 * 线上小说操作库，
 * wangyan lixiaofeng 2024-11-28
 *
 */

//$obj=new Mysql_class();
//$sdfsdfs=$obj->fetch("select * from union_user limit 10",$obj->union_slave);

class  mysql_class
{

    public $db_master = 'db_master'; //主库

    // 手动加载.env文件，确保配置可用
    private function loadEnv()
    {
        $envFile = dirname(__DIR__) . '/.env';
//        echo "Debug: Loading .env from: " . $envFile . "\n";
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $value = trim($value, '"\''); // 去除引号

                    // echo "Debug: Parsed Key: $key\n"; // 打印解析到的Key
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        } else {
            echo "Debug: .env file not found!\n";
        }
    }

    //连接mysql的列表
    private function mysqlList()
    {
        $this->loadEnv(); // 强制加载环境变量
        $list = array();
        // Master Database Configuration
        $master_config = [
            'host' => getenv('DB_MYSQL_HOST') ?: (getenv('DB_MYSQL_HOST') ?: '127.0.0.1'),
            'username' => getenv('DB_MYSQL_USERNAME') ?: (getenv('DB_MYSQL_USERNAME') ?: 'root'),
            'password' => getenv('DB_MYSQL_PASSWORD') ?: (getenv('DB_MYSQL_PASSWORD') ?: ''),
            'db_name' => getenv('DB_MYSQL_DATABASE') ?: (getenv('DB_MYSQL_DATABASE') ?: 'test_db'),
            'port' => getenv('DB_MYSQL_PORT') ?: (getenv('DB_MYSQL_PORT') ?: '3306')
        ];
        //联盟主库master
        $list['db_master'] = array(
            'dsn' => 'mysql:host=' . $master_config['host'] . ';port=' . $master_config['port'] . ';dbname=' . $master_config['db_name'],
            'user' => $master_config['username'],
            'password' => $master_config['password']
        );
        return $list;
    }

    /**
     * @note 返回mysql的句柄并进行连接PDO操作
     *
     * @param [str] $[$db_name] [<连接的句柄>]
     * @return object|bool
     * @author [xiaofeng] <[<luxiaofneg.200@163.com>]>
     * @Date 2020-12-21
     */
    private function getMysqlInfo($db_name)
    {
        if ($db_name) {
            $info = $this->mysqlList();
            if ($info[$db_name]) {
                //測試連接應用
                try {
                    //根据PDO来进行连接，主要用于进行PDO连接
                    @$db = new PDO($info[$db_name]['dsn'], $info[$db_name]['user'], $info[$db_name]['password'], array(PDO::ATTR_PERSISTENT => true));
                    //主要用来设置POD链接失败会抛出来一个异常
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $db->query("set names utf8");
                    return $db;
                } catch (PDOException $e) {
                    echo 'Connection failed: ' . $e->getMessage();
                }
            }
        }
        return null;
    }

    /**
     * 获取条记录，并返回数组
     * @param string $sql
     * @param string $db_name
     * @return bool
     */
    public function fetch($sql, $db_name)
    {
        $db_obj = $this->getMysqlInfo($db_name);
        $date = $db_obj->query($sql)->fetch();
        if (isset($date) && !empty($date)) {
            return $date;
        }
        return false;
    }


    /**
     * 根据条件进行查询
     * @param arr $where
     *        condition =>[] 查询的关联数组
     *        sort_set => [] 排序依据 取决于是否需要排序 输入类型：数组或者单个字符
     *        sort_range =>[] 排序的降序或者升序 desc:降序 asc:升序 输入类型：数组或者单个字符
     *        group_by => '' 按照某个字段来进行排序
     *        limit => ''    查询限制个数，主要做分页会用到 可以是数组或者具体的字符 如 5 |[5,10]
     * @param string $filed 查询字段
     * @param string $db_name 数据库连接句柄
     * @return object|unknow
     */
    function get_data_by_condition($where = '', $table_name = '', $field = '*', $debug = false, $db_name = 'db_slave')
    {
        //插入的参数字段检测==只有判断为数组的时候才进行插入
        if ($where && is_array($where)) {
            $check_key = array_keys($where);
            $flag = true;
            $error_msg = [
                'error_code' => '300001',
                'error_msg' => '传入字段参数无效'
            ];
            //默认的字段类型
            $input_param = [
                'condition', '
			    sort_set',
                'sort_range',
                'group_by',
                'limit',
            ];
            foreach ($check_key as $ckey) {
                if (!in_array($ckey, $input_param)) {
                    $flag = false;
                }
            }
            if (!$flag) {
                return $error_msg;
            }
        }
        $select = $field ? $field : '*';
        $sql = 'SELECT ' . $select . ' FROM `' . $table_name . '` WHERE ';
        if (!is_array($where)) {
            $where = stripslashes($where);
            $sql .= $where;
        } else {
            //按照数组来进行查找
            //按照多维度数组进行排序
            /*
            $arr = array(
                'condition' =>array('upid'=>87,'city_code'=>130800),
                // 'sort_set'	=>'id',
                // 'sort_range'	=>'desc',
                'limit' 	=>'10,5',
            );
             */
            $condition = '';
            $where_data = isset($where['condition']) ? $where['condition'] : [];
            $sort_set = isset($where['sort_set']) ? $where['sort_set'] : ''; //排序依据
            $sort_range = isset($where['sort_range']) ? $where['sort_range'] : ''; //排序方式
            $group_by = isset($where['group_by']) ? trim($where['group_by']) : ''; //分组依据
            $limit = isset($where['limit']) ? $where['limit'] : 0;

            if ($where_data) {
                foreach ($where_data as $k => $v) {
                    if (!$v) continue;
                    $condition .= "`{$k}`='{$v}'" . (count($where_data) > 1 ? ' AND' : '');
                }
                $condition = rtrim($condition, 'AND');
            }
            if ($condition) {
                $sql .= $condition;
            } else {
                $sql .= "true"; //主要为了防止没有条件会报错
            }

            if ($group_by != '') {
                $sql .= " GROUP BY " . $group_by;
            }

            if ($sort_set) {//排序依据处理
                $sort_str = '';
                if (is_array($sort_set)) {
                    $sort_item = '';
                    foreach ($sort_set as $sk => $sv) {
                        $sort_item .= "`{$sv}` " . (isset($sort_range[$sk]) ? $sort_range[$sk] : '') . ',';
                    }
                    if ($sort_item) {
                        $sort_item = rtrim($sort_item, ',');
                        $sort_str = $sort_item;
                        if (isset($sort_item))
                            unset($sort_item);
                    }
                } else {
                    $sort_str = $sort_set . ' ' . ($sort_range ? $sort_range : '');
                }


                $sql .= " ORDER BY " . $sort_str;
            }
            //分页
            if ($limit) {
                $sql .= " LIMIT " . (is_array($limit) ? join(',', $limit) : $limit);
            }
        }
        // return $sql;

        //查询所有的数据进行解析
        $result = $this->fetchAll($sql, $db_name);
        if ($result && is_array($result)) {
            if ($debug) {
                return [
                    'mysql_query' => $sql,
                    'list' => $result
                ];
            } else {
                return $result;
            }
        }
        return [];
    }

    /**
     * 获取结果集，返回tables
     * @param string $sql
     * @param string $db_name
     * @return  bool
     */
    public function fetchAll($sql, $db_name)
    {
        $db_obj = $this->getMysqlInfo($db_name);
        $date = $db_obj->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (isset($date) && !empty($date)) {
            return $date;
        }
        return false;
    }

    /**
     * 执行一个sql,成功返回sql语句,失败返回空
     * @param string $sql
     * @param string $db_name
     * @return string or  false
     */
    public function query($sql, $db_name)
    {
        $db_obj = $this->getMysqlInfo($db_name);
        $date = $db_obj->query($sql);
        if (isset($date) && !empty($date)) {
            return $date;

        }
        return false;
    }

    /**
     * @note 生成批量添加sql语句
     *
     * @param [object] $[data] [<待添加的数据>]
     * @param [string] $[table_name] [<表名称>]
     * @return bool
     */
    public function add_data($data, $table_name = '')
    {
        if (!$data || !$table_name) {
            return false;
        }
        $db_name = $this->db_master;
        $sql = "insert into `{$table_name}`";
        if (!isset($data[0])) {
            $item[] = $data;
        } else {
            $item = $data;
        }
        //取出来第一个的键值来处理

        $push_key = isset($item[0]) ? implode(',', array_keys($item[0])) : '';
        // $order_sns=array_map('trim',explode("\n",$order_sns));
        $sql .= " (" . $push_key . ") values ";
        $inner_sql = '';
        foreach ($item as $key => $val) {
            $tkey = [];
            foreach ($val as $k => $v) {
                $v = @addslashes($v); //有必要进行转义下
                if (!is_array($v)) {
                    $tkey[] = "'" . $v . "'";
                }
            }
            $dk = array_keys($val);
            $dd = array_values($val);
            $inner_sql .= "(" . implode(',', array_values($tkey)) . "),";
        }
        if ($inner_sql) {
            $inner_sql = rtrim($inner_sql, ',');
            $sql .= $inner_sql;
        }
        if ($sql) {//开始添加数据
            $db_obj = $this->getMysqlInfo($db_name);
            $ret = $db_obj->query($sql);
            if ($ret) {
                return $db_obj->lastInsertId(); //返回成功插入的id
                // return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

    /**
     * @note 批量生成更新语句
     *
     * @param [object] $[data] [<待添加的数据>]
     * @param [where] $[str] [<更新条件>]
     * @param [string] $[table_name] [<表名称>]
     * @return bool
     */
    function update_data($data = [], $where = '', $table_name = '', $limit = false, $limit_size = 0)
    {
        if (!$data || !$where) {
            return false;
        }
        $sp_where = '';
        //转义特殊字符
        if (get_magic_quotes_gpc()) {

            $sp_where = is_array($where) ?
                array_map('stripslashes_deep', $where) :
                stripslashes($where);
        } else {
            $sp_where = $where;
        }

        $data = array_filter($data);
        $data = array_unique($data);

        //拼装需要更新的语句
        $sql = "UPDATE `{$table_name}` SET ";
        if ($data && is_array($data)) {
            foreach ($data as $k => $v) {
                $tval[] = "`$k`='{$v}'";
            }
        }
        $update_data = join(',', $tval);
        if (isset($tval))
            unset($tval);
        if (!empty($update_data)) {
            $sql .= $update_data;
        }
        $sql .= ' WHERE ' . $where;
        if ($limit && $limit_size) {
            $sql .= " LIMIT " . $limit_size;
        }
        //exec mysql
        if ($sql) {
            $db_name = $this->db_master;
            $db_obj = $this->getMysqlInfo($db_name);
            if ($db_obj->query($sql)) {
                return 1;
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * 封装删除操作
     * @date 2020.12.23
     * @param string $sql
     * @param string $db_name
     * @return string or  false
     * @author [xiaofeng] <[<luxiaofeng.200@163.com>]>
     *
     */
    public function delete($where = '', $table_name = '')
    {

        $db_name = $this->db_master; //默认启用master来删除

        if (!$where)
            return false;

        $db_obj = $this->getMysqlInfo($db_name);
        $sql = "delete from " . $table_name . " where " . $where;

        $res = $db_obj->query($sql);
        if ($res) {
            return 1;
        } else {
            return 0;
        }
    }

}