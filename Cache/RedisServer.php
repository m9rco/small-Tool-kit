<?php
namespace Cache;
use Redis;
use Yaf;
/**
 * RedisServer [REmote DIctionary Serve 驱动器] 
 *
 * ============================================================
 * 五种数据结构：String / Hash / List / Set / Ordered Set。
 * ============================================================
 * 
 * Redis默认最大连接数是一万。
 * Redis默认不对Client做Timeout处理，可以用timeout 项配置，但即使配了也不会非常精确。
 * 
 * - Key
 * Key 不能太长，比如1024字节，但antirez也不喜欢太短如"u:1000:pwd"，要表达清楚意思才好。
 * 私人建议用":"分隔域，用"."作为单词间的连接，如"comment:1234:reply.to"。
 *
 * - Set
 * Set就是Set，可以将重复的元素随便放入而Set会自动去重，底层实现也是hash table。
 * 
 * - String
 * 最普通的key-value类型，说是String，其实是任意的byte[]，比如图片，最大512M。
 * 所有常用命令的复杂度都是O(1)，普通的Get/Set方法，可以用来做Cache，存Session，为了简化架构甚至可以替换掉Memcached。
 *
 * - Hash
 * Key-HashMap结构，相比String类型将这整个对象持久化成JSON格式，Hash将对象的各个属性存入Map里，
 * 可以只读取/更新对象的某些属性。这样有些属性超长就让它一边呆着不动，另外不同的模块可以只更新自己关心的属性而不会互相并发覆盖冲突
 *
 * - List
 * List是一个双向链表，支持双向的Pop/Push，江湖规矩一般从左端Push，右端Pop——LPush/RPop，而且还有Blocking的版本BLPop/BRPop.
 * 客户端可以阻塞在那直到有消息到来，所有操作都是O(1)的好孩子，可以当Message Queue来用。
 * 当多个Client并发阻塞等待，有消息入列时谁先被阻塞谁先被服务。任务队列系统Resque是其典型应用。
 * 
 */
class RedisServer 
{
    private static $_main;
	private static $_follow;
	private $_redis;

	/**
	 * [__construct 初始化Redis连接]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2016-12-24T16:50:16+0800
	 */
	final private function __construct($config,$select)
	{
		$this->_redis = new Redis();
		if(!empty($config))
		{
            // 创建连接
            $this->_redis ->connect(
                $config->redis_host,
                $config->redis_port,
                $config->redis_timeout
            );
			if(is_resource($this->_redis->socket) && !empty($config->redis_auth)){
				$this->_redis->auth($config->redis_auth);
			}
            $this->_redis->select($select);
		}
		return $this->_redis;
	}

	/**
	 * [__clone 禁用克隆]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2016-12-23T20:51:24+0800
	 * @return                              [type] [boole]
	 */
	final private function __clone(){} 
	/**
	 * [getInstance 创建单例]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2016-12-23T20:44:29+0800
	 * @return                              [object] [object]
	 */
	static public function getMain($select = 0)
	{
		if(!(self::$_main instanceof self))
		{
            $config = new Yaf\Config\Ini(dirname(__FILE__).'/../../config.ini');
			self::$_main = new self($config->redisMain,$select);
		}
		return self::$_main;
	}
    static public function getFollow($select = 0)
    {
     if(!(self::$_follow instanceof self))
        {
            $config = new Yaf\Config\Ini(dirname(__FILE__).'/../../config.ini');
            self::$_follow = new self($config->redisFollw,$select);
        }
        return self::$_follow;
    }

    /**
     * [setStr 设定一条String]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:06:15+0800
     * @param                               [type] $key    [String]
     * @param                               [type] $text   [String]
     * @param                               [type] $expire [Boole]
     */
    public function setStr($key, $text, $expire = null)
    {
        $key = 'string:' . $key;
        $this->_redis->set($key, $text);
        if (!is_null($expire)) {
            $this->_redis->setTimeout($key, $expire);
        }
    }
   	/**
   	 * [getStr 获取一条String]
   	 * @author 		Shaowei Pu 
   	 * @CreateTime	2016-12-24T16:13:25+0800
   	 * @param                               [type] $key [String]
   	 * @return                              [type]      [Data]
   	 */
    public function getStr($key)
    {
        $key = 'string:' . $key;
        $text = $this->_redis->get($key);
        return empty($text) ? null : $text;
    }

   	/**
   	 * [delStr 删除一条String]
   	 * @author 		Shaowei Pu 
   	 * @CreateTime	2016-12-24T16:13:51+0800
   	 * @param                               [type] $key [String]
   	 * @return                              [type]      [Boole]
   	 */
    public function delStr($key)
    {
        $key = 'string:' . $key;
        $this->_redis->del($key);
    }
    /**
     * [setHash 设置一条Hash]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:14:38+0800
     * @param                               [type] $key    [String]
     * @param                               [type] $arr    [String || Array]
     * @param                               [type] $expire [description]
     */
    public function setHash($key='', $arr='', $expire = null)
    {
        if(empty($key) || empty($arr)) return false;
        
        $key = 'hash:' . $key;
        $this->_redis->hMset($key, $arr);
        if (!is_null($expire)) {
            $this->_redis->setTimeout($key, $expire);
        }
    }
    /**
     * [getHash 获取一条Hash]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:15:09+0800
     * @param                               [type] $key    [String]
     * @param                               [type] $fields [String || Array]
     * @return                              [type]         [Data]
     */
    public function getHash($key, $fields = null)
    {
        $key = 'hash:' . $key;
        if (is_null($fields)) {
            $arr = $this->_redis->hGetAll($key);
        } else {
            if (is_array($fields)) {
                $arr = $this->_redis->hmGet($key, $fields);
                foreach ($arr as $key => $value) {
                    if ($value === false) {
                        unset($arr[$key]);
                    }
                }
            } else {
                $arr = $this->_redis->hGet($key, $fields);
            }
        }
        return empty($arr) ? null : (is_array($arr) ? $arr : array($fields => $arr));
    }
    /**
     * [delHash 删除一条Hash]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:15:38+0800
     * @param                               [type] $key   [String]
     * @param                               [type] $field [String]
     * @return                              [type]        [Boole]
     */
    public function delHash($key, $field = null)
    {
        $key = 'hash:' . $key;
        if (is_null($field)) {
            $this->_redis->del($key);
        } else {
            $this->_redis->hDel($key, $field);
        }
    }
    /**
     * [fieldAddVal 在Hash的field内增加一个值(值之间使用“,”分隔)]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:16:50+0800
     * @param                               [type] $key   [String]
     * @param                               [type] $field [String]
     * @param                               [type] $val   [String]
     * @return                              [type]        [Boole]
     */
    public function fieldAddVal($key, $field, $val)
    {
        $arr = $this->getHash($key, $field);
        if (!is_null($arr)) {
            $str = reset($arr);
            $arr = explode(',', $str);
            foreach ($arr as $v) {
                if ($v == $val) {
                    return;
                }
            }
            $str .= ",{$val}";
            $this->setHash($key, array($field => $str));
        } else {
            $this->setHash($key, array($field => $val));
        }
    }
    /**
     * [fieldDelVal 在Hash的field内删除一个值]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:17:24+0800
     * @param                               [type] $key   [String]
     * @param                               [type] $field [String]
     * @param                               [type] $val   [String]
     * @return                              [type]        [Boole]
     */
    public function fieldDelVal($key, $field, $val)
    {
        $arr = $this->getHash($key, $field);
        if (!is_null($arr)) {
            $arr = explode(',', reset($arr));
            $tmpStr = '';
            foreach ($arr as $v) {
                if ($v != $val) {
                    $tmpStr .= ",{$v}";
                }
            }
            if ($tmpStr == '') {
                $this->delHash($key, $field);
            } else {
                $this->setHash($key, array($field => substr($tmpStr, 1)));
            }
        }
    }
    /**
     * [setTableRow 设置表格的一行数据]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:17:35+0800
     * @param                               [type] $table  [String]
     * @param                               [type] $id     [String]
     * @param                               [type] $arr    [String]
     * @param                               [type] $expire [Boole]
     */
    public function setTableRow($table, $id, $arr, $expire = null)
    {
        $key = '' . $table . ':' . $id;
        $this->_redis->hMset($key, $arr);
        if (!is_null($expire)) {
            $this->_redis->setTimeout($key, $expire);
        }
    }
    /**
     * [getTableRow 获取表格的一行数据]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:17:54+0800
     * @param                               [type] $table  [String]
     * @param                               [type] $id     [String]
     * @param                               [type] $fields [String || Array]
     * @return                              [type]         [Data]
     */
    public function getTableRow($table, $id, $fields = null)
    {
        $key = '' . $table . ':' . $id;
        if (is_null($fields)) {
            $arr = $this->_redis->hGetAll($key);
        } else {
            if (is_array($fields)) {
                $arr = $this->_redis->hmGet($key, $fields);
                foreach ($arr as $key => $value) {
                    if ($value === false) {
                        unset($arr[$key]);
                    }
                }
            } else {
                $arr = $this->_redis->hGet($key, $fields);
            }
        }
        return empty($arr) ? null : (is_array($arr) ? $arr : array($fields => $arr));
    }
    /**
     * [delTableRow 删除表格的一行数据]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:18:31+0800
     * @param                               [type] $table [String]
     * @param                               [type] $id    [String]
     * @return                              [type]        [Boole]
     */
    public function delTableRow($table, $id)
    {
        $key = '' . $table . ':' . $id;
        $this->_redis->del($key);
    }
    /**
     * [pushList 推送一条数据至列表，头部]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:18:40+0800
     * @param                               [type] $key [String]
     * @param                               [type] $arr [String]
     * @return                              [type]      [Boole]
     */
    public function pushList($key, $arr)
    {
        $key = 'list:' . $key;
        $this->_redis->lPush($key, json_encode($arr));
    }
    /**
     * [pullList 从列表拉取一条数据，尾部（堵塞时间受限于php的default_socket_timeout）]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:18:52+0800
     * @param                               [type]  $key     [String]
     * @param                               integer $timeout [String]
     * @return                              [type]           [Boole]
     */
    public function pullList($key, $timeout = 0)
    {
        $key = 'list:' . $key;
        if ($timeout > 0) {
            $val = $this->_redis->brPop($key, $timeout); // 该函数返回的是一个数组, 0=key 1=value
        } else {
            $val = $this->_redis->rPop($key);
        }
        $val = is_array($val) && isset($val[1]) ? $val[1] : $val;
        return empty($val) ? null : $this->objectToArray(json_decode($val));
    }
    /**
     * [getListSize 取得列表的数据总条数]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:19:19+0800
     * @param                               [type] $key [String]
     * @return                              [type]      [Data]
     */
    public function getListSize($key)
    {
        $key = 'list:' . $key;
        return $this->_redis->lSize($key);
    }
    /**
     * [delList 删除列表]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:19:35+0800
     * @param                               [type] $key [String]
     * @return                              [type]      [Boole]
     */
    public function delList($key)
    {
        $key = 'list:' . $key;
        $this->_redis->del($key);
    }
    /**
     * [objectToArray 用递归，将stdClass转为array]
     * @author 		Shaowei Pu 
     * @CreateTime	2016-12-24T16:19:44+0800
     * @param                               [type] $obj [obj]
     * @return                              [type]      [obj]
     */
    protected function objectToArray($obj)
    {
        if (is_object($obj)) {
            $arr = (array) $obj;
        }
        if (is_array($obj)) {
            foreach ($obj as $key => $value) {
                $arr[$key] = $this->objectToArray($value);
            }
        }
        return !isset($arr) ? $obj : $arr;
    }
    /**
     * [__call 其他属性]
     * @author         Shaowei Pu 
     * @CreateTime    2016-12-26T16:31:32+0800
     * @param                               [type] $name  [description]
     * @param                               [type] $param [description]
     * @return                              [type]        [description]
     */
    public function __call($name,$param)
    {
        if(method_exists($this->_redis,$name))
        {
            call_user_func_array(array($this->_redis, $name), $param);
        }
    }

	/**
	 * [getStatus 获取当前实例状态]
	 * @author 		Shaowei Pu 
	 * @CreateTime	2016-12-23T21:01:54+0800
	 * @return                              [type] [Boole]
	 */
	public function getStatus()
	{
		return is_resource($this->_redis->socket) && ($this->_redis->ping() == '+PONG');
	}
}