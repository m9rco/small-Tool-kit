<?php

/**
 * MangoServer
 *
 * @author   Pu ShaoWei
 * @date     2017/8/3
 * @license  Mozilla
 */
class MangoServer
{
    /**
     * 单例模式实例化本类
     *
     * @var object
     */
    protected static $_instance = null;

    /**
     * 数据库连接实例化对象名
     *
     * @var object
     */
    protected $_dbLink = null;

    /**
     * 数据库名称
     *
     * @var object
     */
    protected $_dbname = null;

    /**
     * mongo实例化对象
     *
     * @var object
     */
    protected $_mongo = null;

    /**
     * 主从库选择
     *
     * @var object
     */
    protected $_master = null;

    /**
     * 数据库连接参数默认值
     *
     * @var array
     */
    protected $_config = array();

    /**
     * [$_options 连接mongo基础选项]
     * @var array
     */
    protected $_options = array(
        'connect' => true
    );

    /**
     * 是否是 Replica Set 模式
     */
    const IS_REPLSET = true;

    /**
     * 获取Db实例 (单例模式)
     *
     * @param string $dbname
     *
     * @return obj
     */

    public static function getInstance($dbname='default')
    {
        if(empty(self::$_instance))
        {
            self::$_instance = new self($dbname);
        }
        return self::$_instance;
    }

    /**
     * 构造方法
     *
     * 用于初始化运行环境,或对基本变量进行赋值
     *
     * @access public
     *
     * @param array $params 数据库连接参数,如主机名,数据库用户名,密码等
     *
     * @return boolean
     */
    public function __construct($dbname='default')
    {
        $this->_dbname = $dbname;
    }

    protected function pick_peer($master)
    {
        $this->_master = $master;
        if (!extension_loaded('mongo')){
            exit('The mongo extension to be loaded!');
        }

        $this->_config = include ECONF.'EDbref.cfg.php';
        $this->_config = array(
                        'host' => array('127.0.0.1:27017','127.0.0.1:27018','127.0.0.1:27019'),
                            'user' => 'test',
                            'passwd' => '',
                            'db' => 'test',
                            'options' => array(
                            'connect' => true,
                            'replicaSet' => 'Test'
                        ));
        // replicaSet 副本集
        if ( self::IS_REPLSET ) {

            $dsn = 'mongodb://' . $this->_config['user'] . ':' .
                $this->_config['passwd'] . '@';

            if ( is_array($this->_config['host']) ) {
                foreach ( $this->_config['host'] as $key => $host) {
                    $dsn .= $host . ',';
                }
                $dsn = substr($dsn, 0, -1);
            }

            $dsn .= '/' . $this->_dbname;
            $this->_options = array_merge($this->_options, $this->_config['options']);

        } else { // 主从 Master-Slave 模式
            if($this->_master)
            {
                $dsn = 'mongodb://'.
                    $this->_config['masterConf'][0]['user'].":".
                    $this->_config['masterConf'][0]['passwd']."@".
                    $this->_config['masterConf'][0]['host'].":".
                    $this->_config['masterConf'][0]['port'];
            }
            else
            {
                $dsn = 'mongodb://';
                foreach($this->_config['slaveConf'] as $key=>$val)
                {
                    $dsn.=
                        $val['user'].":".
                        $val['passwd']."@".
                        $val['host'].":".
                        $val['port'];
                    $dsn.=',';
                }
                $dsn = substr($dsn, 0, -1);
            }

            $dsn .= '/'.$this->_dbname;
        }


        $params = array(
            'dsn'    => $dsn,
            'option' => $this->_options,
            'dbname' =>$this->_dbname,
        );

        if (!isset($params['dbname']) || !$params['dbname'])
        {
            exit('The file of MongoDB config is error, dbname is not found!');
        }

        try {
            //实例化mongo
            $this->_mongo = new \Mongo($params['dsn'], $params['option']);

            //连接mongo数据库
            $this->_dbLink = $this->_mongo->selectDB($params['dbname']);

            //用户登录
            if (isset($params['username']) && isset($params['password']))
            {
                $this->_dbLink->authenticate($params['username'], $params['password']);
            }

            return $this->_dbLink;
        } catch (Exception $exception) {

            //抛出异常信息
            throw new Exception('MongoDb connect error!<br/>' . $exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Select Collection
     *
     * @author ColaPHP
     *
     * @access public
     *
     * @param string $collection 集合名称(相当于关系数据库中的表)
     *
     * @return object
     */
    public function collection($collection,$master)
    {
        $this->_dbLink = $this->pick_peer($master);
        if(!$this->_dbLink){
            return false;
        }
        return $this->_dbLink->selectCollection($collection);
    }

    /**
     * 查询一条记录
     *
     * @access public
     *
     * @param string $collnections 集合名称(相当于关系数据库中的表)
     * @param array $query 查询的条件array(key=>value) 相当于key=value
     * @param array fields 需要列表的字段信息array(filed1,filed2)
     *
     * @return array
     */
    public function getOne($collnections, $query, $fields=array(),$master=false)
    {
        $res =  $this->collection($collnections,$master)->findOne($query, $fields);

        if (!empty($res)) {
            isset($res['_id']) &&  $res['_id'] = (array) $res['_id'];
        }
        return $res;
    }

    /**
     * 查询多条记录
     *
     * @access public
     *
     * @param string $collnections 集合名称(相当于关系数据库中的表)
     * @param array $query 查询的条件array(key=>value) 相当于key=value
     * @param array fields 需要列表的字段信息array(filed1,filed2)
     *
     * @return array
     */
    public function getAll($collnections, $query, $fields=array(),$limit=0,$start=0,$sort=array(),$master=false)
    {

        $limit = intval($limit);
        $start = intval($start);
        if(!$limit){
            $start = 0;
        }

        $result = array();
        $cursor = $this->collection($collnections,$master)->find($query, $fields)->limit($limit)->skip($start)->sort($sort);
        while ($cursor->hasNext()) {
            $res     = $cursor->getNext();
            //mongoid转义
            isset($res['_id']) &&  $res['_id'] = (array) $res['_id'];
            $result[] = $res;
        }

        return $result;
    }

    /**
     * 插入数据
     *
     * @access public
     *
     * @param string $collnections 集合名称(相当于关系数据库中的表)
     * @param array $data 所要写入的数据信息
     *
     * @return boolean
     */
    public function insert($collnections, $data)
    {
        return $this->collection($collnections,true)->insert($data);
    }

    /**
     * 更改数据
     *
     * @access public
     *
     * @param string $collnections 集合名称(相当于关系数据库中的表)
     * @param array $query 查询的条件array(key=>value) 相当于key=value
     * @param array $data 所要更改的信息
     * @param array $options 选项
     *
     * @return boolean
     */
    public function update($collection, $query, $data, $options=array('safe'=>true,'multiple'=>true))
    {
        return $this->collection($collection,true)->update($query, $data, $options);
    }

    /**
     * 删除数据
     *
     * @access public
     *
     * @param string $collnections 集合名称(相当于关系数据库中的表)
     * @param array $query 查询的条件array(key=>value) 相当于key=value
     * @param array $option 选项
     *
     * @return boolean
     */
    public function delete($collection, $query, $option=array("justOne"=>false))
    {
        return $this->collection($collection,true)->remove($query, $option);
    }


    /**
     * 计数 @by wangxiaokang
     *
     * @param string  $collnections 集合名称(相当于关系数据库中的表)
     * @param array   $query 查询的条件array(key=>value) 相当于key=value
     * @param boolean $master true主 false从
     *
     * @return int
     */
    public function count($collection, $query=array(), $master=false)
    {
        return $this->collection($collection, $master)->count($query);
    }

    /**
     * 分组计算 @by wangxiaokang
     *
     * @param array  $keys
     * @param array  $initial  计数器初始值
     * @param string $reduce   文档汇总函数
     * @param array  $options  配置参数
     */
    public function group($collection, array $keys, array $initial, $reduce, $option=array(), $master=false)
    {
        return $this->collection($collection, $master)->group($keys, $initial, $reduce, $option);
    }

    /**
     * 聚合计算 @by wangxiaokang
     *
     * @param array $param ex : array(
     *                            '$project' => array(), //查询字段，支持表达式
     *                          )
     *
     *
     */
    public function aggregate($collection, $param=array(), $master=false)
    {
        return $this->collection($collection, $master)->aggregate($param);
    }

    /**
     * 执行一条命令
     */
    public function command($collection, $command = array(), $master=false)
    {
        return $this->collection($collection, $master)->command($command);
    }

    /**
     * 去重计算 @by wangxiaokang
     *
     * @param array  $keys   字段
     * @param array  $params 查询字段
     */
    public function distinct($collection, $keys, $params=array(), $master=false)
    {
        return $this->collection($collection, $master)->distinct($keys, $params);
    }

    /**
     * MongoId
     *
     * @author ColaPHP
     *
     * @access public
     *
     * @param string $id 获取mongoId
     *
     * @return object
     */
    public static function id($id = null)
    {
        return new \MongoId($id);

    }

    /**
     * MongoTimestamp
     *
     * @author ColaPHP
     *
     * @access public
     *
     * @param int $sec
     * @param int $inc
     *
     * @return MongoTimestamp
     */
    public static function Timestamp($sec = null, $inc = 0)
    {
        if (!$sec)
        {
            $sec = time();
        }

        return new MongoTimestamp($sec, $inc);
    }

    /**
     * GridFS
     *
     * @author ColaPHP
     *
     * @access public
     *
     * @return object
     */
    public function gridFS($prefix = 'fs')
    {
        return $this->_dbLink->getGridFS($prefix);
    }

    /**
     * 析构函数
     *
     * 程序执行完毕，打扫战场
     *
     * @access public
     *
     * @return void
     */
    public function __destruct() {
        if ($this->_dbLink)
        {
            $this->_dbLink = null;
        }
        return true;
    }

}
